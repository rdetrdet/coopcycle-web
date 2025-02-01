<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RouteStamp;
use Monolog\Attribute\AsMonologProcessor;

#[AsMonologProcessor]
class MessengerRouteProcessor extends MessengerStampProcessor
{
    public function __invoke(array $record): array
    {
        $stamp = $this->getStamp();

        if ($stamp instanceof RouteStamp) {
            $record['extra']['requests'] = [
                [
                    'controller' => $stamp->getController(),
                    'route' => $stamp->getRoute()
                ]
            ];
        }

        return $record;
    }
}
