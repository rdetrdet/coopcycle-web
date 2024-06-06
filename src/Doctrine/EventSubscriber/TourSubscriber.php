<?php

namespace AppBundle\Doctrine\EventSubscriber;


use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\Tour;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;


class TourSubscriber implements EventSubscriber
{
    private $logger;

    public function __construct(
        LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush
        );
    }

    /**
     * Trickles down the assignment information from tour to tasks
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        $taskCollectionItems = array_filter($entities, function ($entity) {
            return $entity instanceof TaskCollectionItem;
        });

        foreach ($taskCollectionItems as $taskCollectionItem) {

            $taskCollection = $taskCollectionItem->getParent();

            // When a TaskCollectionItem has been removed, its parent is NULL.
            if (!$taskCollection) {
                $entityChangeSet = $uow->getEntityChangeSet($taskCollectionItem);
                [ $oldValue, $newValue ] = $entityChangeSet['parent'];
                $taskCollection = $oldValue;
                $removed = true;
            } else {
                $removed = false;
            }

            if ($taskCollection instanceof Tour) {
                $this->logger->debug(sprintf('Tour modification: processing TaskCollectionItem #%d', $taskCollectionItem->getId()));
                if (!$removed && $taskCollection->getTaskListItem()) { // tour is assigned and the item belongs to it
                    $item = $taskCollection->getTaskListItem();
                    $taskList = $item->getParent();
                    $this->logger->debug(sprintf('Tour modification: Task #%d needs to be assigned', $taskCollectionItem->getTask()->getId()));
                    $taskCollectionItem->getTask()->assignTo($taskList->getCourier(), $taskList->getDate());
                } else if ($removed && $taskCollection->getTaskListItem()) { // tour is assigned and the item was removed
                    $this->logger->debug(sprintf('Tour modification: Task #%d needs to be unassigned', $taskCollectionItem->getTask()->getId()));
                    $taskCollectionItem->getTask()->unassign();
                }
            }
        }

        $uow->computeChangeSets();
    }
}
