<?php

// The object for interacting with Ultracamp Directly

class UltracampModel
{

    protected $ucCurl;
    protected $url;

    protected $production;
    protected $logger;
    public $queryTime;

    function __construct($logger = null)
    {
        $this->ucCurl = $this->setupUC();
        $this->setLogger($logger);

        $host = gethostname();
        if (isset($host) && $host == 'host.cedarsprings.camp') {
            $this->production = true;
        }
    }

    /**
     * Holds the basic Ultracamp connection information
     * Initiates the curl object
     */
    private function setupUC()
    {

        // List the reservations for the requested session
        $curl = curl_init();

        // Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "107:7EJWNDKAMG496K9Q");
        // Basic MTA3OjdFSldOREtBTUc0OTZLOVE=

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // Ultracamp error on 6/21/22 created cert issues - to resolve, I skipped the check.
        // commented out this flag on 10/13/22 after the system stabalized over the summer
        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        return $curl;
    }

    /**
     * Helper object for each of the functions. This is simply to avoid repeating code.
     */
    function setURL($newURL)
    {
        $this->url = $newURL;
        curl_setopt($this->ucCurl, CURLOPT_URL, $this->url);
    }

    /**
     * Takes the request built by each method and processes it through the curl object
     * returns back the results and logs the time the query took to the debug log stream
     */
    function processRequest($url)
    {

        $this->setURL($url);

        $result = curl_exec($this->ucCurl);
        $this->logger->d_bug("Ultracamp API Time", curl_getinfo($this->ucCurl, CURLINFO_TOTAL_TIME));
        $this->logger->d_bug($this->url);

        /* This type of result will come back on error
		
			string(85) "{"StatusCode":404,"Message":"Person not found","DeveloperMessage":"Person not found"}"
		*/

        $responseCode = curl_getinfo($this->ucCurl, CURLINFO_HTTP_CODE);

        if ($responseCode < 200 || $responseCode >= 300) {
            $this->logger->error("Ultracamp responded with an error when processing an API reqeust. Response Code: " . $responseCode);
            if (is_array($result)) {
                $this->logger->error("Ultrcamp Full Response", $result);
            } else {
                $this->logger->error("Ultrcamp Full Response", [$result]);
            }
        }

        return json_decode($result);
    }

    /**
     * Get the person information when a person Id is provided
     */
    function getPersonById($personId)
    {
        $url = "https://rest.ultracamp.com/api/camps/107/people/" . $personId;

        return $this->processRequest($url);
    }
    /**
     * Lists the people on an account when provided an account Id
     */
    function getPeopleByAccount($accountId)
    {
        $url = "https://rest.ultracamp.com/api/camps/107/people?accountNumber=" . $accountId;

        return $this->processRequest($url);
    }

    /**
     * Returns the person and info for the primary account holder
     */
    function getPrimaryAccountPerson($accountId)
    {
        // get the people
        $people = $this->getPeopleByAccount($accountId);

        foreach ($people as $person) {
            if ($person->PrimaryContact == 'true') {
                return $person;
            }
        }
    }

    /**
     * Reservation details from reservation Id
     */
    function getReservationByReservationId($reservationId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails/' . $reservationId;

        return $this->processRequest($url);
    }

    /**
     * Lists the reservations for a given session Id
     */
    function getReservationsBySessionID($sessionId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails?sessionId=' . $sessionId;

        return $this->processRequest($url);
    }

    /**
     * Lists the reservations with a given order date
     * Takes an option sessionId (for the events system) to limit the reservations to a single session
     */
    function getReservationsByOrderDate($beginDate, $endDate = null, $sessionId = null)
    {
        $beginDate = date("Ymd", strtotime($beginDate));

        if (!empty($endDate)) {
            $endDate = date("Ymd", strtotime($endDate));
        }

        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails?orderDateFrom=' . $beginDate;
        $url .= !empty($endDate) ? '&orderDateTo=' . $endDate : '';
        $url .= !empty($sessionId) ? '&sessionId=' . $sessionId : '';

        return $this->processRequest($url);
    }

    /**
     * Used by the camper queue account level import - this will take an account number and import all reservations made since the day prior
     */
    function getRecentReservationsByAccount($accountId)
    {
        $beginDate = date("Ymd", strtotime('yesterday'));

        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails?orderDateFrom=' . $beginDate;
        $url .= '&accountId=' . $accountId;

        return $this->processRequest($url);
    }

    /**
     * Lists the reservations with a given modification date
     */
    function getReservationsByModificationDate($beginDate, $endDate = null, $accoundId = null)
    {
        $beginDate = date("Ymd", strtotime($beginDate));

        if (!empty($endDate)) {
            $endDate = date("Ymd", strtotime($endDate));
        }

        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails?lastModifiedDateFrom=' . $beginDate;
        $url .= !empty($endDate) ? '&lastModifiedDateTo=' . $endDate : '';
        $url .= !empty($accoundId) ? '&accountId=' . $accoundId : '';

        return $this->processRequest($url);
    }

