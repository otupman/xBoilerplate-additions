<?php
/**
 * Initial blank version, ready for update.
 */
class CW_MySQL
{

    protected static $_object = null;
    protected static $_db = null;

    protected function __construct() {
        self::$_db = new mysqli($config['host'], $config['username'], $config['password'], $config['db']);
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
    public function select($table, $attrs, $where = null, $order = null) {

        $sth = $dbh->prepare('SELECT ? FROM ? WHERE ?');
        $sth->bindValue(1, $calories, PDO::PARAM_INT);
        $sth->bindValue(2, $colour, PDO::PARAM_STR);
        $sth->execute();

    }

    public function insert($table, array $data) {

    }

    public function delete($table, $where) {

    }

    public function update($table, array $data, $where) {

    }

    public function getLastInsertId() {
        return self::$_db->insert_id;
    }
}
