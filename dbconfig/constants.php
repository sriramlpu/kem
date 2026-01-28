<?php
date_default_timezone_set("Asia/Kolkata");
/***
 *	File For Creating The Constants

 *	License	GNU General Public License
 *
 */

/****
 *	Checking Whether Localhost Is Using
 *
 */
define('LOCAL_WEBSITE','sapp');
error_reporting(E_ALL ^ E_NOTICE);
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == "localhost" || $_SERVER['HTTP_HOST'] == "127.0.0.1")) {
	
	#	 Define For Database Server  *	 Database Server	= localhost
	
	DEFINE('DB_SERVER', "localhost");
	
	#	 Define For Database User Name *	 Database User Name	= root
	 
	DEFINE('DB_USER', "root");
	
	# Define For Database User Password *	 Database Password	=   ''
	
	DEFINE('DB_PASS', "");
	
	# Define For Database Name  *	 Database Name	=  sdar
	 
	DEFINE('DB_DATABASE', "kmk");
	
	define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'].'/'.LOCAL_WEBSITE.'/');
	
	
	define('SERVER_PATH' , 'http://'.$_SERVER['HTTP_HOST'].'/'.LOCAL_WEBSITE.'/view/');
	
	define('INDEX_URL' , 'http://'.$_SERVER['HTTP_HOST'].'/'.LOCAL_WEBSITE.'/');
	
	# for ini file path
	define('LOG_FILE_PATH',$_SERVER['DOCUMENT_ROOT'].'/'.LOCAL_WEBSITE.'applog');
	
} else {
	
	
	#	 Define For Database Server * Database Server	= localhost
	DEFINE('DB_SERVER', "localhost");
	
	#	 Define For Database User Name * Database User Name	= seedtoj1_sdar
	
	DEFINE('DB_USER', "kmkglobal_web");
	
	#	 Define For Database User Password  *	 Database Password	=   ''
	
	DEFINE('DB_PASS', 'tI]rfPhdOo9zHdKw');

	
	#	 Define For Database Name *	 Database Name	=  sdar
	
	DEFINE('DB_DATABASE', "kmkglobal_web");
	define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'].'/');
		
	define('SERVER_PATH', 'http://'.$_SERVER['HTTP_HOST'].'/view/');	
	
	define('INDEX_URL' , 'http://'.$_SERVER['HTTP_HOST'].'/');
	
	
	# for error log files
	define('LOG_FILE_PATH', $_SERVER['DOCUMENT_ROOT'].'/applog');
	
	
}


	
/***
 *	(1)Syllabus Module
 *	Tables Code ::: aa
 * 	Total 14 Tables
 */

DEFINE('TABLE_STUDENTS', "booking");
DEFINE('TABLE_CITIES', "cities");
DEFINE('TABLE_USERS', "users");
DEFINE('TABLE_DOCTORS', "doctors");
DEFINE('TABLE_SERVICES', "services");
DEFINE('TABLE_SPECIALITY', "speciality");
DEFINE('TABLE_ADMIN', "admin");
DEFINE('TABLE_EXECUTIVES', "executives");
DEFINE('TABLE_ORDERS', "orders");
DEFINE('TABLE_IMAGEAREA', "imagearea");



// for  configrations
define("KMVALUE", 4);
/**************** Logging Files ***************/
/*
 * define the log level as below
 * 0 for Emergency Action Required
 * 1 for Alerts
 * 2 for Errors
 * 3 for Some warnings
 * 4 for Noties
 */


define("LOG_MAX_LEVEL", 5);
define("LOG_MIN_LEVEL", 0);
define("LOG_LEVEL", 2);
define("EMERGENCY_ACTION_REQUIRED",0);
define("ALERT",1);
define("ERRORS",2);
define("WARNINGS",3);
define("NOTICES",4);
define("INFORMATION",5);
?>
