<?php

//Built February 2021 by Ben Nyquist

date_default_timezone_set('America/Los_Angeles');

require_once(__DIR__ . '/lib/conn.php');

class ViewCounter
{

    protected $clientIP;
    protected $currentPage;

    protected $db;
    protected $timestamp;

    public $debug;

    function __construct()
    {
        $this->db = new CounterDBController();
    }


    function recordVisit($page, $ip)
    {

        $this->setTimeStamp();
        $this->setIpAddress($ip);
        $this->setPage($page);
        $this->storeVisit();
    }


    function setTimeStamp()
    {
        $this->timestamp = date('Y-m-d H:i:s');
        return $this->timestamp;
    }

    function setIpAddress($ip)
    {
        $this->clientIP = $ip;
    }

    function setPage($page)
    {
        $this->currentPage = $page;
    }

    function storeVisit()
    {
        $sql = 'INSERT INTO history (ip, page, date) VALUES (?,?,?)';
        $values = array($this->clientIP, $this->currentPage, $this->timestamp);

        try {
            $this->db->insert($sql, 'sss', $values);
        } catch (Exception $e) {
            throw new Exception("Unable to create the database record", 0, $e);
        }
    }
}
