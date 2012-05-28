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
        $letters = '';
        //getting first letter from each of value type
        foreach($dataType as $word) {
            $letter = substr($word, 0, 1);
            $letters .= $letter;
        }

        $dataValues = (count($data) >= 1) ? ' VALUES ('.$values.')' : '';
        $sql= $table.$dataValues;

        //$stmt initialization
        $stmt = self::$_db->stmt_init();
        if($stmt->prepare($sql)) {
            $letters = '\''.$letters.'\'';
            $d = array_keys($data);

            $variables = '';
            foreach($d as $i) {
                $variables .= '$'.$i .', ';
            }
            $variables = substr($variables, 0, -2);

            $bindparams = $letters .", " .$variables;
            print $bindparams; //'ssii', $firstname, $lastname, $age, $createdDate
            $stmt->bind_param('ssii', $firstname, $lastname, $age, $createdDate); //added from print $bindparams

            //to do
            print(print_r($data));

            $firstname = 'TOM';
            $lastname = 'Tomson';
            $age = 22;
            $createdDate = 1338227241;

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
