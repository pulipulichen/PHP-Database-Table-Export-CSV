<?php

class PHPDatabaseTableExportCSV {

    function index($f3) {
        $white_list = $this->_get_white_list($f3);

        $f3->set('CACHE', TRUE);
        $cache = \Cache::instance();
        $cache->clear('table_name_list');
        if ($cache->exists('table_name_list')) {
            $table_name_list = $cache->get('table_name_list');
            $view_name_list = $cache->get('view_name_list');
        } else {
            $db = new \DB\SQL('pgsql:host=' . $f3->get('DATABASE_HOST') . ';dbname=' . $f3->get('DATABASE_NAME')
                    , $f3->get('DATABASE_USER'), $f3->get('DATABASE_PASSWORD'));

            $sql = "SELECT table_name
                FROM information_schema.tables
                WHERE table_schema='public'
                AND table_type='BASE TABLE' order by table_name";

            $table_names = $db->exec($sql);

            $table_name_list = array();
            foreach ($table_names as $name) {
                if ($this->_in_white_list($white_list, $name['table_name'])) {
                    $table_name_list[] = $name['table_name'];
                }
            }

            // ----------------------------------

            $sql = "select schemaname, viewname from pg_catalog.pg_views
                where schemaname NOT IN ('pg_catalog', 'information_schema')
                order by schemaname, viewname;";
            $name_list = $db->exec($sql);


            $view_name_list = array();
            foreach ($name_list as $name) {
                if ($this->_in_white_list($white_list, $name['viewname'])) {
                    $view_name_list[] = $name['viewname'];
                }
            }

            $cache->set('table_name_list', $table_name_list, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
            $cache->set('view_name_list', $view_name_list, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
        }
        
        $f3->set("table_name_list", $table_name_list);
        $f3->set("view_name_list", $view_name_list);
        echo View::instance()->render('table_name_list.htm');
    }

    function table_name($f3) {
        $white_list = $this->_get_white_list($f3);
        $table_name = $f3->get('PARAMS.table_name');
        if ($this->_in_white_list($white_list, $table_name) === false) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Table "' . $table_name . '" exportion is not allowed.', true, 500);
            return;
        }

        $f3->set('CACHE', TRUE);
        $cache = \Cache::instance();
        $cache_key = 'table_' . $f3->get('PARAMS.table_name');

        $cache->clear($cache_key);
        if ($cache->exists($cache_key)) {
            $result = $cache->get($cache_key);
        } else {
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

    function _in_white_list($white_list, $name) {
        if (is_array($white_list) 
                && in_array($name, $white_list)) {
            //echo $name . "-";
            return true;
        }
        else if (is_null($white_list) 
                || is_array($white_list) === false 
                || count($white_list) === 0 ) {
            return true;
        } else {
            return in_array($name, $white_list);
        }
    }

    function _get_white_list($f3) {
        return $f3->get("DATABASE_WHITE_LIST");
    }

}
