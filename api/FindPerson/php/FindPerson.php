<?php

// Class which takes biographical information and attempts to return a matching ultracamp person
// Created by Ben Nyquist, 3/26/2021


// ----- USAGE ------ //
/*

     $finder = new FindPerson;
    
    // Any one element is enough to run a search, but the more data available, the higher the confidence of a match
     $finder->setMailingAddress('4612 SR 92', 'Lake Stevens', 'WA', '98258'); // in order of $city, $state, $zip 
     $finder->setPhoneNumber('425-442-6145'); function removes formatting, so any format is acceptable
     $finder->setEmailAddress('rosark51@gmail.com'); 
     $finder->setName('Allison Nyquist'); any order, no punctuation, seperate names by a space (i.e. first <space> last)

    // optional flag - if set to true, then the system will continue looking for a match, even when a unique element such as email has rendered a match
    // this severly slows down the system creates unnecessary Ultracamp API calls, and should only be needed when troubleshooting
    $finder->continueOnMatch = true;
    
    // enables debug level logging
    $finder->debug = true;

    // Execute Search
     $finder->search();

    // You can manage the weight of a matched element through direct assignment of values to the various variables
     $finder->nameWeight = 25;
     $finder->isPrimaryWeight = -5;

    // RESULTS OF A SEARCH

    echo "Account ID: " .$finder->accountId ."\n";
    echo "Person ID: " .$finder->personId ."\n";
    echo "Person Name: " .$finder->personFirstName ." " .$finder->personLastName ."\n";
    echo "Primary: " . ($finder->isPrimaryContact ? "true" : "false") ."\n";
    echo "Match Confidence: " .$finder->confidence ."\n";
    echo "Match Count: " .$finder->matchCount ."\n"; // This is the number of matches that had the same confidence score as the one returned. Confidence minus match count is a real metric
    echo "Match Explanation: "; // (array)
     foreach($finder->explanation as $a) {
          echo "\n\t";
          echo $a;
     }


     $finder->fullMatch is the Ultracamp JSON for the matched person
*/


class FindPerson
{

    // the reservations database
    protected $db;

    // the ultracamp library
    protected $uc;

    // logger variables
    public $debug;
    public $logger;

    // biographical variables
    protected $email, $phone, $name, $address, $city, $state, $zip, $account;

    // configuration variables
    public $findPrimaryContact = false;
    public $continueOnMatch = false; // if false, then when a likley match is found, searching stops. If true, we'll search every variable, regardless of likliehood that we've already found the match
    public $skip_ultracamp_lookup = false; // if true, we'll trust our database to lookup the name and not query Ultracamp for a search - only applies to name only searches
    public $years_back_to_look = 100; // if set to a number, we'll only consider campers who have attended camp this many years into the past. This helps reduce duplicate matches

    // weight values for match types
    public $emailWeight     = 50;
    public $phoneWeight     = 50;
    public $addressWeight   = 50;
    public $accountWeight   = 75;
    public $nameWeight      = 15;
    public $cityWeight      = 5;
    public $stateWeight     = 5;
    public $zipWeight       = 10;
    public $isPrimaryWeight = 5;

    // results
    public $accountId, $personId, $personFirstName, $personLastName, $isPrimaryContact;
    public $confidence, $explanation, $fullMatch, $matchCount;

    function __construct($extLogger = false)
    {

        $this->setLogger($extLogger);

        require_once __DIR__ . '/../../../db/db-reservations.php';
        $this->db = new reservationsDb($this->logger);

        require_once __DIR__ . '/../../../includes/ultracamp.php';
        $this->uc = new UltracampModel($this->logger);

        // $this->logger->debug(" ");
        // $this->logger->debug("--  findAccount() has been initialized -- -");
    }

    function enableDebug()
    {
        $this->debug = true;
        // $this->logger->pushHandler($dbugStream);
    }

