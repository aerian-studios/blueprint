<?php
namespace Aerian\Blueprint\ModelTypes\Eloquent;

/**
 * Trait CrudActionTrait
 * This trait provides functionality for CRUD controllers including implementing default actions
 */
trait CrudActionTrait
{
    /**
     * @var
     * @todo currently assumes an Aerian\Database\Eloquent\Model, but should be decoupled?
     */
    protected $_model;

    /**
     *
     * @var string
     */
    protected $_modelBasePath = 'App\\';

    /**
     * @param $entityType string e.g. 'product' or 'productCategory'
     * @return $this
     */
    protected function setModel($entityType)
    {
        $entityClass = $this->getEntityClass($entityType);
        $this->_model = new $entityClass;
        return $this;
    }

    protected function getEntityClass($entityType)
    {
        return $this->_modelBasePath . ucfirst($entityType);
    }

    public function index($entity, $limit = 5, $offset =0)
    {
        $this->setModel($entity);
        return $this->_getListArray($limit, $offset);
    }

    public function blueprint($entity, $id = null)
    {
        $this->setModel($entity);

        return $this->_getValidRowOr404($id)
            ->blueprint()
            ->toNormalizedArray();
    }

    protected function _getListArray($limit, $offset)
    {
        $selectColumns = $this->_model->getListColumns();
        $key = $this->_model->getKeyName();
        array_unshift($selectColumns, $key);

        $rows = $this->_model->limit($limit)->offset($offset)->get($selectColumns)->keyBy($key);
        $listColumns = $this->_model->getListColumns();

        return [
            'columnsIds' => array_keys($listColumns),
            'columns' => $listColumns,
            'itemIds' => $rows->pluck($key),
            'items' => $rows
        ];
    }

    /**
     * @param $id
     * @return \Aerian\Database\Eloquent\Model
     * @todo this is currenctly coupled to Aerian\Database\Eloquent\Model, but should support other types of models
     */
    protected function _getValidRowOr404($id)
    {
        if ($id) {
            $row = $this->_model->find($id);
        } else {
            $row = $this->_model;
        }

        if (!$row instanceof \Aerian\Database\Eloquent\Model) {
            abort(404);
        }

        return $row;
    }

}
