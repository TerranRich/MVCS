<?php

namespace Core;

/**
 * Database wrapper for PDO connection
 * 
 * Usage:
 *      $db = DB::connect();
 *      $results = $db->getAll("SELECT ...");
 * -or-
 *      $result = DB::connect()->getAll("SELECT ...");
 * 
 * @package  DB
 * @version  1.0
 */
class DB
{
    private static $instance;
    protected static $conn = null;
    
    const WAIT_TIMEOUT = 300;
    const ACTION_SELECT = 'select';
    const ACTION_UPDATE = 'update';
    const ACTION_INSERT = 'insert';
    const ACTION_DELETE = 'delete';
    
    /**
     * Class constructor
     * 
     * @access private
     * @return void
     */
    private function __construct()
    {
        
    }
    
    /**
     * Class destructor
     * 
     * @access private
     * @return void
     */
    function __destruct()
    {
        
    }
    
    /**
     * Close database connection
     * 
     * @access public
     * @return void
     */
    public function close()
    {
        self::$conn = null;
    }
    
    /**
     * Disables cloning
     * 
     * @access public
     * @return void
     */
    public function __clone()
    {
        throw new Exception('Cloning of class DB is not allowed.');
    }
    
    /**
     * Initialize the singleton
     * 
     * @return DB Instance of this class
     */
    public static function init()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }
        
        return self::$instance;
    }
    
    /**
     * Sanitize input using PDO's own quote() method
     * 
     * @param  String  $val  Value to sanitize
     * @return String        Sanitized result
     */
    public static function sanitize($val)
    {
        if (self::$conn instanceof PDO) {
            return substr(self::$conn->quote($val), 1, -1);
        } else {
            $conn = DB::init()->getConnection();
            if ($conn instanceof PDO) {
                return substr($conn->quote($val), 1, -1);
            } else {
                // Last resort
                return addslashes($val);
            }
        }
    }
    
    /**
     * Returns a hex literal
     * 
     * @param  int    $val Value
     * @return string      Hex literal
     */
    public static function hexLiteral($val)
    {
        return "X'" . self::sanitize($val) . "'";
    }
    
    /**
     * Dump debug error message
     * 
     * @param  Exception $e Exception thrown
     * @return void
     */
    public function debug($e)
    {
        dump($e->getMessage());
    }
    
    /**
     * Get the PDO connection object
     * 
     * @return PDO  Connection object
     */
    public function getConnection()
    {
        return self::$conn;
    }
    
    /**
     * Simulate a database ping
     * 
     * @access protected
     * @param  PDO  $dbh  PDO database handle
     * @return bool       Success
     */
    protected static function ping(PDO $dbh)
    {
        $bad_status = 'MySQL server go bye-bye';
        
        try {
            $status = $dbh->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (PDOException $e) {
            $status = $bad_status;
        }
        
        return ($status !== $bad_status && $status !== '');
    }
    
    /**
     * Display last error
     * 
     * @access public
     * @return Array  PDO error information
     */
    public static function getError()
    {
        return '';
        // return self::$conn->errorInfo();
    }
    
    /**
     * Create database connection resource. Reuses existing resource if one
     * already exists (Singleton).
     * 
     * @access public
     * @throws Exception if database connection fails
     * @return DB
     */
    public static function connect()
    {
        global $config;
        
        if (
            !isset(self::$conn) ||
            self::$conn === null ||
            self::$conn instanceof PDO === false ||
            self::ping(self::$conn) === false
        ) {
            $conn_str = sprintf(
                "mysql:host=%s;dbname=%s",
                $config['db_host'],
                $config['db_name']
            );
            self::$conn = new PDO($conn_str, $config['db_username'], $config['db_password']);
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            
            if (self::$conn instanceof PDO === false) {
                throw new Exception('Unable to connect to database server.');
            }
            
        }
        
        return self::init();
    }
    
    /**
     * Perform SQL query. Returns resource for SELECTs, LAST_INSERT_ID() for
     * INSERTs, or # affected rows for UPDATEs and DELETEs. If logged enabled,
     * execution time and # affected rows are logged.
     * 
     * @access private
     * @throws Exception if unable to execute prepared statement
     * @param  String  $sql     SQL query to run
     * @param  Array   $params  Parameters to bind to query (if any)
     * @param  String  $action  Type of action (select, insert, update, delete)
     * @return Mixed
     */
    private static function runQuery($sql, $params = [], $action = null)
    {
        $result = self::getPreparedStatement($sql, $params);
        
        if (!$result->execute($params)) {
            throw new Exception("Unable to perform query:<br>$sql<br><br>" . $result->errorInfo());
        }
        
        switch ($action)
        {
            case self::ACTION_INSERT:
                $result = self::$conn->lastInsertId();
                break;
            
            case self::ACTION_UPDATE:
            case self::ACTION_DELETE:
                $result = $result->rowCount();
                break;
            
            default:
                // Do nothing; return resource
        }
        
        return $result;
    }
    
    /**
     * Add support for WHERE IN (?) and WHERE NOT IN (?) clauses to PDO
     * 
     * @access public
     * @throws Exception if missing array of values when using [NOT] IN (?)
     * @param  String  $sql     SQL query
     * @param  Array   $params  Parameters to bind
     * @return PDOStatement     PDO statement object
     */
    public static function getPreparedStatement($sql, Array &$params)
    {
        // Check for WHERE IN (?) / NOT IN (?) clause
        if (preg_match_all('/(NOT\s+)?IN\s+\(\?\)/i', $sql, $matches)) {
            $array_params = array_values(array_filter($params, function($param) {
                return is_array($param);
            }));
            
            // Expand ? to match length of param
            foreach ($matches[0] as $i => $match) {
                if (!isset($array_params[$i])) {
                    throw new Exception('Must pass array of values when using "[NOT] IN (?)" in WHERE clause.');
                }
            }
            
            $replace = implode(',', array_fill(0, count($array_params[$i]), '?'));
            
            $pos = strpos($sql, $match);
            if ($pos !== false) {
                $replace = str_replace('?', $replace, $match);
                $sql = substr_replace($sql, $replace, $pos, strlen($match));
            }
        }
        
        // Flatten params
        foreach ($params as $offset => $param) {
            if (is_array($param)) {
                array_splice($params, $offset, 1, $param);
            }
        }
        
        return self::$conn->prepare($sql);
    }
    
    /**
     * Public method for executing SQL queries
     * 
     * @access public
     * @param  String $sql    SQL query
     * @param  Array  $params Paramneters to bind
     * @return Mixed
     * @see    runQuery()
     */
    public function query($sql, array $params = array())
    {
        return self::runQuery($sql, $params);
    }
    
    /**
     * Method for performing INSERT queries
     * 
     * @access public
     * @param  String $sql    SQL query
     * @param  Array  $params Paramneters to bind
     * @return int            ID of last inserted row
     */
    public function insert($sql, array $params = array())
    {
        return self::runQuery($sql, $params, self::ACTION_INSERT);
    }
    
    /**
     * Method for performing UPDATE queries
     * 
     * @access public
     * @param  String $sql    SQL query
     * @param  Array  $params Paramneters to bind
     * @return int            Number of rows affected
     */
    public function update($sql, array $params = array())
    {
        return self::runQuery($sql, $params, self::ACTION_UPDATE);
    }
    
    /**
     * Method for performing DELETE queries
     * 
     * @access public
     * @param  String $sql    SQL query
     * @param  Array  $params Paramneters to bind
     * @return int            Number of rows deleted
     */
    public function delete($sql, array $params = array())
    {
        return self::runQuery($sql, $params, self::ACTION_DELETE);
    }
    
    /**
     * Method for performing DESCRIBE queries
     * 
     * @access public
     * @param  String $table Name of table
     * @return Array         Result of DESCRIBE query
     */
    public function describe($table)
    {
        $sth = self::$conn->prepare('DESCRIBE ' . $table);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_OBJ);
        unset($sth);
        
        return $result;
    }
    
    /**
     * Method for performing EXPLAIN queries
     * 
     * @access public
     * @param  String $sql SQL query to EXPLAIN
     * @return Array       Result of EXPLAIN query
     */
    public function explain($sql)
    {
        $sth = self::$conn->prepare('EXPLAIN ' . $sql);
        $sth->execute();
        $result = $sth->fetchAll(PDO::FETCH_OBJ);
        unset($sth);
        
        return $result;
    }
    
    /**
     * Start transaction for InnoDB tables
     * 
     * @access public
     * @return bool Whether transaction initiation was successful
     */
    public function startTransaction()
    {
        if (!self::getTransaction()) {
            return self::$conn->beginTransaction();
        }
        
        return true;
    }
    
    /**
     * Commit transaction for InnoDB tables
     * 
     * @access public
     * @return bool Whether commit was successful
     */
    public function commit()
    {
        if (self::getTransaction()) {
            self::$conn->commit();
        }
        
        return false;
    }
    
    /**
     * Roll back transaction for InnoDB tables
     * 
     * @access public
     * @return bool Whether rollback was successful
     */
    public function rollback()
    {
        if (self::getTransaction()) {
            self::$conn->rollBack();
        }
        
        return false;
    }
    
    /**
     * Check to see if transaction has already been started
     * 
     * @access public
     * @return bool Whether we're in a transaction already
     */
    public function getTransaction()
    {
        return self::$conn->inTransaction();
    }
    
    /**
     * Perform SELECT query and return single value
     * 
     * @access public
     * @param  String  $sql  SQl query
     * @return Mixed
     */
    public function getOne($sql, array $params = array(), $col_nbr = 0)
    {
        $sth = self::runQuery($sql, $params);
        $result = $sth->fetchColumn($col_nbr);
        unset($sth);
        
        return $result;
    }
    
    /**
     * Perform SELECT query and return numerical array of strings that
     * correspond to the fetched row
     * 
     * @access public
     * @param  String  $sql  SQL query
     * @return Array
     */
    public function getRow($sql, array $params = array(), $ret_type = PDO::FETCH_ASSOC)
    {
        $sth = self::runQuery($sql, $params);
        $result = $sth->fetch($ret_type);
        unset($sth);
        
        return $result;
    }
    
    /**
     * Perform SELECT query, returns associative array of strings that
     * correspondings to first result row
     * 
     * @access public
     * @param  String  $sql     SQL query
     * @param  Array   $params  Parameters to bind to query
     * @return Array            First row of results
     */
    public function getFirst($sql, array $params = array())
    {
        return $this->getRow($sql, $params, PDO::FETCH_ASSOC, 1);
    }
    
    /**
     * Performs SELECT query, returns array of data, formatted as specified by
     * $mode
     * 
     * @access public
     * @param  String  $sql   SQL query
     * @param  String  $mode  Return type (PDO::FETCH_OBJ/FETCH_ASSOC/FETCH_NUM)
     * @return Mixed
     */
    public function getAll($sql, array $params = array(), $mode = PDO::FETCH_ASSOC)
    {
        // If 2nd param is not array, assume it is the mode
        if (!is_array($params)) {
            list($mode, $params) = [$params, $mode];
        }
        
        $sth = self::runQuery($sql, $params);
        $results = $sth->fetchAll($mode);
        
        unset($sth);
        return $results;
    }
    
    /**
     * Check whether table exists
     * 
     * @access public
     * @param  String $table Name of table
     * @return bool          Whether table exists
     */
    public function tableExists($table)
    {
        $sth = self::$conn->prepare("SHOW TABLES LIKE ?");
        $sth->execute([$table]);
        $result = $sth->fetchColumn(0);
        unset($sth);
        
        return !empty($data);
    }
    
    /**
     * Perform DESCRIBE query for a table, formatted as specified by given mode
     * 
     * @access public
     * @param  String $table Name of table
     * @param  String $mode  PDO fetch mode
     * @return Array         List of fields in table
     */
    public function getColumns($table, $mode = PDO::FETCH_OBJ)
    {
        $result = self::describe($table);
        
        if ($mode === PDO::FETCH_NUM) {
            $cols = [];
            foreach ($result as $col) {
                $cols[] = $col->Field;
            }
            $result = $cols;
        }
        
        return $result;
    }
    
    /**
     * Perform SHOW INDXES on table to get index[es]
     * 
     * @access public
     * @param  String $table     Name of table
     * @param  String $extra_sql Any extra SQL to include in SHOW INDEXES query
     * @param  Array  $params    Parameters for $extra_sql
     * @return Array             List of indexes in table
     */
    public function getIndexes($table, $extra_sql = '', array $params = array())
    {
        if (!empty($extra_sql)) {
            $extra_sql = ' ' . $extra_sql;
        }
        
        $sth = self::$conn->prepare("SHOW INDEXES FROM `$table`$extra_sql");
        $sth->execute($params);
        $result = $sth->fetchAll(PDO::FETCH_OBJ);
        unset($sth);
        
        return $result;
    }
    
    /**
     * Return primary key[s] for specified table as defined by DESCRIBE query
     * 
     * @access public
     * @param  String $table Name of table
     * @return Array|String  Primary key[s] (String if 1, Array if > 1)
     */
    public function getPrimaryKey($table)
    {
        $pri_keys = [];
        $columns  = self::getColumns($table);
        
        foreach ($columns as $col) {
            if (strtoupper($col->Key) == 'PRI') {
                $pri_keys[] = $col->Field;
            }
        }
        
        return $pri_keys[1] ? $pri_keys : ($pri_keys[0] ?: '');
    }
    
    /**
     * Return array of possible ENUM values for column in given table
     * 
     * @access public
     * @param  String $table  Name of table
     * @param  String $column Name of column
     * @return Array          Possible ENUM values (quotes removed)
     */
    public function getEnumValues($table, $column)
    {
        $result = self::getAll("SHOW COLUMNS FOR $table LIKE '$column'", PDO::FETCH_OBJ);
        
        $type = self::getAll("SHOW COLUMNS FROM {$table} WHERE Field = '{$field}'", PDO::FETCH_OBJ);
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        
        return explode("','", $matches[1]);
    }
    
    /**
     * Return array of default values for the specified table
     * 
     * @param  String $table Name of table
     * @return Array         List of default values
     */
    public function getDefaultValues($table)
    {
        $values = [];
        $result = $this->getAll("DESCRIBE $table");
        foreach ($result as $row) {
            $values[$row->Field] = $row->Default;
        }
        
        return $values;
    }
    
    /**
     * Validate data given for table
     * 
     * @param  string $table    Table name
     * @param  string $keys     Key(s)
     * @param  string $vals     Value(s) for key(s)
     * @param  array  $pri_keys Primary key(s)
     * @param  array  $pri_vals Primary key value(e)
     * @return true             If successful
     * 
     * @throws Exception if anything is invalid
     */
    public static function validate($table = '', $keys = '', $vals = '', $pri_keys = [], $pri_vals = [])
    {
        if (empty($table)) {
            throw new Exception('DB::ERROR - Table name is undefined.');
        }
        if (empty($keys)) {
            throw new Exception("DB::ERROR - Column names for table $table are undefined.");
        }
        if (empty($vals)) {
            throw new Exception("DB::ERROR - Column values for table $table are undefined.");
        }
        if (count($keys) != count($vals)) {
            throw new Exception("DB::ERROR - Number of values does not match number of columns for table $table.");
        }
        
        for ($i = 0; $i < count($pri_keys); $i++) {
            if (!empty($pri_vals[$i]) && empty($pri_keys[$i])) {
                throw new Exception("DB::ERROR - Primary key not defined for table $table.");
            }
            if (!empty($pri_keys[$i]) && empty($pri_vals[$i])) {
                throw new Exception('DB::ERROR - Value not set for primary key: ' . $pri_keys[$i] . '.');
            }
        }
        
        return true;
    }
    
    /**
     * Generate SQL to count rows in a table
     * 
     * @param  string $sql Given SQL query
     * @return string      SQL count query
     */
    public static function buildCountQuery($sql = '')
    {
        if (empty($sql)) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        $arr = explode('FROM', $sql);
        if (count($arr) < 1) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        
        $arr[0] = "SELECT COUNT(*) AS ct ";
        $sql = implode('FROM', $arr);
        // Toss out any ORDER/GROUP BY clauses
        $sql = preg_replace('/\sORDER BY(.)*[GROUP]?/', '', $sql);
        
        return $sql;
    }
    
    /**
     * Generate SQL to insert a row into the table
     * 
     * @param  string $table Table name
     * @param  string $keys  Key(s)
     * @param  string $vals  Value(s)
     * @param  string $conn  Database connection
     * @return string        SQL insert query
     */
    public static function buildInsertQuery($table, $keys, $vals, $conn = null)
    {
        self::$conn = $conn;
        
        try {
            $valid = self::validate($table, $keys, $vals);
        } catch (Exception $e) {
            self::debug($e);
        }
        
        $vals = self::quoteVals($vals);
        
        $sql = "INSERT INTO `%s` (`%s`)
                VALUES (%s)";
        $sql = sprintf(
            $sql,
            $table,
            implode('`, `', $keys),
            implode(', ',   $vals)
        );
        
        return $sql;
    }
    
    /**
     * Generate SQL to insert a row into the table, with ON DUPLICATE KEY UPDATE
     * check
     * 
     * @param  string $table Table name
     * @param  string $keys  Key(s)
     * @param  string $vals  Value(s)
     * @param  string $conn  Database connection
     * @return string        SQL insert query w/ update
     */
    public static function buildInsertUpdateQuery($table, $keys, $vals, $conn = null)
    {
        self::$conn = $conn;
        
        try {
            $valid = self::validate($table, $keys, $vals);
        } catch (Exception $e) {
            self::debug($e);
        }
        
        $vals = self::quoteVals($vals);
        
        $sql = "INSERT INTO `%s` (`%s`)
                VALUES (%s)";
        $sql = sprintf(
            $sql,
            $table,
            implode('`, `', $keys),
            implode(', ',   $vals)
        );
        
        $sql_parts = [];
        foreach ($keys as $i => $key) {
            $sql_parts[] = '`' . $key . '` = ' . $vals[$i];
        }
        
        $sql .= 'ON DUPLICATE KEY UPDATE ' . implode(', ', $sql_parts);
        
        return $sql;
    }
    
    /**
     * Generate SQL to replace a row in the table
     * 
     * @param  string $table Table name
     * @param  string $keys  Key(s)
     * @param  string $vals  Value(s)
     * @param  string $conn  Database connection
     * @return string        SQL replace query
     */
    public static function buildReplaceQuery($table, $keys, $vals, $conn = null)
    {
        return preg_replace('/^INSERT/', 'REPLACE', self::buildInsertQuery($table, $keys, $vals, $conn));
    }
    
    public static function buildUpdateQuery($table, $keys, $vals, $pri_keys, $pri_vals, $cond = '', $conn = null)
    {
        self::$conn = $conn;
        
        if (!is_array($pri_keys)) {
            $pri_keys = [$pri_keys];
        }
        if (!is_array($pri_vals)) {
            $pri_vals = [$pri_vals];
        }
        
        try {
            $valid = self::validate($table, $keys, $vals, $pri_keys, $pri_vals);
        } catch (Exception $e) {
            self::debug($e);
        }
        
        $vals = self::quoteVals($vals);
        
        $sql = "UPDATE `$table` SET ";
        
        $sql_parts = [];
        for ($i = 0; $i < count($keys); $i++) {
            $sql_parts[] = '`' . $key[$i] . '` = ' . $vals[$i];
        }
        
        $sql .= implode(', ', $sql_parts) . ' WHERE ';
        
        $sql_parts = [];
        for ($i = 0; $i < count($pri_keys); $i++) {
            $sql_parts[] = '`' . $pri_keys[$i] . '` = \'' . $pri_vals[$i] . "'";
        }
        
        $sql .= implode(' AND ', $sql_parts);
        $sql .= " $cond LIMIT 1";
        
        return $sql;
    }
    
    public static function buildVerifyUpdateQuery($sql)
    {
        $sql = str_replace('UPDATE', 'SELECT COUNT(*) FROM', $sql);
        $sql = preg_replace('/SET[\w\W]*WHERE/', ' WHERE', $sql);
        return $sql;
    }
    
    public static function quoteVals($vals = [], $implode = false, $glue = ',')
    {
        $allowed_funcs = [
            'NOW', 'DATE_SUB', 'DATE_ADD',
            'CEILING', 'CEIL', 'FLOOR',
            'ROUND', 'AES_ENCRYPT', 'AES_DECRYPT',
        ];
        $arr = [];
        
        foreach ($vals as $val) {
            // Hex literal: X'val', x'val'
            if (
                (
                    strpos($val, "X'") === 0 ||
                    strpos($val, "x'") === 0
                ) &&
                substr($val, -1) === "'"
            ) {
                $arr[] = self::hexLiteral(substr($val, 2, -1));
            } elseif (strpos($val, '(') === false && $val !== 'NULL') {
                $arr[] = "'" . self::sanitize($val) . "'";
            } else {
                $func_name = (strpos($val, '(') !== false)
                    ? substr($val, 0, strpos($val, '('))
                    : $val;
                $arr[] = (in_array($func_name, $allowed_funcs) || $val === 'NULL')
                    ? $val
                    : "'" . self::sanitize(val) . "'";
            }
        }
        
        return $implode ? implode($glue, $arr) : $arr;
    }
    
    public static function getSelectColumns($sql = '', $omit = [])
    {
        if (empty($sql)) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        
        $arr = explode('FROM', $sql);
        if (count($arr) < 1 || !isset($arr[0])) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        $sql = preg_replace('/SELECT\W?/',       '', trim($arr[0]));
        $sql = preg_replace('/[A-Z]*\([^)]*\)/', '', $sql);
        $sql = preg_replace('/[a-z_]*[^,a-z_]/', '', $sql);
        $arr = explode(',', $sql);
        
        $cols = [];
        foreach ($arr as $val) {
            if (!in_array($val, $omit)) {
                $cols = trim($val);
            }
        }
        
        return $cols;
    }
    
    public static function setLimit($sql = '', $limit = '')
    {
        if (empty($sql)) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
            return preg_replace('/(LIMIT)(.*)?$/', '', trim($sql)) . ' ' . $limit;
        }
    }
    
    public static function addCondition($sql = '', $col, $val)
    {
        if (empty($sql)) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        $column = preg_match('/[a-z]*[.][a-z]*/', $col)
            ? $col
            : '`' . $col . '`';
        $statement = (strpos($sql, 'GROUP BY') !== false)
            ? 'GROUP BY'
            : 'ORDER BY';
        return preg_replace("/$statement/", "AND $column LIKE '%$val%' $statement", $sql);
    }
    
    public static function setSort($sql = '', $col = '')
    {
        if (empty($sql)) {
            throw new Exception('DB::ERROR - Invalid SQL statement.');
        }
        return preg_replace('/ORDER BY [^LIMIT]*/', '', trim($sql))
            . " ORDER BY $col ";
    }
    
    public static function sortByColumn($a, $b, $order_by, $order_dir = 'ASC')
    {
        $order_dir = strtoupper($order_dir);
        if (!in_array($order_dir, ['ASC', 'DESC'])) {
            $order_dir = 'ASC';
        }
        if ($a->$order_by == $b->order_by) {
            return 0;
        }
        if (
            ($order_dir ==  'ASC' && $a->$order_by > $b->$order_by) ||
            ($order_dir == 'DESC' && $a->$order_by < $b->order_by)
        ) {
            return 1;
        }
        return -1;
    }
}
