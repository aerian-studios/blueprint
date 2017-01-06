<?php
namespace Aerian\Blueprint;

use Mockery\CountValidator\Exception;

class Blueprint
{
    /**
     * @var array
     */
    protected $_blueprintArray;

    /**
     * Blueprint constructor.
     * @param array $_blueprintArray
     */
    public function __construct(array $_blueprintArray)
    {
        $this->_blueprintArray = $_blueprintArray;
    }

    /**
     * magic call method allows setting of array properties e.g. setLabel('elementName', 'New label')
     * @param $name
     * @param $arguments
     * @return Blueprint
     */
    public function __call($name, $arguments)
    {
        if (strpos($name, 'set') === 0) {
            $property = lcfirst(substr($name, 3));
            return $this->_setProperty($property, $arguments[0], $arguments[1]);
        } else {
            throw new Exception('Unrecognised method ' . $name . ' called on ' . self::class);
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->_blueprintArray;
    }

    public function toNormalizedArray()
    {
        return [
            'elementIds' => array_keys($this->_blueprintArray),
            'elements' => $this->_blueprintArray
        ];
    }

    protected function _setProperty($property, $element, $value)
    {
        $this->_blueprintArray[$element][$property] = $value;
        return $this;
    }
}