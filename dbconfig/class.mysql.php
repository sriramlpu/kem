<?php
/**
 * Database abstraction class, make very easy to work with databases.
 * 
 * @author DVTS.
 * @name PDO Class
 */

 /**
 * Database specific class - MySQL
 *
 */
ini_set('max_execution_time', 3000);
require_once 'constants.php';
class DataBasePDO
{		
	/*********************************************************************
	 * Conection Parameters												*
	 **********************************************************************/

	private static $hostname	=  DB_SERVER;  
	private static $username	=  DB_USER;
	private static $password	=  DB_PASS;
	private static $dbname		=  DB_DATABASE; 
	private static $instance 	=  FALSE;
	private $db;
	public $fetch_mode 			=  PDO::FETCH_ASSOC;


	/*********************************************************************
	 * Cached parameters												*
	 **********************************************************************/

	private $last_query			= NULL;
	private $last_statement		= NULL;
	private $last_result		= NULL;
	private $row_count			= NULL;
	private $affected_row		= NULL;

	/**
	 * Constructor.
	 * @return object DB
	 * @access public
	 */

	public function __construct($connect = true, $dataBase = null, $server = null,$userName = null, $passWord = null){
			if ($dataBase !== null) $this->dbname  = $dataBase;
			if ($server   !== null) $this->hostname  = $server;
			if ($userName !== null) $this->username    = $userName;
			if ($passWord !== null) $this->password   = $passWord;
				
			ini_set('track_errors',1);
			if (!self::$instance){
					$this->connectToDB();
			}
			return self::$instance;		
	}
	/**
	 * Connect to the database and set the error mode to Exception. 
	 * @return void
	 * @access public
	 */

	public function connectToDB(){
		try {
			$dns='mysql:host='.self::$hostname.';dbname='.self::$dbname;
			self::$instance = new PDO($dns, self::$username, self::$password);
			self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db = self::$instance;
		}catch (PDOException $e) {
			print "Error No <b> GEDM-1001 </b>";
			die();
		}
	}
	/**
	*Insert or Update data based on where condition
	* @param string $table
	* @param array $valueAr
	* @param array/string $where
	* @access public
	* @If $where condition is NULL it executes insertData
	* @If $where condition is NOTNULL it executes updateData
	**/

	public function insertOrUpdate($table = NULL, $valueAr = NULL, $where= NULL){

			if(! is_null($where)){
				return $this->updateData($table,$valueAr,$where);
			}
			else{
				return $this->insertData($table,$valueAr);
			}
	}

	/**
	 * Insert a value into a table.
	 * @param string $table
	 * @param array $valueAr
	 * @return void
	 * @access public
	 * @todo Validate if $table or $valueAr is null.
	 */	
	

	public function insertData($table=NULL,$valueAr=NULL){
		
			array_walk($valueAr,'DataBasePDO::prepareDbValues');
			
			$sqlQuery = "INSERT INTO `".$table."` 
					(`".implode('`, `', array_keys($valueAr))."`) 
					VALUES (".implode(', ', $valueAr).")";
					//print_r($sqlQuery); //die();
					 return $this->insertExecuteQuery($sqlQuery);							 
	}
	/**
	 * Select values form the table.
	 * @param string $table
	 * @param array $valueAr
	 * @return void
	 * @access public
	 * @todo Validate if $table or $valueAr is null.
	 */		

	public function selectData($table,$whereAr=1,$columns=NULL,$limit=NULL){
				
			if(!is_null($columns)){
				$sqlQuery=$this->buildSQLColumns($columns);
			}
			else{
				$sqlQuery="*";
			}			
			$sqlQuery = "SELECT ".$sqlQuery." FROM `".$table."`";
			if (is_array($whereAr)){
					$sqlQuery .=$this->buildSQLWhereClause($whereAr);
			}
			else{
					$sqlQuery .=" WHERE $whereAr";
			}
			if (! is_null($limit)) {
				$sqlQuery .= " LIMIT " . $limit;
			} 
            
			return $this->getAllResults($sqlQuery);							 
	}
	
	/**
	 * [STATIC] Builds a comma delimited list of columns for use with SQL
	 *
	 * @param array $valuesArray An array containing the column names.
	 * @param boolean $addQuotes (Optional) TRUE to add quotes
	 * @param boolean $showAlias (Optional) TRUE to show column alias
	 * @return string Returns the SQL column list
	 *  Venkat
	 */
	static private function buildSQLColumns($columns, $addQuotes = true, $showAlias = true) {
		if ($addQuotes) {
			$quote = "`";
		} else {
			$quote = "'";
		}
		switch (gettype($columns)) {
			case "array":
				$sql = "";
				foreach ($columns as $key => $value) {
					// Build the columns
					if (strlen($sql) == 0) {
						$sql = $quote . $value . $quote;
					} else {
						$sql .= ", " . $quote . $value . $quote;
					}
					if ($showAlias && is_string($key) && (! empty($key))) {
						$sql .= ' AS "' . $key . '"';
					}
				}
				return $sql;
				break;
			case "string":
				return $quote . $columns . $quote;
				break;
			default:
				return false;
				break;
		}
		
	}
	
