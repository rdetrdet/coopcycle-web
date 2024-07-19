<?php

namespace AppBundle\Utils\Barcode;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Package;
use Doctrine\ORM\Mapping\Entity;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeUtils {

    const WITHOUT_PACKAGE = '6767%03d%d%d6076';
    const WITH_PACKAGE =    '6767%03d%d%dP%dU%d6076';

    public static function parse(string $barcode): Barcode {
        $matches = [];
        if (!preg_match(
            '/6767(?<instance>[0-9]{3})(?<entity>[1-2])(?<id>[0-9]+)(P(?<package>[0-9]+))?(U(?<unit>[0-9]+))?8076/',
            $barcode,
            $matches,
            PREG_OFFSET_CAPTURE
        )) { return new Barcode($barcode); }

        return new Barcode(
            $barcode,
            $matches['entity'][0],
            $matches['id'][0],
            $matches['package'][0] ?? null,
            $matches['unit'][0] ?? null
        );
    }

    /**
     * @return Barcode[]|Barcode|null
     * @param object $entity
     */
    public static function getBarcodeFromEntity(object $entity): array|Barcode|null {
        switch (get_class($entity)) {
            case Task::class:
                return self::getBarcodeFromTask($entity);
            case Package::class:
                return self::getBarcodeFromPackage($entity);
            default:
                return null;
        }
    }

    public static function getBarcodeFromTask(Task $task): Barcode {
        $code = sprintf(
            self::WITHOUT_PACKAGE,
            1, //TODO: Dynamicly get instance
            Barcode::TYPE_TASK, $task->getId()
        );

        return self::parse($code);
    }

    /**
     * @return Barcode[]
     */
    public static function getBarcodeFromPackage(Package $package): array {
        $codebars = [];
        for ($q = 0; $q < $package->getQuantity(); $q++) {
            $codebars[] = sprintf(
                self::WITH_PACKAGE,
                1, //TODO: Dynamicly get instance
                Barcode::TYPE_TASK, $package->getTask()->getId(),
                $package->getId(), $q + 1
            );
        }
        return array_map(fn(string $code) => self::parse($code), $codebars);
    }

}
