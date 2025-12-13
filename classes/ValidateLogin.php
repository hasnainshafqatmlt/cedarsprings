<?php


date_default_timezone_set('America/Los_Angeles');

class ValidateLogin
{

    public $logger;
    protected $uc;
    protected $cart;

    function __construct($logger = null)
    {
        $this->setLogger($logger);

        require_once 'UltracampCart.php';

        $this->cart = new UltracampCart($this->logger);
    }

    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        $this->logger = new ValidateLoginLogger();
    }

    // take an account API key and ensure it's valid
    function validate($key, $account)
    {

        $key = FILTER_VAR($key, FILTER_SANITIZE_STRING);
        $account = FILTER_VAR($account, FILTER_SANITIZE_STRING);

        if (!isset($key) || !isset($account)) {
            $this->logger->error("Missing API key ($key) or account number ($account) in the validate login process.");
            return false;
        }

        //$this->logger->d_bug("Validating key / account", array($key, $account));

        try {
            $result = $this->cart->validateAccountKey($key, $account);
        } catch (Exception $e) {
            $this->logger->error('Unable to validate the api key: ' . $e->getMessage());
            $this->logger->error("UC Response: ", $result);
            return false;
        }

        if (isset($result['Authenticated']) && $result['Authenticated'] == true) {
            return true;
        }

        $this->logger->d_bug("Failed to validate the ultracamp API Key", $result);
        return false;
    }

    /**
     * Ensures that a person is a member of an account - prevents login with legit account and waitlist any camper, regardless of account, by form manipulation
     */
    function validateCamper($account, $camper)
    {

        // UC doesn't validate that I sent in a person, and seems to want to return our entire database
        if (empty($camper) || !is_numeric($camper)) {
            $this->logger->error("No camper ID was provided in validateCamper().");
            return false;
        }

        if (empty($account) || !is_numeric($account)) {
            $this->logger->error("No account ID was provided in validateCamper().");
            return false;
        }

        $person = $this->cart->getPersonById($camper);
        if ($person->AccountId != $account) {
            echo json_encode(array('Authenticated' => false));
            return false;
        }

        return true;
    }

    // takes the full list of entries, and validates the campers are all member of the account
    // this is it's own function to only check each camper ID once, even if included in the array many times
    // returns bool if all people in the array are members of the account
    function validateCampers($account, $entries)
    {
        $people = array();

        foreach ($entries as $entry) {
            $e = explode('-', $entry);
            // ensure that we've not checked this camper yet
            if (in_array($e[1], $people)) {
                continue;
            }

            $this->logger->d_bug("Testing Camper " . $e[1] . " against account " . $account . ".");
            if ($this->validateCamper($account, $e[1])) {
                $people[] = $e[1]; // save the camper to the list of those checked so we don't check them again
            } else {
                $this->logger->error("Camper " . $e[1] . " was found to not be associated with account " . $account . ".");
                return false; // if the check fails, stop checking more and just fail the test
            }
        }

        $this->logger->d_bug("Tested " . count($people) . " people in " . count($entries) . " entries - all are account members.");
        return true; // they all passed
    }
}



class ValidateLoginLogger
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
        throw new Exception($arg1);
        return true;
    }
}
