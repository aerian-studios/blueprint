<?php

namespace Aerian\Blueprint\CrudActionTrait;

use Illuminate\Support\Facades\App;
use Aerian\Blueprint\Adaptor\ServiceModel as ServiceModelAdaptor;
use Aerian\Blueprint\Adaptor\ServiceModelRecord as ServiceModelRecordAdaptor;

trait ServiceModelCrudActionTrait
{
    protected $_model;

    /**
     * returns a normalised array of entities based on the entity name (classic GET list API)
     * @param string $entityName
     * @return array
     */
    public function index($entityName)
    {
        $this->_setModelByEntityName($entityName);

        $defaultInputs = [
            'limit' => 50,
            'offset' => 0
        ];

        $filters = array_merge($defaultInputs, request()->all());

        //get a collection using supplied input merged with default as filters
        $collection = $this->_model->getCollection($filters);

        $columns = $this->_model->getListColumns();

        //populate items array
        $items = [];
        foreach ($collection as $item) {

            //prepare actions for this item
            //@todo should consider a default set for this model with overrides at item level only
            $actions = $item->getCrudListActions();
            $actionIds = array_keys($actions);

            //append the item
            $items[$item->id] = [
                'actionIds' => $actionIds,
                'actions' => $actions,
                'properties' => array_intersect_key($item->toArray(), array_flip(array_keys($columns))) //only include the columns specified in the columns array
            ];
        }

        return [
            'totalCount' => $collection->getTotalCount(),
            'offset' => $collection->getOffset(),
            'columnIds' => array_keys($columns),
            'columns' => $columns,
            'itemIds' => array_keys($items),
            'items' => $items
        ];
    }

    /**
     * returns a blueprint normalised array for a given entityName
     * @param string $entityName
     * @return array
     */
    public function blueprintForModel($entityName)
    {
        $this->_setModelByEntityName($entityName);

        return (new ServiceModelAdaptor())->blueprint($this->_model)->toNormalizedArray();

    }

    /**
     * returns a blueprint normalised array for a given entity record using entityName and id
     * @param string $entityName
     * @param int $id
     * @return array
     */
    public function blueprintForRecord($entityName, $id)
    {
        $this->_setModelByEntityName($entityName);

        $record = $this->_model->getRecord($id);
        if ($record) {
            return (new ServiceModelRecordAdaptor())->blueprint($record)->toNormalizedArray();
        } else {
            abort(404);
        }
    }

    /**
     * saves an entity by PUTing data to API
     * @param $entityName
     * @param $id
     * @return mixed
     */
    public function save($entityName, $id)
    {
        $this->_setModelByEntityName($entityName);

        $record = $this->_model->putRecord($id, request()->all());

        return (new ServiceModelRecordAdaptor())->blueprint($record)->toNormalizedArray();
    }

    /**
     * Make a delete request to an API to delete the entity with the supplied name and id
     * @param $entityName
     * @param $id
     * @return mixed
     */
    public function delete($entityName, $id)
    {
        $this->_setModelByEntityName($entityName);

        $this->_model->deleteRecord($id);

        //@todo not sure what to return on a delete request
        return;
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
