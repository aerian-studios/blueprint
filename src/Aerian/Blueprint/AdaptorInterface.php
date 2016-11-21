<?php
namespace Aerian\Blueprint;

interface AdaptorInterface
{
    /**
     * @param $model
     * @return Blueprint
     */
    public function blueprint($model);
}