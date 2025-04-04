<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskListRepository;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Integration\Standtrack\StandtrackClient;
use AppBundle\Service\RoutingInterface;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Contracts\Translation\TranslatorInterface;

class MarkAsDoneHandler
{

    public function __construct(
        private RecordsMessages $eventRecorder,
        private TranslatorInterface $translator,
        private TaskListRepository $taskListRepository,
        private readonly RoutingInterface $routing,
        private LoggerInterface $logger,
        private StandtrackClient $standtrackClient
    )
    {}

    public function __invoke(MarkAsDone $command)
    {
        /** @var Task $task */
        $task = $command->getTask();

        // TODO Use StateMachine?

        if ($task->isCompleted()) {
            throw new TaskAlreadyCompletedException(sprintf('Task #%d is already completed', $task->getId()));
        }

        if ($task->isCancelled()) {
            throw new TaskCancelledException(sprintf('Task #%d is cancelled', $task->getId()));
        }

        if ($task->hasPrevious() && !$task->getPrevious()->isCompleted()) {
            throw new PreviousTaskNotCompletedException(
                $this->translator->trans('tasks.mark_as_done.has_previous', [
                    '%failed_task%' => $task->getId(),
                    '%previous_task%' => $task->getPrevious()->getId(),
                ])
            );
        }

        $this->eventRecorder->record(new Event\TaskDone($task, $command->getNotes()));

        //TODO: Make this async
        if (!empty($task->getIUB())) {
            try {
                $this->standtrackClient->markDelivered($task->getBarcode(), $task->getIUB());
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to mark task[id=%d] as delivered on Standtrack: %s', $task->getId(), $e->getMessage()));
            }
        }

        $task->setStatus(Task::STATUS_DONE);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }

        $this->calculateCo2Impact($task);
    }

    private function calculateCo2Impact(Task $task) {

        $taskList = $this->taskListRepository->findLastTaskListByTask($task);

        if (!$taskList) {
            $this->logger->error('Task was marked as finished but no corresponding tasklist was found'); // should not happen
            return;
        }

        $coordinates = [];
        $vehicle = $taskList->getVehicle();

        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }

        foreach ($taskList->getTasks() as $item) {
            $coordinates[] = $item->getAddress()->getGeo();
        }

        // going back to the warehouse
        if (!is_null($vehicle)) {
            $coordinates[] = $taskList->getVehicle()->getWarehouse()->getAddress()->getGeo();
        }


        if (count($coordinates) <= 1) {
            return;
        }

        // TODO : if we saved the whole route on the TaskList (not just the distance) we would not have to recalculate the legs here
        $route = $this->routing->route(...$coordinates)['routes'][0];

        if (!is_null($vehicle)) {
            $legs = array_slice($route["legs"], 0, -1); // return to the warehouse is not materialized by a task
            foreach ($legs as $index => $leg) {
                $current = $taskList->getTasks()[$index];
                if ($current->getId() === $task->getId()) {
                    $emissions = intval($vehicle->getCo2emissions() * $leg['distance'] / 1000);
                    $task->setTraveledDistanceMeter(intval($leg['distance'])); // in meter
                    $task->setEmittedCo2($emissions);
                    break;
                }
            }
        } else {
            $legs = $route["legs"];
            foreach ($legs as $index => $leg) {
                $current = $taskList->getTasks()[$index + 1]; // we assume we start at the first task, as there is no warehouse
                if ($current->getId() === $task->getId()) {
                    $task->setTraveledDistanceMeter(intval($leg['distance'])); // in meter
                    $task->setEmittedCo2(0);
                    break;
                } // reset
            }
        }
    }
}
