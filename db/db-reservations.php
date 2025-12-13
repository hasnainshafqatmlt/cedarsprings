<?php

// require the conn file ahead of adding on any of the database connection files
require_once __DIR__ . '/conn.php';


class reservationsDb extends dbConnection
{
    protected $host;
    protected $user;
    protected $password;
    protected $database;
    protected $conn;

    public $logger;


    function __construct($logger = NULL)
    {
        $this->host = get_option('custom_login_db_host', 'localhost');
        $this->user = get_option('custom_login_db_user', '');
        $this->password = get_option('custom_login_db_password', '');
        $this->database = get_option('custom_login_db_name', '');

        $this->conn = $this->connectDB();
    }


    function setLogger($logger = null)
    {
        return true;
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


/* Being used by the following pages and tools
 - tools/pages/bulk_email/addFamilyDaysToList.php
 - concierge/email-unsubscribe.php
 - tools/pages/bulk_email/endOfSummerMailer.php
 - tools/pages/events
 */