<?php
require_once('dbconfig/class.mysql.php');
$dbObj = new DataBasePDO();

function insData($table, $valAr)
{
	global $dbObj;
	$result = $dbObj->insertData($table, $valAr);
	return $result;
}

function upData($table, $valAr, $whAr)
{
	global $dbObj;
	$result = $dbObj->updateData($table, $valAr, $whAr);
	return $result;
}

function excuteSql($sql)
{
	global $dbObj;
	$result = $dbObj->executeQuery($sql);
	return $result;
}
function exeSql($sql)
{
	global $dbObj;
	$result = $dbObj->getAllResults($sql);
	
	return $result;
}
function getStatus($s)
{
	if ($s == 1) {
		$res = "Active";
	} elseif ($s == 2) {
		$res = "Deactive";
	} else {
		$res = "Close";
	}

	return $res;
}
function getServieList()
{
	global $servTable;
	global $dbObj;
	$sql = 'SELECT   
						 *   
						  FROM ' .
		$servTable . ' WHERE status=1';


	$result = $dbObj->getAllResults($sql);
	//echo"<pre>";print_r($result);die();
	return $result;
}
function getAllDistricts($id = 0)
{
	global $distTable;
	global $dbObj;
	$sql = 'SELECT   
						 *   
						  FROM ' .
		$distTable;
	if ($id) {
		$sql .= ' WHERE
							  id = "' . $id . '"';
	}

	$result = $dbObj->getAllResults($sql);
	//echo"<pre>";print_r($result);die();
	return $result;
}
function getCount($table, $con = 0)
{
	global $dbObj;
	$sql = 'SELECT   
					 count(*) as nos  
					  FROM ' .
		$table;

	if ($con) {
		$sql .= ' WHERE ' . $con;
	}
// 		 echo $sql;
	$result = $dbObj->getAllResults($sql); //echo"<pre>";print_r($result);die();
	return $result[0]['nos'];
}


function getSum($table, $column, $con = 0)
{
	global $dbObj;
	$sql = 'SELECT   
						 SUM(' . $column . ') as nos  
						  FROM ' .
		$table;

	if ($con) {
		$sql .= ' WHERE ' . $con;
	}
// 		 echo $sql;
	$result = $dbObj->getAllResults($sql); //echo"<pre>";print_r($result);die();
	return $result[0]['nos'];
}

function getExecutivesBySpes($spid)
{
	global $docsTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$docsTable . ' as e 
						
						 WHERE
							  e.spid = "' . $spid . '"';
	$result = $dbObj->getAllResults($sql);
	//echo"<pre>";print_r($result);die();
	return $result;
}

function getExecutivesByCitySps($spid, $cid)
{
	global $docsTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$docsTable . ' as e 
						
						 WHERE
							  e.spid = "' . $spid . '" and e.cid="' . $cid . '"';
	$result = $dbObj->getAllResults($sql);
	//echo"<pre>";print_r($result);die();
	return $result;
}
function getSpecialitiesByCategory($sid)
{
	global $specTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$specTable . ' as e 
						
						 WHERE
							  e.sid = "' . $sid . '"';
	$result = $dbObj->getAllResults($sql);
	//echo"<pre>";print_r($result);die();
	return $result;
}

function getAllCities()
{
	//	echo $userId;die();
	global $cityTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$cityTable . ' as e 
						
						 WHERE 1 ORDER BY name';
	$result = $dbObj->getAllResults($sql); //echo"<pre>";print_r($result);die();
	return $result;
}
function getAllCitiesByDist($did)
{
	//	echo $userId;die();
	global $cityTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$cityTable . ' as e 
						
						 WHERE
							  e.dtid = "' . $did . '" ORDER BY e.name';
	$result = $dbObj->getAllResults($sql); //echo"<pre>";print_r($result);die();
	return $result;
}
function getPerticularSub($table, $cname, $con = 0)
{
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$table . ' 
						
						 WHERE
							  name = "' . $cname . '"';

	if ($con) {
		$sql .= ' AND ' . $con;
	}
	$result = $dbObj->getAllResults($sql);   //echo"<pre>";print_r($result);die();
	return $result;
}
//
function getSubject($table, $con = 0, $order = 1, $ort = 'ASC')
{
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$table;

	if ($con) {
		$sql .= ' WHERE ' . $con;
	}
	$sql .= ' ORDER BY ' . $order . ' ' . $ort;
	// echo $sql;
	$result = $dbObj->getAllResults($sql);
	// echo"<pre>";
	// print_r($result);
	// die();
	return $result;
}
// function getValue($table, $id)
// {
// 	global $dbObj;
// 	$sql = 'SELECT   
// 					 *   
// 					  FROM ' .
// 		$table . ' WHERE id=' . $id;

// 	$result = $dbObj->getOneRow($sql);

// 	return $result['name'];
// }

function getValue($table, $id, $idColumn = 'id')
{
    global $dbObj;
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $idColumn . '=' . $id;
    $result = $dbObj->getOneRow($sql);
    return $result['name'];
}
// function getName($table, $name)
// {
// 	global $dbObj;
// 	$sql = 'SELECT   
// 					 *   
// 					  FROM ' .
// 		$table . ' WHERE name like "%' . $name . '%"';

// 	$result = $dbObj->getOneRow($sql);