    /**
     * Allows outside callers to set their own logger - this became my standard practice after friend finder was built
     * I've included this here so that this can support it as Camper Queue is using this class
     */
    // if the logger is coming in, use it, otherwise create a dummy logger so we don't error out when we attempt to call it
    function setLogger($logger = null)
    {

        if (is_object($logger)) {
            $this->logger = $logger;
            return true;
        }

        // require_once(__DIR__ . '/../../logger/logger.php');
        $this->logger = $logger;

        //failsafe 
        if (!is_object($this->logger)) {
            $this->logger = new FriendManagerDummyLogger();
        }
    }


    // sets the email address, returns the filtered address
    function setEmailAddress($email)
    {

        $this->email = FILTER_VAR($email, FILTER_VALIDATE_EMAIL);
        // $this->logger->d_bug("Email Address was set", $this->email);
        return $this->email;
    }

    // sets the account number
    function setAccount($account)
    {

        $this->account = FILTER_VAR($account, FILTER_SANITIZE_NUMBER_INT);
        // $this->logger->d_bug("Account Number Address was set", $this->account);
        return $this->account;
    }

    // filters and sets a mailing address
    function setMailingAddress($address, $city, $state, $zip)
    {

        $this->address = FILTER_VAR($address, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->city = FILTER_VAR($city, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->state = FILTER_VAR($state, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->zip = FILTER_VAR($zip, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // $this->logger->d_bug("Address has been set", $this->address);
        // $this->logger->d_bug("City has been set", $this->city);
        // $this->logger->d_bug("State has been set", $this->state);
        // $this->logger->d_bug("Zip has been set", $this->zip);
    }

    // stores the name
    function setName($name)
    {

        $this->name = FILTER_VAR($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        // $this->logger->d_bug("Name has been set", $this->name);

        return $this->name;
    }

    // set's the phone number, returns filtered result, or false if invalid
    function setPhoneNumber($phone)
    {

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            //Phone is 10 characters in length (###) ###-####
            $this->phone = $phone;
            // $this->logger->d_bug("Phone has been set", $this->phone);

            return $phone;
        }

        return false;
    }

    function search()
    {

        $result = array('confidence' => 0, 'matchCount' => 0, 'additionalMatches' => array());
        $currentPerson = null;

        // if there is an account number, start with that!
        if ($this->account) {
            // $this->logger->d_bug("Looking up people by account number", $this->account);
            $accountResult = $this->ultracampPersonSearch('accountNumber', $this->account);

            if (is_array($accountResult)) {
                // $this->logger->d_bug(count($accountResult) . " people found with this email address.");
                foreach ($accountResult as $match) {
                    $test = $this->getConfidence($match);
                    if ($test['confidence'] > $result['confidence']) {
                        // $this->logger->d_bug("Promoting a match to the likely candidate.", $test);
                        $test['matchCount'] = 1;
                        $result = $test;
                        $currentPerson = $match;
                    } else if ($test['confidence'] == $result['confidence']) {
                        // if we have an identical confidence level, note the count
                        $result['matchCount'] += 1;
                        $result['additionalMatches'][] = $test['personId'];
                    }
                }
            }
        }

        // if there is a match here, then we've probably got our person
        if (!$this->continueOnMatch && $result['confidence'] > $this->accountWeight) {
            $this->setResult($result, $currentPerson);
            return $result;
        }

        // search ultracamp for all PEOPLE that have this email address
        // this will certainly bring back multiple people within the same account if there is a match
        if ($this->email) {
            // $this->logger->d_bug("Looking up people by email address", $this->email);
            $emailResult = $this->ultracampPersonSearch('email', $this->email);

            if (is_array($emailResult)) {
                // $this->logger->d_bug(count($emailResult) . " people found with this email address.");
                foreach ($emailResult as $match) {
                    $test = $this->getConfidence($match);
                    if ($test['confidence'] > $result['confidence']) {
                        // $this->logger->d_bug("Promoting a match to the likely candidate.", $test);
                        $test['matchCount'] = 1;
                        $result = $test;
                        $currentPerson = $match;
                    } else if ($test['confidence'] == $result['confidence']) {
                        // if we have an identical confidence level, note the count
                        $result['matchCount'] += 1;
                        $result['additionalMatches'][] = $test['personId'];
                    }
                }
            }
        }

        // if there is a match here, then we've probably got our person
        if (!$this->continueOnMatch && $result['confidence'] > $this->emailWeight) {
            $this->setResult($result, $currentPerson);
            return $result;
        }

        // the next most unique item is a phone number, so let's go there
        if ($this->phone) {
            // $this->logger->d_bug("Looking up people by phone number", $this->phone);
            $phoneResult = $this->ultracampPersonSearch('phoneNumber', $this->phone);

            if (is_array($phoneResult)) {
                // $this->logger->d_bug(count($phoneResult) . " people found with this phone number.");
                foreach ($phoneResult as $match) {
                    $test = $this->getConfidence($match);
                    if ($test['confidence'] > $result['confidence']) {
                        // $this->logger->d_bug("Promoting a match to the likely candidate.", $test);
                        $test['matchCount'] = 1;
                        $result = $test;
                        $currentPerson = $match;
                    } else if ($test['confidence'] == $result['confidence']) {
                        // if we have an identical confidence level, note the count
                        $result['matchCount'] += 1;
                        $result['additionalMatches'][] = $test['personId'];
                    }
                }
            }
        }

        // if there is a match here, then we've probably got our person
        if (!$this->continueOnMatch && $result['confidence'] > $this->phoneWeight) {
            $this->setResult($result, $currentPerson);
            return $result;
        }


        // next, check by name
        // since we don't ask the user to differentiate first name and last name, we have to guess. 
        // We're going to split the name apart on the space character
        $names = explode(" ", trim($this->name));

        // We're going make sure the array is only three values long, just in case someone types something stupid
        $names = array_slice($names, -3);

        // assume that the last entry is the last name
        $names = array_reverse($names);

        // we'll keep checking names until we get a hit or run out of names to try
        // $this->logger->d_bug("Looking for match by name.", $names);

        foreach ($names as $n) {
            // ensure that we aren't searching for a middle initial or something
            // also skips when there are no names supplied
            if (strlen($n) < 3) {
                // $this->logger->d_bug("Skipping the name $n as it is too short.");
                continue;
            }

            // We first check our database, it is much faster.
            // The database doesn't hold all bio information, so if we are checking certain elements for a match, the DB cannot help
            // therefore, only check if we're not using those elements as criteria for a match
            if (!$this->phone && !$this->email && !$this->address) {

                $matches = $this->databasePersonSearch($n);
            }

            // if there is are matches, then check the confidence level of each of the matches
            if (isset($matches) && is_array($matches)) {
                // $this->logger->d_bug("Searching the database by name $n resulted in a match with " . count($matches) . " result(s).");

                // we can get a whole host of results back. We need to find the right one
                // check each one against the confidence matrix and keep the highest score
                foreach ($matches as $match) {
                    $test = $this->getConfidence($match);
                    // if this result has a higher score the the one we're holding on to, use this as the current candidate
                    if ($test['confidence'] > $result['confidence']) {
                        // $this->logger->d_bug("Promoting a match to the likely candidate.", $test);
                        $test['matchCount'] = 1;
                        $result = $test;
                        $currentPerson = $match;
                    } else if ($test['confidence'] == $result['confidence']) {
                        // if we have an identical confidence level, note the count
                        $result['matchCount'] += 1;
                        $result['additionalMatches'][] = $test['personId'];
                    }
                }

                // don't test more names if the name we found looks good (due to likely finding the correct last name)
                if ($result['confidence'] > $this->nameWeight && !$this->continueOnMatch) {
                    // $this->logger->d_bug("Break conditions for name search: confidence: " . $result['confidence'] . " nameWeight: " . $this->nameWeight);
                    // $this->logger->d_bug("We're not looking for more names, the ones we found seem right.", $result);

                    $this->setResult($result, $currentPerson);
                    return $result;
                }
            }

            // added 3/14/23 to allow friend finder to rely only on the database
            // our customer informaiton is now large enough that Ultracamp won't help much
            if (!$this->skip_ultracamp_lookup) {
                // to be here means that the database didn't turn up anything
                // take the name in the array and check it against Ultracamp
                $matches = $this->ultracampPersonSearch('lastName', $n);

                // if there is are matches, then check the confidence level of each of the matches
                if (isset($matches) && is_array($matches)) {
                    // $this->logger->d_bug("Searching by name $n resulted in a match with " . count($matches) . " result(s).");

                    // we can get a whole host of results back. We need to find the right one
                    // check each one against the confidence matrix and keep the highest score
                    foreach ($matches as $match) {
                        $test = $this->getConfidence($match);
                        // if this result has a higher score the the one we're holding on to, use this as the current candidate
                        if ($test['confidence'] > $result['confidence']) {
                            // $this->logger->d_bug("Promoting a match to the likely candidate.", $test);
                            $test['matchCount'] = 1;
                            $result = $test;
                            $currentPerson = $match;
                        } else if ($test['confidence'] == $result['confidence']) {
                            // if we have an identical confidence level, note the count
                            $result['matchCount'] += 1;
                            $result['additionalMatches'][] = $test['personId'];
                        }
                    }

                    // don't test more names if the name we found looks good (due to likely finding the correct last name)
                    if ($result['confidence'] > $this->nameWeight && !$this->continueOnMatch) {
                        // $this->logger->d_bug("Break conditions for name search: confidence: " . $result['confidence'] . " nameWeight: " . $this->nameWeight);
                        // $this->logger->d_bug("We're not looking for more names, the ones we found seem right.", $result);
                        $this->setResult($result, $currentPerson);
                        return $result;
                    }
                }
            }
        }

        if (!isset($currentPerson)) {
            $this->setResult($result);
        } else {
            $this->setResult($result, $currentPerson);
        }

        return $result;
    }

    private function setResult($result, $currentPerson = null)
    {

        // I don't remember why this function exists - BN 3/14/23
        // This is why you document and comment EVERYTHING
        // $this->logger->d_bug("Set Result", array($result, $currentPerson));

        if (!isset($result) || count($result) == 0) {
            $this->logger->error("setResult was called without a result set loaded.");
            // $this->logger->d_bug("Current Person", $currentPerson);
            // $this->logger->d_bug("Result", $result);
            return false;
        }

        if ((!isset($currentPerson) || count($currentPerson) == 0) && $result['confidence'] > 0) {
            $this->logger->error("setResult was called without a currentPerson loaded.");
            // $this->logger->d_bug("Result", $result);
            // $this->logger->d_bug("Current Person", $currentPerson);
            return false;
        }

        // we have a match, and current person was passed in
        // without this check, on no match, we get undefined index warnings
        if (isset($currentPerson) && count($currentPerson) > 0) {

            // if the current person comes from our database, then it won't have all of the informaiton that we need
            // check for a short response (currently the database only returns 7 values, Ultracamp, 38), and query UC for the full details if needed
            if (count($currentPerson) < 15) {
                // $this->logger->d_bug("Looking up person with Ultracamp.");
                $ucPerson = $this->getUltracampPerson($currentPerson['Id']);

                if (!empty($ucPerson['StatusCode']) && $ucPerson['StatusCode'] == '404') {
                    $this->logger->warning("Ultracamp cannot find a person with the id " . $currentPerson['personId']);
                    $ucPerson = null;
                }

                $currentPerson = ($ucPerson ? $ucPerson : $currentPerson);
            }

            if (!$currentPerson['AccountId']) {
                $this->logger->warning("Something broke: ", $currentPerson);
            }
            // $this->logger->d_bug("Current Person", $currentPerson);

            $this->fullMatch = $currentPerson;
            $this->accountId = $currentPerson['AccountId'];
            $this->personId = $currentPerson['Id'];
            $this->personFirstName = $currentPerson['FirstName'];
            $this->personLastName = $currentPerson['LastName'];
            $this->isPrimaryContact = $currentPerson['PrimaryContact'];
            $this->explanation = $result['explanation'];
        }

        $this->confidence = $result['confidence'];
        $this->additionalMatches = isset($result['additionalMatches']) ? array_unique($result['additionalMatches']) : NULL;
        if (is_array($this->additionalMatches)) {
            $this->matchCount = count($this->additionalMatches) + 1;
        } else {
            $this->matchCount = 1;
        }

        // $this->logger->d_bug("Exiting Person Search with a match confidence of " . $this->confidence);
    }

    private function getConfidence($person)
    {
        // Things in the UC account that we're looking at
        /*
            $person['AccountId'];
            $person['FirstName'];
            $person['LastName'];
            $person['Email'];
            $person['PrimaryContact'];
            $person['SecondaryContact'];
            $person['phoneNumber'];
        */

        $confidence = 0;
        $explanation = array();

        // Ensure that we're being handed a valid record
        if (empty($person['AccountId'])) {
            $this->logger->warning("A person was passed to getConfidence, but is not a valid Ultracamp record.");
            // $this->logger->d_bug("Invalid Account", $person);
            return array();
        }

        // $this->logger->d_bug("-- Checking Match Confidence for person " . $person['FirstName'] . " " . $person['LastName'] . " with account number " . $person['AccountId'] . " --");

        // if an account number is passed in, we really just need to look within the account for a person that matches that name
        if ($this->account) {
            // $this->logger->d_bug("Checking account number", array($this->account, $person['AccountId']));
            if ($this->account == $person['AccountId']) {
                $confidence += $this->accountWeight;
                // $this->logger->d_bug("Account Number Matched, confidence +" . $this->accountWeight);
                $explanation[] = "( " . $this->accountWeight . ") An account number was entered in the query and found in Ultracamp.";
            }
        }

        // we REALLY like when we find an email address that matches
        // don't try if there isn't an address, or it'll match on NULL in ultrcamp too
        if ($this->email) {
            // $this->logger->d_bug("Checking email addresses", array($this->email, $person['Email']));
            if (strtolower(trim($this->email)) == strtolower(trim($person['Email']))) {
                $confidence += $this->emailWeight;
                // $this->logger->d_bug("Email Account Returned, confidence +" . $this->emailWeight);
                $explanation[] = "(" . $this->emailWeight . ") The email address entered matches this Ultracamp record";
            }
        }

        // check the address (we really like this match too!)
        // to increase the liklihood of a correct match, we're stripping all punctuation from the strings, converting everything to lower, and only looking at the first 10 characters.
        if ($this->address) {
            $pattern = '/[^A-Za-z0-9[:space:]]/';

            $actAdd1 = strtolower(substr(preg_replace($pattern, "", $person['Address']), 0, 10));
            $frmAdd1 = strtolower(substr(preg_replace($pattern, "", $this->address), 0, 10));

            // $this->logger->d_bug("Comparing Addresses", array($actAdd1, $frmAdd1));

            if ($actAdd1 == $frmAdd1) {
                $confidence += $this->addressWeight;
                // $this->logger->d_bug("Address Matches: +" . $this->addressWeight);
                $explanation[] = "(" . $this->addressWeight . ") The entered address matches the person.";
            }
        }


        // the final unique(ish) value is the phone number. It'll get high marks for confidance too
        if ($this->phone) {
            // the match can have up to 3 phone numbers, we'll check them each, but count it a match when we hit on any one of them
            // this is because often the home phone and cell phone are the same value. Since we don't care what position the phone number
            // is in, we're happy to simply identify that it is associated with this person

            // ultracamp stores formatted phone numbers, so we'll strip out the non-digit characters
            $phone1 = preg_replace('/[^0-9]/', '', $person['PrimaryPhoneNumber']);
            $phone2 = preg_replace('/[^0-9]/', '', $person['FirstAlternatePhoneNumber']);
            $phone3 = preg_replace('/[^0-9]/', '', $person['SecondAlternatePhoneNumber']);

            // $this->logger->d_bug("Comparing Phone Numbers with " . $this->phone, array($phone1, $phone2, $phone3));

            if ($phone1 == $this->phone || $phone2 == $this->phone || $phone3 == $this->phone) {
                // $this->logger->d_bug("A phone number match was found: +" . $this->phoneWeight);
                $explanation[] = "(" . $this->phoneWeight . ") The entered phone number matches the person.";
                $confidence += $this->phoneWeight;
            }
        }

        // check to see if the name entered on the form matches the one on the account
        if ($this->name) {
            $nameCheck = $this->compareNames($this->name, $person['FirstName'], $person['LastName']);
            // $this->logger->d_bug("Name Check is +" . $this->nameWeight . " for each name match", $nameCheck);

            // for each matched name, increase the confidence of a match
            foreach ($nameCheck as $n) {
                if ($n) {
                    $confidence += $this->nameWeight;
                    $explanation[] = "(" . $this->nameWeight . ") A name (first or last) entered matches this Ultracamp person.";
                }
            }
        }

        // if the provided account shows the users is the primary contact
        // this can weed out kids when searching for an account
        // the confidence boost is low, so a name match will override
        // however, without this, then it is a toss up which person comes back with an account level search (i.e. only email)
        if (isset($person['PrimaryContact']) && $person['PrimaryContact'] == "true") {
            $confidence += $this->isPrimaryWeight;
            // $this->logger->d_bug("Primary Contact: +" . $this->isPrimaryWeight);
            $explanation[] = "(" . $this->isPrimaryWeight . ") The match belongs to the primary contact to the person.";
        }

        // check the state (only a minor confidence boost)
        if ($this->state && strtolower($person['State']) == strtolower($this->state)) {
            $confidence += $this->stateWeight;
            // $this->logger->d_bug("Address State Matches: +" . $this->stateWeight);
            $explanation[] = "(" . $this->stateWeight . ") The entered state matches the person.";
        }

        // check the city 
        if ($this->city && trim(strtolower($person['City'])) == trim(strtolower($this->city))) {
            $confidence += $this->cityWeight;
            // $this->logger->d_bug("Address City Matches: +" . $this->cityWeight);
            $explanation[] = "(" . $this->cityWeight . ") The entered city matches the person.";
        }

        // check the zip (only a minor confidence boost) (substr to only look at first 5 digits)
        if ($this->zip && substr($person['ZipCode'], 0, 5) == substr($this->zip, 0, 5)) {
            $confidence += $this->zipWeight;
            // $this->logger->d_bug("Address Zip Code Matches: +" . $this->zipWeight);
            $explanation[] = "(" . $this->zipWeight . ") The entered zipcode matches the person.";
        }

        return array('personId' => $person['Id'], 'confidence' => $confidence, 'explanation' => $explanation);
    }

    function ultracampPersonSearch($type, $query)
    {

        // Search Ultracamp for people base on various criteria

        // $type matches the ultracamp parameters
        // address, lastName, firstName, nickname, accountNumber, address, city, postalCode, stateProv, country, phoneNumber, email, grade

        // replace spaces with %20, as well as other special characters
        $query = rawurlencode($query);

        $curl = curl_init();
        $url = "https://rest.ultracamp.com/api/camps/107/people?" . $type . "=" . $query;

        // $this->logger->d_bug("Querying Ultracamp", $url);

        // Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "107:7EJWNDKAMG496K9Q");

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        // Ultracamp error on 6/21/22 created cert issues - to resolve, I'm skipping the check
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);


        try {
            $result = curl_exec($curl);
        } catch (Exception $e) {
            $this->logger->error("Unable to lookup a user in Ultracamp by email.");
            $this->logger->error($e->getMessage());
        }

        // Capture any returned error
        if (curl_errno($curl)) {
            $error_message = (curl_strerror(curl_errno($curl)) ? curl_strerror(curl_errno($curl)) : "Ultracamp did not respond to the api request");
            $this->logger->error("CURL Error when attempting to load ultracamp user by email", array("error_status" => "cURL error: {$error_message}"));
        }

        curl_close($curl);

        // return the API response
        return json_decode($result, true);
    }

    // checks our campers database for a match against names. 
    // if there is a value within our recorded history in $this->years_back_to_look, we'll only consider campers with registration counts within that window
    function databasePersonSearch($name)
    {

        // $this->logger->d_bug("Searching for last name $name in our database.");

        // our database goes back to 2018 - check how many years ago that was
        $maxYearsBack = date('Y') - 2018;
        $whereSuffix = '';

        // check to see if years_back_to_look is less than that
        if ($this->years_back_to_look < $maxYearsBack) {
            // it is, so we'll add some WHERE clauses on the SQL
            for ($i = 0; $i <= $this->years_back_to_look; $i++) {
                // prod likes to complain about this being non-numeric. Localhost doesn't care - anyway, forcing the issue
                $curYear = (int)(date('Y') - $i);

                if (!is_numeric($curYear)) {
                    // $this->logger->d_bug("Current Year is showing as non-numeric: $curYear");
                }

                $whereSuffix .= ($i == 0) ? ' AND (' : ' OR ';
                $whereSuffix .= "count_$curYear > 0";
            }

            $whereSuffix .= ')';
        }

        $sql = "SELECT 
                      first_name AS 'FirstName'
                    , last_name AS 'LastName'
                    , person_ucid AS 'Id'
                    , account_ucid AS 'AccountId'
                    , city AS 'City'
                    , state AS 'State'
                    , zip AS 'ZipCode'
                FROM 
                    campers 
                WHERE 
                    last_name = ?
                OR  first_name = ?"
            . $whereSuffix;

        $this->logger->d_bug("Database name Lookup: $sql -- $name");

        try {
            $result = $this->db->runQuery($sql, 'ss', array($name, $name));
        } catch (Exception $e) {
            $this->logger->error("Unable to query the database for a camper with the last name $name: " . $e->getMessage());
        }

        return (empty($result)) ? false : $result;
    }

    // takes two names (first and last) and compares them to the full name in the database
    // tries both frontwards and backwards, so the order of the names is irelevent
    function compareNames($template, $name1, $name2)
    {

        $names = explode(' ', strtolower($template));
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));

        // $this->logger->d_bug("Comparing Names $name1 and $name2", $names);

        $result = array('name1' => false, 'name2' => false);

        foreach ($names as $name) {
            $name = trim($name);

            if ($name == $name1) {
                $result['name1'] = true;
                // $this->logger->d_bug("Name: $name matches Name1: $name1");
                continue;
            }

            if ($name == $name2) {
                $result['name2'] = true;
                // $this->logger->d_bug("Name: $name matches Name2: $name2");
                continue;
            }
        }

        return $result;
    }

    // load the ultracamp data for a person by their person Id 
    function getUltracampPerson($Id)
    {

        if (!filter_var($Id, FILTER_VALIDATE_INT)) {
            return false;
        }

        //let's stick with the Ultracamp API library, shall we? (3/14/23 - BN)
        $result = $this->uc->getPersonById($Id);
        return (array) $result; // api returns a class, i'm expecting an array - convert it

        /*
        if(!preg_match('/^[1-9][0-9]{0,15}$/', $Id)) {
            return false;
        }

        $curl = curl_init();
        $url = "https://rest.ultracamp.com/api/camps/107/people/" .$Id;
        
        // $this->logger->d_bug("Querying Ultracamp", $url);

        // Authentication:
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, "107:7EJWNDKAMG496K9Q");

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        
        try {
            $result = curl_exec($curl);
        } catch(Exception $e) {
            $this->logger->error("Unable to get a person from Ultracamp by Person Id.");
            $this->logger->error($e->getMessage());
        }
        
        // Capture any returned error
        if(curl_errno($curl)) {
            $error_message = (curl_strerror(curl_errno($curl)) ? curl_strerror(curl_errno($curl)) : "Ultracamp did not respond to the api request") ;
            $this->logger->error("CURL Error when attempting to load ultracamp person by Id", array("error_status" => "cURL error: {$error_message}"));
        }

        curl_close($curl);

        // return the API response
        return json_decode($result,true);
        */
    }
}

class FindPersonDummyLogger
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
