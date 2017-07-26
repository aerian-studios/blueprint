<?php

namespace Aerian\Blueprint\CrudActionTrait;

use Illuminate\Support\Facades\App;
use Illuminate\Http\Response;
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

        $defaultParams = [
            'format' => 'json',
            'limit' => 50,
            'offset' => 0
        ];

        //prepare filters using supplied input merged with defaults
        $params = array_merge($defaultParams, request()->all());

        switch ($params['format']) {
            case 'csv':
                return $this->_outputCSV($params);
                break;
            default:
                return $this->_getListData($params);
                break;
        }
    }

    protected function _getListData(array $params = [])
    {
        //get a collection object containing the data
        $collection = $this->getModel()->getCollection($params);

        //find what columns have been configured for this model to be included in the output
        $columns = $this->getModel()->getListColumns();

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
            'count' => $collection->count(),
            'limit' => $params['limit'],
            'columnIds' => array_keys($columns),
            'columns' => $columns,
            'itemIds' => array_keys($items),
            'items' => $items
        ];

    }

    protected function _outputCSV(array $params = [])
    {
        $filename = date("Y-m-d-H-i-s-") . $this->getModel()->getEntityName() . ".csv";
        $handle = fopen($filename, 'w+');

        $headingRow = true;
        $params['offset'] = 0; //start at the beginning
        $params['limit'] = 100; //batch size

        while (true) {
            $data = $this->_getListData($params);

            if ($data['count'] == 0) {
                break;
            }

            //write rows
            foreach ($data['items'] as $item) {
                //write headers
                if ($headingRow) {
                    $headings = array();
                    foreach ($data['columnIds'] as $columnId) {
                        $label = (isset($data['columns'][$columnId]['label'])) ? $data['columns'][$columnId]['label'] : $columnId;
                        //excel bug fix
                        if ($label === "id") {
                            $label = 'Id';
                        }
                        $headings[] = $label;
                    }
                    fputcsv($handle, $headings, ',', '"');
                    $headingRow = false;
                }
                // add a tab to the end of each value
                // this forces excel to treat each value
                // as text and not try to parse dates etc
                foreach($item['properties'] as $key => $value) {
                    //  replace – with regular - and strip tags
                    $item['properties'][$key] = str_replace('–','-', strip_tags($value)) . "\t";
                }

                fputcsv($handle, $item['properties'], ',', '"');
            }

            $params['offset'] += $params['limit'];
        }

        fclose($handle);
        $headers = ['Content-Type' => 'text/csv'];
        return response()->download($filename, $filename, $headers);

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
     * @todo the model set should probably implement an interface
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

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->_model;
    }
}
