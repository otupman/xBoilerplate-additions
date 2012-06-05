<?php
/**
 * @author Oliver Tupman <oliver.tupman@centralway.com>
 * Date: 01/06/2012
 * Time: 12:55
 */
class CW_SimpleDao
{
    private $_objectType;
    private $_db;
    private $_tableName;
    private $_fields;

    /**
     * @param string $objectType class name of the object
     * @param array $fields the list of fields that belong to the object
     * @param string $tableName the name of the table that holds the object
     */
    public function __construct($objectType, array $fields, $tableName) {
        $this->_objectType = $objectType;
        $this->_db = CW_SQL::getInstance();
        $this->_tableName = $tableName;
        $this->_fields = $fields;
    }

    /**
     * @param mixed $ids
     * @return mixed an instance of the object for the DAO
     * @throws Exception in the event that no such object with that ID exists
     */
    public function loadById($ids) {
        $results = $this->_db->select($this->_fields, $this->_tableName, $ids, CW_SQL::NO_ORDER, 1, $this->_objectType);
        if(sizeof($results) != 1) {
            throw new Exception('Could not find an object');
        }
        return $results[0];
    }

    public function exists($ids) {
        $results = $this->_db->select($this->_fields, $this->_tableName, $ids, CW_SQL::NO_ORDER, 1, $this->_objectType);
        return sizeof($results) > 0;
    }

    public function find(array $where = null, array $order = null, $limit = null) {
        return $this->_db->select($this->_fields, $this->_tableName, $where, $order, $this->_objectType);
    }

    public function insert($item) {
        $convertedItem = $this->convertObjectToArray($item);
        $newId = $this->_db->insert($this->_tableName, $convertedItem);
        return $newId;
    }

    public function convertObjectToArray($item)
    {
        $arrayifiedItem = (array)$item;
        $convertedItem = array();
        foreach ($arrayifiedItem as $key => $value) {
            $propertyIndex = stripos($key, "\0", 2) + 1;
            if($propertyIndex === false) {
                $propertyIndex = 0;
            }
            else {
                // We have the index already
            }
            $simpleKey = substr($key, $propertyIndex);
            $convertedItem[$simpleKey] = $value;
        }
        return $convertedItem;
    }

    public function update($item, array $ids) {
        $convertedItems = $this->convertObjectToArray($item);
        $this->_db->update($this->_tableName, $convertedItems, $ids);
    }

}