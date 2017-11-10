<?php

namespace Nezumikun\MysqliHelper;

class MysqliHelper {
    /** @var mysqli Mysql database descriptor */
    protected $db;
    protected $queries = [];
    protected $query_path = "";
    
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $charset = 'utf8', $socket = null, $query_path = null) {
        if (is_array($host)) {
            foreach ($host as $key => $val) {
                $$key = $val;
            }
        }
        $this->db = new mysqli($host, $username, $passwd, $dbname, $port, $socket);
        $this->db->set_charset($charset);
        if ($query_path != null) {
            $this->setQueryPath($query_path);
        }
    }
    
    public function setQueryPath($query_path) {
        $this->query_path = $query_path;
        $this->queries = [];
    }

    private function getAssoc(mysqli_stmt $stmt) {
        $res = [];
        $meta = $stmt->result_metadata();
        if ($meta === FALSE) {
            return $res;
        }
        $fields = $meta->fetch_fields();
        $params = '';
        $comma = '';
        foreach ($fields as $field) {
            ${$field->name} = NULL;
            $params .= $comma . '$' . $field->name;
            $comma = ', ';
        }
        $code = '$stmt->bind_result(' . $params . ');';
        eval($code);
        while ($stmt->fetch()) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field->name] = ${$field->name};
            }
            $res[] = $row;
        }
        $stmt->close();
        while ($this->db->more_results()) {
            if ($this->db->next_result()) {
                $this->db->store_result ();
            }
        }
        return $res;
    }
    
    public function getAssocSql($query, $params = null) {
        $stmt =  $this->db->stmt_init();
        if (!$stmt->prepare($query)) {
            throw new Exception($stmt->error);
        }
        if ($params != null) {
            call_user_func_array([$stmt, "bind_param"], $params);
        }
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        return $this->getAssoc($stmt);
    }
    
    public function doSql($query, $params = null) {
        $stmt =  $this->db->stmt_init();
        if (!$stmt->prepare($query)) {
            throw new Exception($stmt->error);
        }
        if ($params != null) {
            call_user_func_array([$stmt, "bind_param"], $params);
        }
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }
    
    public function getAssocFromFile($query_name, $params = null) {
        $query_file = $this->query_path . $query_name . ".sql";
        if (!file_exists($query_file)) {
            throw new Exception("File for query ". $query_name . " not found");
        }
        return $this->getAssocSql(file_get_contents($query_file), $params);
    }

    public function doFromFile($query_name, $params = null) {
        $query_file = $this->query_path . $query_name . ".sql";
        if (!file_exists($query_file)) {
            throw new Exception("Файл для запроса ". $query_name . " не найден");
        }
        return $this->doSql(file_get_contents($query_file), $params);
    }
}
