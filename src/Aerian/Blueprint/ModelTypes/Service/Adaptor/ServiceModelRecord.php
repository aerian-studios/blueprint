<?php

namespace Aerian\Blueprint\ModelTypes\Service\Adaptor;

use Aerian\Blueprint\RecordAdaptorInterface;
use Aerian\ServiceModel\RecordAbstract;
use Aerian\Blueprint\ModelTypes\Service\Adaptor\ServiceModel as BlueprintServiceModelAdaptor;

class ServiceModelRecord implements RecordAdaptorInterface
{

    public function blueprint($record)
    {
        if (!$record instanceof RecordAbstract) {
            abort(500, '$model must be instance of ' . RecordAbstract::class . '.  ' . get_class($record) . 'given');
        }

        return (new BlueprintServiceModelAdaptor())
            ->blueprint($record->getModel())
            ->setValues(array_merge($record->toArray(), $record->getManyToManyValues()));
    }
}

