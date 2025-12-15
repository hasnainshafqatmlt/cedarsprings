<?php

class CounterDBController
{
	private $host = "localhost";
	// private $password = "LimpidSnoutFreedEver87";
	private $password = "root"; // "LimpidSnoutFreedEver87";
	private $conn;
	// private $user = "cscamp_counter";
	private $user = "root"; // "cscamp_counter";
	private $database = "cscamp_view_counter";

	function __construct()
	{
		$this->conn = $this->connectDB();
	}

	function connectDB()
	{
		$conn = new mysqli($this->host, $this->user, $this->password, $this->database);

		if ($conn->connect_errno) {
			echo "Failed to connect to MySQL";
			exit();
		}

		return $conn;
	}

	function runBaseQuery($query)
	{
		if (!$result = mysqli_query($this->conn, $query)) {
			throw new Exception("runBaseQuery failed: " . $this->conn->error);
			return false;
		}

		while ($row = mysqli_fetch_assoc($result)) {
			$resultset[] = $row;
		}
		if (!empty($resultset))
			return $resultset;
	}

	function runQuery($query, $param_type, $param_value_array)
	{

		if (!$sql = $this->conn->prepare($query))
			throw new Exception("Failed to prepare query [  $query ]. Error description: " . $this->conn->error);

		$this->bindQueryParams($sql, $param_type, $param_value_array);

		try {
			$sql->execute();
		} catch (Exception $e) {
			throw new Exception("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
		}

		try {
			$result = $sql->get_result();
		} catch (Exception $e) {
			throw new Exception("Failed to get SQL Result. Error description: " . $this->conn->error, 0, $e);
		}


		// no result, don't get an error trying to count the number of rows
		if (!is_object($result)) {
			return false;
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
		$param_value_reference[] = &$param_type;
		for ($i = 0; $i < count($param_value_array); $i++) {
			// if you get a fatal error (Cannot crete reference to/from string offsets) here, you have the param_type and param_value inputs out of order in the calling code
			$param_value_reference[] = &$param_value_array[$i];
		}
		call_user_func_array(array(
			$sql,
			'bind_param'
		), $param_value_reference);
	}

	function insert($query, $param_type, $param_value_array)
	{
		if (!$sql = $this->conn->prepare($query))
			throw new Exception("Failed to prepare query. [ $query ] Error description: " . $this->conn->error);
		try {
			$this->bindQueryParams($sql, $param_type, $param_value_array);
		} catch (Exception $e) {
			throw new Exception("Failed to bind query parameters. Error description: " . $this->conn->error, 0, $e);
		}

		try {
			$sql->execute();
		} catch (Exception $e) {
			throw new Exception("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
		}

		return $this->conn->insert_id;
	}

	function update($query, $param_type, $param_value_array)
	{
		if (!$sql = $this->conn->prepare($query))
			throw new Exception("Failed to prepare query. [ $query ] Error description: " . $this->conn->error);

		$this->bindQueryParams($sql, $param_type, $param_value_array);
		try {
			$sql->execute();
		} catch (Exception $e) {
			throw new Exception("Failed to excecute SQL. Error description: " . $this->conn->error, 0, $e);
		}
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
