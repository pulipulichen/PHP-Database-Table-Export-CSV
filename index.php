<?php

// Kickstart the framework
$f3=require('lib/base.php');

$f3->set('DEBUG',1);
if ((float)PCRE_VERSION<7.9) {
	trigger_error('PCRE version is out of date');
}

// Load configuration
$f3->config('config.ini');

$f3->route('GET /',
	function($f3) {
            $f3->set('CACHE',TRUE);
            $cache = \Cache::instance();
            $cache->clear('table_name_list');
            //if ($cache->exists('table_name_list')) {
            if (false) {
                $table_name_list = $cache->get('table_name_list');
                $view_name_list = $cache->get('view_name_list');
            }
            else {
                $db = new \DB\SQL('pgsql:host=' . $f3->get('DATABASE_HOST') . ';dbname=' . $f3->get('DATABASE_NAME')
                        , $f3->get('DATABASE_USER'), $f3->get('DATABASE_PASSWORD'));

                $sql = "SELECT table_name
      FROM information_schema.tables
     WHERE table_schema='public'
       AND table_type='BASE TABLE' order by table_name";

                $table_names = $db->exec($sql);

                $table_name_list = array();
                foreach ($table_names as $name) {
                    $table_name_list[] = $name['table_name'];
                }

                // ----------------------------------

                $sql = "select schemaname, viewname from pg_catalog.pg_views
where schemaname NOT IN ('pg_catalog', 'information_schema')
order by schemaname, viewname;";
                $name_list = $db->exec($sql);


                $view_name_list = array();
                foreach ($name_list as $name) {
                    $view_name_list[] = $name['viewname'];
                }

                $cache->set('table_name_list', $table_name_list, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
                $cache->set('view_name_list', $view_name_list, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
            }
            
            $f3->set("table_name_list", $table_name_list);
            $f3->set("view_name_list", $view_name_list);
            echo View::instance()->render('table_name_list.htm');
	}
);

$f3->route('GET /@table_name',
	function($f3) {
            $f3->set('CACHE',TRUE);
            $cache = \Cache::instance();
            $cache_key = 'table_' . $f3->get('PARAMS.table_name');
    
            $cache->clear($cache_key);
            if ($cache->exists($cache_key)) {
                $result = $cache->get($cache_key);
            }
            else {
                $sql = 'select * from ' . $f3->get('PARAMS.table_name');

                $db = new \DB\SQL('pgsql:host=' . $f3->get('DATABASE_HOST') . ';dbname=' . $f3->get('DATABASE_NAME')
                        , $f3->get('DATABASE_USER'), $f3->get('DATABASE_PASSWORD'));
                $result = $db->exec($sql);
                $cache->set($cache_key, $result, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
            }
            
            // -----------------------------------------
            
            if ($f3->get('CSV_DOWNLOAD')) {
                header('Content-Type: application/csv');
                header('Content-Disposition: attachment; filename=' . $f3->get('PARAMS.table_name') . '.csv');
                header('Pragma: no-cache');
            }
            
            $out = fopen('php://output', 'w');
            if (count($result) > 0) {
                fputcsv($out, array_keys($result[0]));
            } 
            
            foreach ($result as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
	}
);

$f3->run();
