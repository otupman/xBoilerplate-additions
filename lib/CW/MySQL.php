<?php
/**
 * Initial blank version, ready for update.
 */
class CW_MySQL extends ArrayObject
{
    protected static $_object = null;
    protected static $_db = null;

    public function __construct() {
        //self::$_db = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);
        self::$_db = new mysqli('localhost', 'root', '', 'xBoilerplate_additions');
    }

    /**
     * @param string $query
     * @return mysqli_result
     */
    public function query($query) {
        return self::$_db->query($query);
    }

    /**
     * @return CW_MySQL
     */
    public static function getInstance() {
        if (!self::$_object) {
            self::$_object = new self();
        }
        return self::$_object;
    }

    /**
     * @param $table
     * @param $attrs
     * @param array $where
     * @return mixed
     */
    public function select(array $attrs, $table, array $where = null, $order = null) {

        if($where == null && $order == null) {
            $atrib = (count($attrs) >= 1) ? 'SELECT '.implode(", ", $attrs) : '';
            $table = ' FROM '.$table;
            $query = $atrib.$table;
            $stmt = self::$_db->prepare($query);

            if($stmt === false) {
                throw new Exception('Error preparing: ' . self::$_db->error);
            }
            if(!$stmt->execute()) {
                throw new Exception('Error executing: '. $stmt->error);
            }

            $result = $stmt->get_result();

            if($result === false) {
                throw new Exception('Error getting result: ' . $stmt->error);
            }

            $rows = array();

            while(($row = $result->fetch_object()) != null) {
                $rows[] = $row;

            }
            return $rows;
        }
        if($order == null){
            $atrib = (count($attrs) >= 1) ? 'SELECT '.implode(", ", $attrs) : '';
            $table = ' FROM '.$table;
            $wheres = ' WHERE ' .implode( array_keys($where));
            $wheres .= '=' .implode($where);

            $sql = $atrib.$table.$wheres;

            print $sql;

        }


    }

    private function _createValues($numberOfValues) {
        $values = '';

        for($i=1; $i <= $numberOfValues; $i++) {
            $values .= '?, ';
        }
        $values = substr($values, 0, -2);
        return $values;
    }

    private function _checkTypeOfValues(array $data) {
        foreach ($data as $item) {
            $result[] = $item;
        }
        foreach($result as $type) {
            $dataType[] = gettype($type);
        }

        return $dataType;
    }


    public function insert($table, array $data) {
        //create prepare statement, etc. INSERT INTO `people` (`firstname`, `lastname`, `age`, `createdDate`) VALUES (?, ?, ?, ?)
        $keys = array_keys($data);
        $dbColumnName = '(';
        foreach($keys as $key) {
            $dbColumnName .= '`'.$key.'`, ';
        }
        $dbColumnName = substr($dbColumnName, 0, -2);
        $dbColumnName .= ')';

        $table = 'INSERT INTO `'.$table.'` ' .$dbColumnName;

        $numberOfValues = count($data);
        $values = $this->_createValues($numberOfValues);

        $dataType = $this->_checkTypeOfValues($data);
        $type = '';
        //getting first letter from each of value type
        foreach($dataType as $word) {
            $letter = substr($word, 0, 1);
            $type .= $letter;
        }

        $dataValues = (count($data) >= 1) ? ' VALUES ('.$values.')' : '';
        $sql= $table.$dataValues;

        //$stmt initialization
        $stmt = self::$_db->stmt_init();

        //prepare statement
        if($sqlPrepare = $stmt->prepare($sql)) {
            //bind param
            $q = array();
            foreach($data as $key=>$value) {
                $q[] = &$value;
            }
            call_user_func_array(array($stmt, 'bind_param'), array_merge(array(&$type), $q));

            $result = $stmt->execute();
            if (true === $result) {
                print 'EXICUTE TRUE!!!!!!!!!!!!!!'; //just for test
            }
            else
                throw new Exception('Error: ' .$stmt->error);

            $stmt->close();
        }
        else
            throw new  Exception("Error: " .$stmt->error);
    }

    public function delete($table, $where) {

    }

    public function update($table, array $data, $where) {

    }

    public function getLastInsertId() {
        return self::$_db->insert_id;
    }
}
