<?php

namespace Aerian\Blueprint\Adaptor;

use Aerian\Blueprint\ModelAdaptorInterface;
use Aerian\Blueprint\Blueprint;
use Aerian\ServiceModel\ModelAbstract;

class ServiceModel implements ModelAdaptorInterface
{

    public function blueprint($model)
    {
        if (!$model instanceof ModelAbstract) {
            abort(500, '$model must be instance of ' . ModelAbstract::class . '.  ' . get_class($model) . 'given');
        }
        return new Blueprint($model->getBlueprintConfig());
    }
}

