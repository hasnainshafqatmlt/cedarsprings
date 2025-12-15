<?php

// include the db file immediatly after this one - this has the base methods, but you have to connect to each DB through the specific file

class dbConnection
{
    protected $host;
    protected $user;
    protected $password;
    protected $database;
    protected $conn;

    public function __construct()
    {
        $this->host = get_option('custom_login_db_host', 'localhost');
        $this->user = get_option('custom_login_db_user', '');
        $this->password = get_option('custom_login_db_password', '');
        $this->database = get_option('custom_login_db_name', '');

        // Check for missing credentials
        if (empty($this->host) || empty($this->user) || empty($this->database)) {
            wp_die('Database credentials are not set in plugin settings.');
        }

        $this->conn = $this->connectDB();
    }



    function connectDB()
    {
        $conn = @new mysqli($this->host, $this->user, $this->password, $this->database);
        if ($conn->connect_errno) {
            echo 13;
            echo "Failed to connect to MySQL";
            wp_die("Failed to connect to database from " . __DIR__);
            exit();
        }

        return $conn;
    }

    function runBaseQuery($query)
    {
        if (!$result = mysqli_query($this->conn, $query)) {
            wp_die("runBaseQuery failed: " . $this->conn->error . ". SQL: " . $query);
            return false;
        }

        if (!is_bool($result)) {
            while ($row = mysqli_fetch_assoc($result)) {
                $resultset[] = $row;
            }
            if (!empty($resultset))
                return $resultset;
        }
    }

    function runQuery($query, $param_type, $param_value_array)
    {

        if (!$sql = $this->conn->prepare($query))
            wp_die("Failed to prepare query [  $query ]. Error description: " . $this->conn->error);

        $this->bindQueryParams($sql, $param_type, $param_value_array);

        try {
            $sql->execute();
        } catch (Exception $e) {
            wp_die("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
        }

        try {
            $result = $sql->get_result();
        } catch (Exception $e) {
            wp_die("Failed to get SQL Result. Error description: " . $this->conn->error, 0, $e);
        }

        // if I forget to use insert or update, i'll get a warning for no rows. I want to update the wording to correctly ID the issue
        if (is_bool($result)) {
            wp_die("Boolean result returned instead of query results. Ensure runQuery is not useds for INSERT, UPDATE, or DELETE transactions.");
            wp_die("Query: " . $query);
            return [];
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $resultset[] = $row;
            }
        }

        if (!empty($resultset)) {
            return $resultset;
        }
    }

    function bindQueryParams($sql, $param_type, $param_value_array)
    {

        // if there is a single value coming in, turn it into an array
        if (!is_array($param_value_array)) {
            $param_value_array = array($param_value_array);
        }

        $param_value_reference[] = &$param_type;

        for ($i = 0; $i < count($param_value_array); $i++) {
            // if you get a fatal error (Cannot crete reference to/from string offsets) here, you have the param_type and param_value inputs out of order in the calling code
            $param_value_reference[] = &$param_value_array[$i];
        }

        try {
            call_user_func_array(array(
                $sql,
                'bind_param'
            ), $param_value_reference);
        } catch (Exception $e) {
            wp_die("Failure to bind parameters to SQL.", 0, $e);
        }
    }

    function insert($query, $param_type, $param_value_array)
    {
        if (!$sql = $this->conn->prepare($query))
            wp_die("Failed to prepare query. [ $query ] Error description: " . $this->conn->error);
        try {
            $this->bindQueryParams($sql, $param_type, $param_value_array);
        } catch (Exception $e) {
            wp_die("Failed to bind query parameters. Error description: " . $this->conn->error, 0, $e);
        }

        try {
            $sql->execute();
        } catch (Exception $e) {
            wp_die("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
        }

        return $this->conn->insert_id;
    }

    function update($query, $param_type, $param_value_array)
    {
        if (!$sql = $this->conn->prepare($query)) {
            PluginLogger::log("Failed to prepare query. [ $query ] Error description: " . $this->conn->error);
            wp_die("Failed to prepare query. [ $query ] Error description: " . $this->conn->error);
        }


        $this->bindQueryParams($sql, $param_type, $param_value_array);
        try {
            $sql->execute();
        } catch (Exception $e) {
            PluginLogger::log("Failed to excecute SQL. Error description: " . $this->conn->error . $e->getMessage());
            wp_die("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
        }

        return $this->conn->affected_rows;
    }

    function lastInsertId()
    {
        return $this->conn->insert_id;
    }

    function real_escape_string($str)
    {
        return $this->conn->real_escape_string($str);
    }
}


/* Eamples of use

	    $db_handle = new DBController();
	    $query = "Select * from tbl_token_auth where username = ? and is_expired = ?";
	    $result = $db_handle->runQuery($query, 'si', array($username, $expired));
    
        $db_handle = new DBController();
        $query = "UPDATE tbl_token_auth SET is_expired = ? WHERE id = ?";
        $expired = 1;
        $result = $db_handle->update($query, 'ii', array($expired, $tokenId));
    
        $db_handle = new DBController();
        $query = "INSERT INTO tbl_token_auth (username, password_hash, selector_hash, expiry_date) values (?, ?, ?,?)";
        $result = $db_handle->insert($query, 'ssss', array($username, $random_password_hash, $random_selector_hash, $expiry_date));
    
*/