	/**
	 * [STATIC] Builds a SQL WHERE clause from an array.
	 * If a key is specified, the key is used at the field name and the value
	 * as a comparison. If a key is not used, the value is used as the clause.
	 *
	 * @param array $whereArray An associative array containing the column
	 *                           names as keys and values as data. The values
	 *                           must be SQL ready (i.e. quotes around
	 *                           strings, formatted dates, ect)
	 * @return string Returns a string containing the SQL WHERE clause
	 */
	static public function buildSQLWhereClause($whereAr) {
		$where = "";
		foreach ($whereAr as $key => $value) {
			if (strlen($where) == 0) {
				if (is_string($key)) {
					$where = " WHERE `" . $key . "` = " . $value;
				} else {
					$where = " WHERE " . $value;
				}
			} else {
				if (is_string($key)) {
					$where .= " AND `" . $key . "` = " . $value;
				} else {
					$where .= " AND " . $value;
				}
			}
		}
		return $where;
	}
	/**
	 * Update a value(s) in a table
	 * Ex: 
	 * $table = 'tableName';
	 * $data = array('text'=> 'value', 'date'=> '2009-12-01');
	 * $where = array('id=1','AND name="hai"'); OR $where = 'id = 1';
	 * @param string $table
	 * @param array $valueAr
	 * @param array/string $where
	 * @return void
	 * @access public
	 * @todo Validate the $table, $data, $where variables.
	 */
	
	public function updateData($table = NULL, $valueAr = NULL, $whereAr= NULL){
			array_walk($valueAr,'DataBasePDO::prepareDbValues');

			foreach ($valueAr as $key => $val){
					$valstr[]= '`'.$key.'` = '.$val;
			}

			$sqlQuery = "UPDATE `".$table."` SET ".implode(', ', $valstr);
			
			$sqlQuery .=$this->buildSQLWhereClause($whereAr);
			//print_r($sqlQuery);
		    return $this->executeQuery($sqlQuery);
	}

	/**
	 * Delete a record from a table.
	 * Ex.
	 * $table = 'tableName';
	 * $where = array('id = 12','AND name = "John"'); OR $where = 'id = 	12';
	 * @param string $table
	 * @param array/string $where
	 * @return void
	 * @access public
	 * @todo Validate the $table, $where variables.
	 */

	public function deleteData($table = NULL, $where = NULL){
			$sqlQuery = "DELETE FROM `".$table."` ";
			if (is_array($where)){
					$sqlQuery.= $this->buildSQLWhereClause($where);
			}
			else{
					$sqlQuery.= "WHERE ". $where;
			}
 
		return	$this->executeQuery($sqlQuery);
	}

	/**
	 * Helper function, walk the array, and modify the values.
	 * @param pointer $item
	 * @return void
	 * @access public 
	 */


	public static function prepareDbValues(&$item){
				$item = "'".self::escape($item)."'";
	}
	
	/**
	 * Execute a query (INSERT, UPDATE, DELETE).
	 * @param string $sqlQuery
	 * @return int
	 * @access public
	 * @todo Validate $sqlQuery
	 */
		
	public function executeQuery($sqlQuery = NULL){
		
		$this->last_query = $sqlQuery;
		 
		try{
			$this->affected_row = $this->db->exec($sqlQuery);
		}catch (PDOException $e) {
			print "Error No <b> GEDM-1003 $e </b>";
			die();
		}
			
		if ( $this->catch_error() ) return false;
		return $this->affected_row;
	}	
	/**
	 * Execute a query (INSERT).
	 * @param string $sqlQuery
	 * @return int
	 * @access public
	 * @todo Validate $sqlQuery
	 */
		
	public function insertExecuteQuery($sqlQuery = NULL){
	
		$this->last_query = $sqlQuery;
		
		try{
			$this->affected_row = $this->db->exec($sqlQuery);
		}catch (PDOException $e) {
			print "Error No <b> $e </b>";
			die();
		}
			
		if ( $this->catch_error() ) return false;
		
		return $this->db->lastInsertId();
	}	
	
	/** 
	 * Get one row from the DB.
	 * @param string $sqlQuery
	 * @return result set
	 * @access public
	 * @todo Validate $sqlQuery.
	 */

	public function getOneRow($sqlQuery = NULL){
			$this->internalQuery($sqlQuery);
			$result = $this->last_statement->fetch();
			$this->last_result = $result;
			return $result;		
	}

	/**
	 * Return the the query as a result set.
	 * @param string $sqlQuery
	 * @return result set
	 * @access public
	 * @todo Validate $sqlQuery.
	 */