// 	return $result['id'];
// }
function getName($table, $name, $idColumn = 'id')
{
    global $dbObj;
    $sql = 'SELECT * FROM ' . $table . ' WHERE name like "%' . $name . '%"';
    $result = $dbObj->getOneRow($sql);
    return $result[$idColumn];
}
// function getIdName($table, $id)
// {
// 	global $dbObj;
// 	$sql = 'SELECT   
// 					 *   
// 					  FROM ' .
// 		$table . ' WHERE id = "' . $id . '"';

// 	$result = $dbObj->getOneRow($sql);

// 	return $result['name'];
// }
function getIdName($table, $id, $idColumn = 'id')
{
    global $dbObj;
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $idColumn . ' = "' . $id . '"';
    $result = $dbObj->getOneRow($sql);
    return $result['name'];
}
function getValues($table, $con)
{
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$table . ' WHERE ' . $con;

	$result = $dbObj->getOneRow($sql);

	return $result;
}
function getField($table, $id, $field, $idColumn = 'id')
{
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$table .  ' WHERE ' . $idColumn . '=' . $id;

	$result = $dbObj->getOneRow($sql);

	return $result[$field];
}
function getFieldId($table, $field, $val, $idColumn = 'id')
{
    global $dbObj;
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $field . '=' . '"' . $val . '"';
    $result = $dbObj->getOneRow($sql);
    return $result[$idColumn];
}

// function getFieldId($table, $field, $val)
// {
// 	global $dbObj;
// 	$sql = 'SELECT   
// 					 *   
// 					  FROM ' .
// 		$table . ' WHERE ' . $field . '=' . '"' . $val . '"';
// 	$result = $dbObj->getOneRow($sql);

// 	return $result['id'];
// }
// function getRowValues($table, $id)
// {
// 	global $dbObj;
// 	$sql = 'SELECT   
// 					 *   
// 					  FROM ' .
// 		$table . ' WHERE id=' . $id;
// // echo $sql;
// 	$result = $dbObj->getOneRow($sql);
// 	return $result;
// }
function getRowValues($table, $id, $idColumn = 'id')
{
    global $dbObj;
    $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $idColumn . '=' . $id;
    $result = $dbObj->getOneRow($sql);
    return $result;
}

function dispJson($js)
{
	$jds = json_decode($js);
	$items = '';
	foreach ($jds as $x => $jd) {
		$n = $x + 1;
		$items .= $n . ') ' . $jd . '<br/>';
	}
	return $items;
}
function getAdminDetails($aid)
{
	//	echo $userId;die();
	global $adminTable;
	global $dbObj;
	$sql = 'SELECT   
					 *   
					  FROM ' .
		$adminTable . ' as e 
						
						 WHERE
							  e.admin_id = "' . $aid . '"';
	$result = $dbObj->getAllResults($sql); //echo"<pre>";print_r($result);die();
	return $result[0];
}
//for  pharma things


function getItemsBySid($sid)
{
	global $dbObj;
	$table = TABLE_ITEMS;

	$whereArray				= array("`sid`='$sid' ORDER BY name ");

	$results					= $dbObj->selectData($table, $whereArray);

	return $results;
}
function getAllIssueByCust($cid)
{
	global $dbObj;
	$table = TABLE_ISSUES;

	$whereArray	= array(" `cid`='$cid' ORDER BY created DESC ");

	$results					= $dbObj->selectData($table, $whereArray);

	return $results;
}
function getAllINotifications()
{
	global $dbObj;
	$table = 'notification';

	$whereArray = array(" 1 ORDER BY created DESC ");

	$results = $dbObj->selectData($table, $whereArray);

	return $results;
}
function statusUpdate($sid, $st)
{
	global $dbObj;
	$mtable = TABLE_ISSUES;
	$valueAr['istatus'] = $st;
	$whereArray = array("`id`='$sid'");
	$result = $dbObj->updateData($mtable, $valueAr, $whereArray);
}
function statusName($sid)
{
	global $dbObj;
	$mtable = TABLE_STATUS;
	$sql = "select sname from $mtable where id=$sid";
	$result = $dbObj->getOneRow($sql);
	return $result['sname'];
}

function getReplyDetails($iid)
{
	global $dbObj;
	$table = TABLE_REPLIES;

	$whereArray				= array(" `iid`='$iid' ORDER BY created DESC ");

	$results					= $dbObj->selectData($table, $whereArray);

	return $results;
}
function smsSend($to, $message)
{
	$message = urlencode($message);
	$sendUrl = "http://smslogin.mobi/spanelv2/api.php?username=IMAFSS&password=Fss@123&to=91" . $to . "&from=IMAFSS&message=" . $message;

	if (function_exists('curl_init')) {
		$ch = curl_init();      // initialize a new curl resource
		curl_setopt($ch, CURLOPT_URL, $sendUrl);      // set the url to fetch
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY); // Personally I prefer CURLAUTH_ANY as it covers all bases
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // common name and also verify that it matches the hostname    provided)
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0); // don't give me the headers just the content
		$content = curl_exec($ch);
		$error = curl_error($ch);

		curl_close($ch); // remember to always close the session and free all resources
	} else {
		// curl library is not installed so we better use something else

		file_get_contents($sendUrl);
	}
	if (!$error) {
		return "success";
	} else {
		return $error;
	}
}

