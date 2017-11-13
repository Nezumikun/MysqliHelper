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
        $this->db = new \mysqli($host, $username, $passwd, $dbname, $port, $socket);
        $this->db->set_charset($charset);
        if ($query_path != null) {
            $this->setQueryPath($query_path);
        }
    }
    
    public function setQueryPath($query_path) {
        $this->query_path = $query_path;
        $this->queries = [];
    }

    private function cleanNextResults() {
        while ($this->db->more_results()) {
            if ($this->db->next_result()) {
                $this->db->store_result ();
            }
        }
    }
    
    private function getAssoc(\mysqli_stmt $stmt) {
        if (method_exists($stmt, "get_result") && method_exists("mysqli_result", "fetch_assoc")) {
            $temp = $stmt->get_result();
            return $temp->fetch_assoc();
        }
        $res = [];
        $meta = $stmt->result_metadata();
        if ($meta === FALSE) {
            return $res;
        }
        $fields = [];
        foreach ($meta->fetch_fields() as $field) {
            ${$field->name} = NULL;
            $fields[] = $field->name;
        }
        $code = '$stmt->bind_result($' . implode(", $", $fields) . ');';
        eval($code);
        while ($stmt->fetch()) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = ${$field};
            }
            $res[] = $row;
        }
        $stmt->close();
        $this->cleanNextResults();
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
    
    protected function getFileName($query_name) {
        return $this->query_path . "/" . $query_name . ".sql";
    }
    
    protected function getSqlFile($query_name) {
        if (!array_key_exists($query_name, $this->queries)) {
            $query_file = $this->getFileName($query_name);
            if (!file_exists($query_file)) {
                throw new \Exception("File '" . $query_file . "' for query ". $query_name . " not found");
            }
            $this->queries[$query_name] = file_get_contents($query_file);
        }
        return $this->queries[$query_name];
    }

    public function getAssocFromFile($query_name, $params = null) {
        return $this->getAssocSql($this->getSqlFile($query_name), $params);
    }

    public function doFromFile($query_name, $params = null) {
        return $this->doSql($this->getSqlFile($query_name), $params);
    }
}
