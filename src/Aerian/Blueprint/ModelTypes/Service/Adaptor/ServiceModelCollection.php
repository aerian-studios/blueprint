<?php

namespace Aerian\Blueprint\ModelTypes\Service\Adaptor;

use Aerian\Blueprint\CollectionAdaptorInterface;
use Aerian\ServiceModel\CollectionAbstract;

class ServiceModelCollection implements CollectionAdaptorInterface
{
    /**
     * @param $collection
     * @return array
     */
    public function toOptionsBlueprint($collection)
    {
        if (!$collection instanceof CollectionAbstract) {
            abort(500, '$collection must be instance of ' . CollectionAbstract::class . '.  ' . get_class($collection) . 'given');
        }

        $options = [];

        foreach ($collection as $record) {
            $options[] = [
                'value' => $record->getId(),
                'label' => $record->__toString()
            ];
        }

        return $options;
    }
}

