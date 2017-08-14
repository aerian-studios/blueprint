<?php
namespace Aerian\Blueprint;

interface CollectionAdaptorInterface
{
    /**
     * take a collection, and return an array of 'options' which can be used in a blueprint config
     * @param $collection
     * @return array
     */
    public function toOptionsBlueprint($collection);
}