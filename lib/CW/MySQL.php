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

//        $b = new ArrayObject();
//        $results = array();
//        if($where == null && $order == null) {
//            $atrib = (count($attrs) >= 1) ? 'SELECT '.implode(", ", $attrs) : '';
//            $table = ' FROM '.$table;
//            $query = $atrib.$table;
//
//
//            if($results = self::$_db->prepare($query)) {
//                $results->execute();
//                while($obj = $results->fetch_object()) {
//                    $results[] = $obj;
//                }
//            }
//            return $results;

//            $stmt = self::$_db->prepare($sql);
//            if ($stmt === false) {
//                throw new Exception('Errror! ' .self::$_db->error);
//                error_log('asdasdasdasdasdasdasdasdasd', 0, '/vagrant/error_log');
//            }
//            $data = $stmt->execute();
//            $bindResultParameters = (count($attrs) >= 1) ? '$'.implode(", $", $attrs) : '';
//            $stmt->bind_result($bindResultParameters);
//            error_log($data, 0, '/vagrant/error_log');
//
//        }
    }

    private function _createValues($numberOfValues) {
        $values = '';

        for($i=1; $i <= $numberOfValues; $i++) {
            $values .= '? ';
        }
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
        $table = 'INSERT INTO `'.$table.'` (' .implode("` ", array_keys($data)). ')';


        $numberOfValues = count($data);
        $values = $this->_createValues($numberOfValues);

        $dataType = $this->_checkTypeOfValues($data);
        $letters = '';
        //getting first letter from each of value type
        foreach($dataType as $word) {
            $letter = substr($word, 0, 1);
            $letters .= $letter;
        }

        $dataValues = (count($data) >= 1) ? ' VALUES ('.$values.')' : '';
        $sql= $table.$dataValues;
        error_log($table, 0, '/vagrant/error_log');

        $stmt = self::$_db->stmt_init();

        if($stmt->prepare($sql)) {
            $letters = '\''.$letters.'\'';
            $stmt->bind_param($letters, implode(array_keys(" $", $data)));
            $stmt->execute();
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