    /**
     * Lists the reservations for a person, for the current year, when given a person Id
     */
    function getReservationsByPerson($personId)
    {
        // we filter by season, which is the year. Auto-generate the year so that I don't have to remember to change this every January
        $year = date("Y");
        if (!is_int($personId)) {
            $this->logger->error("getReservationsByPerson failed due to invalid person ($personId).");
            return false;
        }

        $url = "https://rest.ultracamp.com/api/camps/107/reservationdetails?seasons=$year%20Season&personId=$personId";

        $this->ucCurl = $this->setupUC();

        return $this->processRequest($url);
    }

    /**
     * Lists the canceled reservations with a given modification date
     */
    function getCanceledReservationById($reservationId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails/' . $reservationId . '/canceled';

        return $this->processRequest($url);
    }

    /**
     * Lists the sessions for a given category
     */
    function getSessionsByCategory($category)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/sessions?category=' . $category;

        return $this->processRequest($url);
    }

    /**
     * Lists the people with a given email address
     */
    function getPeopleByEmail($email)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/people?email=' . $email;

        return $this->processRequest($url);
    }

    /**
     * Lists the people with a given last name
     */
    function getPeopleByLastName($lastname)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/people?lastName=' . $lastname;

        return $this->processRequest($url);
    }

    /**
     * Lists the accounts with a given modification date
     */
    function getAccountsByModifyDate($lastUpdateStartDate, $lastUpdateEndDate = null)
    {
        $lastUpdateStartDate = date("Ymd", strtotime($lastUpdateStartDate));

        if (!empty($lastUpdateEndDate)) {
            $lastUpdateEndDate = date("Ymd", strtotime($lastUpdateEndDate));
        }

        $url = 'https://rest.ultracamp.com/api/camps/107/accounts?lastModifiedDateFrom=' . $lastUpdateStartDate;
        $url .= !empty($lastUpdateEndDate) ? '&lastModifiedDateTo=' . $lastUpdateEndDate : '';

        return $this->processRequest($url);
    }

    /**
     * Get session information
     */
    function getSessionInformation($sessionId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/sessions/' . $sessionId;

        return $this->processRequest($url);
    }

    /**
     * Get session options
     */
    function getSessionOptions($sessionId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/sessions/' . $sessionId . '/options';

        return $this->processRequest($url);
    }

    /**
     * Get transportation options
     */
    function getTransportationOptions($sessionId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/sessions/' . $sessionId . '/transportation';

        return $this->processRequest($url);
    }

    /**
     * Reservation cancellation
     */
    function reservationCancellation($session = NULL, $startdate = NULL, $enddate = NULL)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/reservationdetails/canceled';

        if (!empty($session)) {
            $url .= '?sessionId=' . $session;
        }
        if (!empty($startdate)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'startDate=' . $startdate;
        }
        if (!empty($enddate)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . 'endDate=' . $enddate;
        }

        return $this->processRequest($url);
    }

    /**
     * Get option template
     */
    function getOptionTemplate($templateId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/optiontemplates/' . $templateId;

        return $this->processRequest($url);
    }

    /**
     * Get single option
     */
    function getSingleOption($optionId)
    {
        $url = 'https://rest.ultracamp.com/api/camps/107/options/' . $optionId;

        return $this->processRequest($url);
    }

    // Helper method to add to your class
    public function normalizeUltracampResponse($response)
    {
        // Handle null or empty responses
        if (empty($response)) {
            return [];
        }

        // If it's already an array, return as-is
        if (is_array($response)) {
            return $response;
        }

        // If it's an object, convert to array
        if (is_object($response)) {
            // If it's a stdClass that should be treated as a single item
            if ($response instanceof stdClass) {
                return [$response];
            }

            // If it's some other object type, try to convert to array
            return (array) $response;
        }

        // If it's a JSON string, decode it
        if (is_string($response)) {
            $decoded = json_decode($response, false); // Keep as objects, not associative array
            if (json_last_error() === JSON_ERROR_NONE) {
                // Recursively normalize the decoded result
                return $this->normalizeUltracampResponse($decoded);
            }
        }

        // Fallback: wrap whatever we got in an array
        return [$response];
    }

    /**
     * Set logger
     */
    function setLogger($logger = null)
    {
        if ($logger == null) {
            $this->logger = new UCDummyLogger();
        } else {
            $this->logger = $logger;
        }
    }
}

class UCDummyLogger
{

    function d_bug($arg1, $arg2 = null)
    {
        return true;
    }
    function debug($arg1, $arg2 = null)
    {
        return true;
    }
    function info($arg1, $arg2 = null)
    {
        return true;
    }
    function warning($arg1, $arg2 = null)
    {
        return true;
    }
    function error($arg1, $arg2 = null)
    {
        return true;
    }
}
