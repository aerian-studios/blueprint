<?php

namespace Aerian\Blueprint\CrudActionTrait;

use Illuminate\Support\Facades\App;
use Aerian\Blueprint\Adaptor\ServiceModel as ServiceModelAdaptor;
use Aerian\Blueprint\Adaptor\ServiceModelRecord as ServiceModelRecordAdaptor;

trait ServiceModelCrudActionTrait
{
    protected $_model;

    /**
     * @param string $entityName
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function index($entityName, $limit = 50, $offset = 0)
    {
        $this->_setModelByEntityName($entityName);

        $collection = $this->_model->getCollection(['limit' => $limit, 'offset' => $offset]);

        $itemIds = $items = []; //declare itemIds and items as empty arrays
        $columns = $this->_model->getListColumns();

        //populate the itemIds and items arrays
        foreach ($collection as $item) {
            $itemIds[] = $item->id;
            $items[$item->id] = array_intersect_key($item->toArray(), array_flip($columns)); //only include the columns specified in the columns array
        }

        return [
            'columns' => $columns,
            'itemIds' => $itemIds,
            'items' => $items
        ];
    }

    public function blueprint($entityName, $id = null)
    {
        $this->_setModelByEntityName($entityName);

        if ($id) {
            $record = $this->_model->getRecord($id);
            if ($record) {
                $adaptor = new ServiceModelRecordAdaptor();
                $model = $record;
            } else {
                $adaptor = null;
                $model = null;
                abort(404);
            }
        } else {
            $adaptor = new ServiceModelAdaptor();
            $model = $this->_model;
        }

        return $adaptor->blueprint($model)->toNormalizedArray();

    }

    public function save($entityName, $id)
    {
        $this->_setModelByEntityName($entityName);

        $record = $this->_model->putRecord($id, request()->all());

        return (new ServiceModelRecordAdaptor())->blueprint($record)->toNormalizedArray();
    }

    /**
     * takes an entityName e.g. 'product-category' and sets a model based on a facade accessor e.g. ProductCategoryModel
     * @param $entityName
     * @return $this
     */
    protected function _setModelByEntityName($entityName)
    {
        $this->_setModelByFacadeAccessor($this->_getFacadeAccessorFromEntityName($entityName));
        return $this;
    }

    /**
     * Based on an entity name (e.g. 'product-category') sets an instance of an Aerian/ServiceModel\Model to the
     * ::_model property of this object
     * @param string $facadeAccessorString
     * @return $this
     */
    protected function _setModelByFacadeAccessor($facadeAccessorString)
    {
        $this->_model = App::make($facadeAccessorString);
        return $this;
    }

    /**
     * returns the facade accessor string (used to reference a model) from a entity name.
     * e.g. coverts 'product-category' to 'ProductCategoryModel'. In this example, there should be a facade
     * for retrieving an instance of ProductCategoryModel as per laravel
     * @see https://laravel.com/docs/5.4/facades
     * @param string $entityName
     * @return string
     */
    protected function _getFacadeAccessorFromEntityName($entityName)
    {
        return studly_case($entityName) . 'Model';
    }
}