	public function getAllResults($sqlQuery = NULL){
		
		try {
			$this->internalQuery($sqlQuery);
			$result = $this->last_statement->fetchAll();
			$this->last_result = $result;
		}catch( PDOException $e) {print_r($sqlQuery);die();
			print "Error No <b> $e</b>";
			die();
		}		
		return $result;
	}

	/**
	 * Execute a query.
	 * This function can be used from DB class methods.
	 * @param string $sqlQuery
	 * @return bool
	 * @access public
	 * @todo Validate $sqlQuery
	 */

	public function internalQuery($sqlQuery = NULL){
	        $this->last_query = $sqlQuery;
			$stmt = $this->db->query($sqlQuery);
			if ( $this->catch_error() ) return false;
			$stmt->setFetchMode($this->fetch_mode);
			$this->last_statement = $stmt;
			return TRUE;
	}

	/**
	*  Format a mySQL string correctly for safe mySQL insert
	*  (no mater if magic quotes are on or not)
	* @param string $str
	* @return string
	* @access public
	*/
	
	public static function escape($str){
		     $strm =stripslashes($str);
		     
		     $search  = array("\\","\0","\n","\r","\x1a","'",'"');
             $replace = array("\\\\","\\0","\\n","\\r","\Z","\'",'\"');
             return str_replace($search,$replace,$strm);
		     
			//return $strm ;
	}

	/**
	 * Set the PDO fetch mode.
	 * @param string $fetch_mode
	 * @return void
	 * @access public
	 */
	public function setFetchMode($fetch_mode){
			$this->fetch_mode = $fetch_mode;
	}
	
	/**
	 * Return the last insert id.
	 * @return integer
	 * @access public
	 */
	public function getLastInsertId(){
			return $this->db->lastInsertId();
	}
	
	/**
	 * Return the last executed query.
	 * @return string
	 * @access public
	 */
	public function getLastQuery(){
			return $this->last_query;
	}
	
	/**
	 * Returns the number of rows affected by the last SQL statement.
	 * @return int
	 * @access public
	 */
	public function rowCount(){
		if (!is_null($this->last_statement)){
				return $this->last_statement->rowCount();
		}
		else{
				return 0;
		}
	}


	function catch_error(){
			$err_array = $this->db->errorInfo();
	
			if ( isset($err_array[1]) && $err_array[1] != 25){
				try {
					throw new Exception();
				}
				catch (Exception  $e){
					print $this->getLastQuery();
					die();
				}
			}
	}	
	/**
	 * Select values form the table.
	 * @param string $table
	 * @param array $valueAr
	 * @return void
	 * @access public
	 * @todo Validate if $table or $valueAr is null.
	 */		

	public function countData($table,$whereAr=1,$columns=NULL,$limit=NULL){
				
		if(!is_null($columns)){
			$sqlQuery="COUNT($columns[0]) AS COUNT";
		}
		else{
			$sqlQuery="*";
		}			
		$sqlQuery = "SELECT ".$sqlQuery." FROM `".$table."`";
		if (is_array($whereAr)){
				$sqlQuery .=$this->buildSQLWhereClause($whereAr);
		}
		else{
				$sqlQuery .=" WHERE $whereAr";
		}
		if (! is_null($limit)) {
			$sqlQuery .= " LIMIT " . $limit;
		}
		
		return $this->getAllResults($sqlQuery);							 
	}
	
	public function selectColumnsByTableName($table){
				
		$sqlQuery = "SHOW FULL COLUMNS FROM `".$table."`";
		return $this->getAllResults($sqlQuery);							 
	}
	
	public function selectTablesByDBName($dataBaseName){
				
		$sqlQuery = "SHOW FULL TABLES FROM `".$dataBaseName."`";
		return $this->getAllResults($sqlQuery);							 
	}
	
	public function insertArrayData($table=NULL,$valueAr=NULL){
			
		$inputArray = '';
			
		for($i=0;$i < count($valueAr) ; $i++){
			array_walk($valueAr[$i],'DataBasePDO::prepareDbValues');
			$inputArray  .= ' ('.implode(', ', $valueAr[$i]).' ),';
		}
		$outputArray			= substr($inputArray,0,strlen($inputArray)-1);
			
		$sqlQuery = "INSERT INTO `".$table."` 
					(`".implode('`, `', array_keys($valueAr[0]))."`) 
					VALUES ".$outputArray." ";
					
		return $this->executeQuery($sqlQuery);							 
	}

	/**
	 * Begin a database transaction.
	 * @return bool
	 */
	public function beginTransaction() {
		return $this->db->beginTransaction();
	}

	/**
	 * Commit the current transaction.
	 * @return bool
	 */
	public function commit() {
		return $this->db->commit();
	}

	/**
	 * Roll back the current transaction.
	 * @return bool
	 */
	public function rollBack() {
		return $this->db->rollBack();
	}

}
?>