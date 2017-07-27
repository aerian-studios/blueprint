<?php

namespace Aerian\Blueprint\CrudActionTrait;

use Illuminate\Support\Facades\App;
use Illuminate\Http\Response;
use Aerian\Blueprint\Adaptor\ServiceModel as ServiceModelAdaptor;
use Aerian\Blueprint\Adaptor\ServiceModelRecord as ServiceModelRecordAdaptor;
use Illuminate\Support\Facades\File;

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
                return $this->_getListData($params, 'list');
                break;
        }
    }

    /**
     * @param array $params filter params to be used when fetching the list data
     * @param array|null|'list' $columns the columns to include in the output. null = all columns; 'list' = columns configured on the model as the list columns
     * @return array
     */
    protected function _getListData(array $params = [], $columnIds = null)
    {
        //get a collection object containing the data
        $collection = $this->getModel()->getCollection($params);

        //use provided columns, or default the columns configured on the model for 'lists'
        if ($columnIds === 'list') {
            $columns = $this->getModel()->getListColumns();
            $columnIds = array_keys($columns);
        } elseif ($columnIds === null) {
            //if $columnIds is null, use the blueprint to get all columns - n.b. the API may not return all these, we don't know yet
            $columns = $this->getModel()->getAllColumns();
            $columnIds = array_keys($columns);
        }

        //populate items array
        $items = [];
        $i = 1;
        foreach ($collection as $item) {
            $itemAsArray = $item->toArray();

            //sync up the columns we intend to display with what's returned from the API
            //exclude any requested columns that the API has not returned
            //only do this on the first row of the data set
            if ($i === 1) {
                $columns = array_intersect_key($columns, array_flip(array_keys($itemAsArray)));
                $columnIds = array_keys($columns);
            }

            //get the properties from the item that we want as per the columnIds array
            $properties = array_intersect_key($itemAsArray, array_flip($columnIds));

            //prepare actions for this item
            //@todo should consider a default set for this model with overrides at item level only
            $actions = $item->getCrudListActions();
            $actionIds = array_keys($actions);

            //append the item
            $items[$item->id] = [
                'actionIds' => $actionIds,
                'actions' => $actions,
                'properties' => $properties //only include the columns specified in the columnIds array
            ];

            $i++;
        }

        //ensure columns and columnIds are arrays
        $columnIds = (!is_array($columnIds)) ? [] : $columnIds;
        $columns = (!is_array($columns)) ? [] : $columns;

        return [
            'totalCount' => $collection->getTotalCount(),
            'offset' => $collection->getOffset(),
            'count' => $collection->count(),
            'limit' => $params['limit'],
            'columnIds' => $columnIds,
            'columns' => $columns,
            'itemIds' => array_keys($items),
            'items' => $items
        ];

    }

    protected function _outputCSV(array $params = [])
    {
        $basePath = storage_path() . DIRECTORY_SEPARATOR . 'csv-downloads';
        if(!File::exists($basePath)) {
            File::makeDirectory($basePath);
        }

        $filename = date("Y-m-d-H-i-s-") . $this->getModel()->getEntityName() . '-' . rand() . ".csv";
        $filePath = $basePath . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($filePath, 'w+');

        $headingRow = true;
        $params['offset'] = 0; //start at the beginning regardless of the offset passed
        $params['limit'] = 100; //batch size

        while (true) {
            $data = $this->_getListData($params, null /*get all columns, not just those configured for the list view*/);

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
        return response()->download( $filePath, $filename, $headers)->deleteFileAfterSend(true);

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
