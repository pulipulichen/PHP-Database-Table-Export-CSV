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
        $table_name_params = $f3->get('PARAMS.table_name');
        $table_name_params = explode(".", $table_name_params);
        $table_name = $table_name_params[0];
        $format = NULL;
        if (count($table_name_params) > 1) {
            $format = $table_name_params[1];
        }
        
        $white_list = $this->_get_white_list($f3);
        
        if ($format === NULL
                || $this->_in_white_list($white_list, $table_name) === false) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Table "' . $table_name . '" exportion is not allowed.', true, 403);
            return;
        }
        
        $f3->set('CACHE', TRUE);
        $cache = \Cache::instance();
        $cache_key = 'table_' . $table_name;

        $cache->clear($cache_key);
        if ($cache->exists($cache_key)) {
            $result = $cache->get($cache_key);
        } else {
            $sql = 'select * from ' . $table_name;

            $db = new \DB\SQL('pgsql:host=' . $f3->get('DATABASE_HOST') . ';dbname=' . $f3->get('DATABASE_NAME')
                    , $f3->get('DATABASE_USER'), $f3->get('DATABASE_PASSWORD'));
            $result = $db->exec($sql);
            $cache->set($cache_key, $result, intval($f3->get('CACHE_TIME_TO_LIVE_MINUTES') * 60));
        }
        
        if (count($result) === 0) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Table "' . $table_name . '" is no data.', true, 403);
            return;
        }
        
        if ($format === 'arff') {
            $this->download_arff($f3, $result, $table_name);
        }
        else {
            $this->download_csv($f3, $result, $table_name);
        }
    }
    
    function download_csv($f3, $result, $table_name) {

        if ($f3->get('FILE_DOWNLOAD')) {
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename=' . $table_name . '.csv');
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
    
    function download_arff($f3, $result, $table_name) {
        
        $attribute_list = array_keys($result[0]);
        
        $attribute_type = array();
        
        foreach ($attribute_list AS $field_name) {
            $type = 'REAL';
            for ($i = 0; $i < count($result); $i++) {
                $value = $result[$i][$field_name];
                
                if (trim($value) === "") {
                    continue;
                }
                
                if (strlen($value) > 100) {
                    $type = "STRING";
                    break;
                }
                if ($type !== "REAL" || is_numeric($value) === FALSE) {
                    if ($type === "REAL") {
                        $type = array();
                        $i = -1;
                    }
                    else {
                        $value = "'" . addslashes($value) . "'";
                        if (in_array($value, $type) === FALSE) {
                            $type[] = $value;
                        }
                    }
                    
                    if (count($type) > 100) {
                        $type = "STRING";
                        break;
                    }
                }
            }
            $attribute_type[$field_name] = $type;
        }
        
        // -----------------------------------------

        if ($f3->get('FILE_DOWNLOAD')) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $table_name . '.csv');
            header('Pragma: no-cache');
        }
        
        $out = fopen('php://output', 'w');
        fputs($out, '@RELATION ' . $table_name . "\n\n");
        
        if (count($result) > 0) {
            //fputcsv($out, array_keys($result[0]));
            foreach ($result[0] AS $field_name => $field_value) {
                $type = $attribute_type[$field_name];
                if ($type === 'REAL') {
                    fputs($out, "@ATTRIBUTE " . $field_name . " REAL\n");
                }
                else if ($type === "STRING") {
                    fputs($out, "@ATTRIBUTE " . $field_name . " STRING\n");
                }
                else {
                    fputs($out, "@ATTRIBUTE " . $field_name . " {" . implode(",", $type) . "}\n");
                }
            }
        }

        fputs($out, "\n@DATA\n");
        foreach ($result as $row) {
            foreach ($row AS $key => $value) {
                if (trim($value) === "") {
                    $row[$key] = '?';
                }
                else {
                    $row[$key] = addslashes($value);
                }
            }
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
