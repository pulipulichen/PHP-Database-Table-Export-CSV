<?php
ini_set('max_execution_time', 30000);

// Kickstart the framework
$f3=require('lib/base.php');

$f3->set('DEBUG',1);
if ((float)PCRE_VERSION<7.9) {
	trigger_error('PCRE version is out of date');
}

// Load configuration
$f3->config('config.ini');
include 'Controller/PHPDatabaseTableExportCSV.php';

$f3->route('GET /', 'PHPDatabaseTableExportCSV->index');
$f3->route('GET /index.php', 'PHPDatabaseTableExportCSV->index');

$f3->route('GET /@table_name', 'PHPDatabaseTableExportCSV->table_name');

$f3->run();
