<?php
namespace Aerian\Blueprint;

interface RecordAdaptorInterface
{
    /**
     * takes a model, and converts it into a blueprint array object
     * @param $model
     * @return Blueprint
     */
    public function blueprint($model);

    /**
     * Takes a column key string (e.g. 'name'), and returns it as a label (e.g. 'Product name')
     * @param $columnKey
     * @return mixed
     */
    //public function getLabelForColumnKey($columnKey);
}