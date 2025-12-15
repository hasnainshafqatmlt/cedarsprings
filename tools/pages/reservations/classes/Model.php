<?php

//Built November 2020 by Ben Nyquist

date_default_timezone_set('America/Los_Angeles');

require_once(__DIR__ . '/../../../../db/conn.php');
require_once(__DIR__ . '/../../../../db/db-reservations.php');

class BaseModel
{
	protected $db;
	public $logger;

	public $reservationId;
	public $reservationUCID;
	public $personUCID;
	public $accountUCID;
	public $sessionUCID;
	public $sessionName;
	public $weekNumber;
	public $campName;
	public $transportation;
	public $busRider;
	public $lunchQty;
	public $amExtCare;
	public $pmExtCare;
	public $pod;
	public $orderDate;
	public $revenue;
	public $discounts;


	public function __construct($logger = null)
	{
		$this->setLogger($logger);

		$this->db = new reservationsDb($logger);
	}

	function setLogger($logger)
	{
		if (is_object($logger)) {
			$this->logger = $logger;
		} else {
			$this->logger = new DummyLogger();
		}

		return true;
	}

	// create a debug log entry - old methodology, now just maps into the logger class
	function d_bug($message, $data = null)
	{

		if (!is_object($this->logger))
			return false;

		$this->logger->d_bug($message, $data);

		return true;
	}

	/**
	 * Gets session information from the database. This replaces the old manually typed array.
	 */
	function getSeasonSessions()
	{
		$sql = "SELECT session_id as ucid, short_name as name FROM summer_weeks ORDER by week_num";
		try {
			$sessions = $this->db->runBaseQuery($sql);
		} catch (Exception $e) {
			$this->logger->error("Unable to retrieve the session list from the database in the reservations Model: " . $e->getMessage());
			return false;
		}

		return $sessions;
	}


	function processBasicInfo($reservation)
	{

		$this->reservationUCID 	= $reservation->ReservationId;
		$this->personUCID 		= $reservation->PersonId;
		$this->accountUCID 		= $reservation->AccountId;
		$this->sessionUCID 		= $reservation->SessionId;
		$this->sessionName		= $reservation->SessionName;
		$this->orderDate 		= date("Y-m-d H:i:s", strtotime($reservation->OrderDate) - 60 * 60 * 3); // adjust order date to use pacific time instead of the reported eastern time
		$this->revenue			= $reservation->OrderTotal;
		$this->discounts		= $this->getDiscounts($reservation->FeeDetails);
	}

	function getDiscounts($feeDetails)
	{

		$res = 0;

		foreach ($feeDetails as $f) {
			if (strpos($f, 'Discounts') !== false) {
				$res = preg_replace("/[^0-9.]/", "", $f);
			}
		}

		return $res;
	}

	function storeUser($reservation)
	{


		if ($reservation->PersonId == "") //something is very wrong
		{
			throw new Exception("There is no person ID included in the call to create user.");
			//var_dump($reservation);
			return false;
		}

		//check to ensure that the user doesn't already exist
		$person = $this->findPerson($reservation->PersonId);

		if ($person > 0) {
			// $this->d_bug("User was already found.", $person);

			// check to see if we need to update the database
			$this->updatePerson($reservation);

			return $person['camper_id']; // we already have this user			
		}

		// Sometime in March 2023 - Ultracamp started to return full state names instead of abbreviations. Probably related to camper queue and using the cart
		// We need to look out for that as the DB stores a two character abbreviation for users
		$camper_state = (strlen($reservation->PersonState) > 2) ?  $this->abbreviate_state($reservation->PersonState) : $reservation->PersonState;

		if ($camper_state == '') {
			$this->logger->warning("No state was provided to the reservations model for account " . $reservation->AccountId . ". String Provided: " . $reservation->PersonState);
		}

		$sql = "INSERT INTO campers (person_ucid, account_ucid, first_name, last_name, city, state, zip, gender, birth_date) VALUES (?,?,?,?,?,?,?,?,?)";
		$parameters = array(
			$reservation->PersonId,
			$reservation->AccountId,
			iconv('ISO-8859-1', 'ASCII//TRANSLIT', $reservation->FirstName),
			iconv('ISO-8859-1', 'ASCII//TRANSLIT', $reservation->LastName),
			$reservation->PersonCity,
			$camper_state,
			$reservation->PersonZip,
			$reservation->Gender,
			date("Y-m-d", strtotime($reservation->BirthDate))
		);
		try {
			$result = $this->db->insert($sql, 'iisssssss', $parameters);
		} catch (Exception $e) {
			throw new Exception("Something broke when trying to insert the person record into the database. " . $e->getMessage(), 0, $e);
		}

		if ($result < 1) {
			$this->logger->error("SQL did not complete the camper insert.");
			$this->logger->error($sql, $parameters);
		}


		return $result;
	}

	function findPerson($personUcid)
	{
		//check to ensure that the user doesn't already exist
		$sql = "SELECT camper_id FROM campers WHERE person_ucid = ?";
		try {
			$result = $this->db->runQuery($sql, 'i', array($personUcid));
		} catch (Exception $e) {
			throw new Exception("Unable to query the database to find a person.", 0, $e);
			return false;
		}

		if (isset($result)) {
			return $result[0];
		}

		return null;
	}

	// loops through our information on the camper and updates anything that doesn't match ultracamp
	function updatePerson($reservation)
	{
		if (!isset($reservation)) {
			return false;
		}

		$personSql = "SELECT person_ucid, first_name, last_name, city, state, zip, birth_date, gender FROM campers WHERE person_ucid = ?";
		try {
			$result = $this->db->runQuery($personSql, 'i', array($reservation->PersonId));
		} catch (Exception $e) {
			throw new Exception("Unable to retrieve the person record from campers for person_ucid " . $reservation->PersonId . ". " . $e->getMessage(), 0, $e);
			return false;
		}

		if (!isset($result) || count($result) == 0) {
			return false;
		}

		$person = $result[0];
		$person_ucid = $person['person_ucid'];

		if (!isset($person_ucid)) {
			return false;
		}

		if ($person['first_name'] != $reservation->FirstName) {
			$sql = 'UPDATE campers SET first_name = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($reservation->FirstName, $person_ucid));
				$this->d_bug("Updating first name for camper $person_ucid from " . $person['first_name'] . " to " . $reservation->FirstName);
			} catch (Exception $e) {
				throw new Exception("Unable to update the first name of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		if ($person['last_name'] != $reservation->LastName) {
			$sql = 'UPDATE campers SET last_name = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($reservation->LastName, $person_ucid));
				$this->d_bug("Updating last name for camper $person_ucid from " . $person['last_name'] . " to " . $reservation->LastName);
			} catch (Exception $e) {
				throw new Exception("Unable to update the last name of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		if ($person['city'] != $reservation->PersonCity) {
			$sql = 'UPDATE campers SET city = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($reservation->PersonCity, $person_ucid));
				$this->d_bug("Updating city for camper $person_ucid from " . $person['city'] . " to " . $reservation->PersonCity);
			} catch (Exception $e) {
				throw new Exception("Unable to update the city of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		// Sometime in March 2023 - Ultracamp started to return full state names instead of abbreviations. Probably related to camper queue and using the cart
		// We need to look out for that as the DB stores a two character abbreviation for users
		$camper_state = (strlen($reservation->PersonState) > 2) ?  $this->abbreviate_state($reservation->PersonState) : $reservation->PersonState;

		if ($person['state'] != $camper_state) {
			$sql = 'UPDATE campers SET state = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($camper_state, $person_ucid));
				$this->d_bug("Updating state for camper $person_ucid from " . $person['state'] . " to " . $camper_state);
			} catch (Exception $e) {
				throw new Exception("Unable to update the state of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		if ($person['zip'] != $reservation->PersonZip) {
			$sql = 'UPDATE campers SET zip = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($reservation->PersonZip, $person_ucid));
				$this->d_bug("Updating zip for camper $person_ucid from " . $person['zip'] . " to " . $reservation->PersonZip);
			} catch (Exception $e) {
				throw new Exception("Unable to update the zip for person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		if ($person['gender'] != $reservation->Gender) {
			$sql = 'UPDATE campers SET gender = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array($reservation->Gender, $person_ucid));
				$this->d_bug("Updating gender for camper $person_ucid from " . $person['gender'] . " to " . $reservation->Gender);
			} catch (Exception $e) {
				throw new Exception("Unable to update the gender of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		if ($person['birth_date'] != date("Y-m-d", strtotime($reservation->BirthDate))) {
			$sql = 'UPDATE campers SET birth_date = ? WHERE person_ucid = ?';
			try {
				$this->db->update($sql, 'si', array(date("Y-m-d", strtotime($reservation->BirthDate)), $person_ucid));
				$this->d_bug("Updating birthdate for camper $person_ucid from " . $person['birth_date'] . " to " . date("Y-m-d", strtotime($reservation->BirthDate)));
			} catch (Exception $e) {
				throw new Exception("Unable to update the birthdate of person " . $person_ucid . ". " . $e->getMessage(), 0, $e);
			}
		}

		return true;
	}

	/* -----------------------------------
	* Takes a state name and returns the abbreviation
	* List from https://awesometoast.com/php-state-names/
	* The code there was trash though, so I neutered the function (it had 5 lines of code and errors in 3 of them)
	* ----------------------------------- */
	function abbreviate_state($name)
	{
		$us_state_abbrevs_names = array(
			'WA' => 'WASHINGTON',
			'AL' => 'ALABAMA',
			'AK' => 'ALASKA',
			'AZ' => 'ARIZONA',
			'AR' => 'ARKANSAS',
			'CA' => 'CALIFORNIA',
			'CO' => 'COLORADO',
			'CT' => 'CONNECTICUT',
			'DE' => 'DELAWARE',
			'DC' => 'DISTRICT OF COLUMBIA',
			'FL' => 'FLORIDA',
			'GA' => 'GEORGIA',
			'HI' => 'HAWAII',
			'ID' => 'IDAHO',
			'IL' => 'ILLINOIS',
			'IN' => 'INDIANA',
			'IA' => 'IOWA',
			'KS' => 'KANSAS',
			'KY' => 'KENTUCKY',
			'LA' => 'LOUISIANA',
			'ME' => 'MAINE',
			'MD' => 'MARYLAND',
			'MA' => 'MASSACHUSETTS',
			'MI' => 'MICHIGAN',
			'MN' => 'MINNESOTA',
			'MS' => 'MISSISSIPPI',
			'MO' => 'MISSOURI',
			'MT' => 'MONTANA',
			'NE' => 'NEBRASKA',
			'NV' => 'NEVADA',
			'NH' => 'NEW HAMPSHIRE',
			'NJ' => 'NEW JERSEY',
			'NM' => 'NEW MEXICO',
			'NY' => 'NEW YORK',
			'NC' => 'NORTH CAROLINA',
			'ND' => 'NORTH DAKOTA',
			'OH' => 'OHIO',
			'OK' => 'OKLAHOMA',
			'OR' => 'OREGON',
			'PA' => 'PENNSYLVANIA',
			'RI' => 'RHODE ISLAND',
			'SC' => 'SOUTH CAROLINA',
			'SD' => 'SOUTH DAKOTA',
			'TN' => 'TENNESSEE',
			'TX' => 'TEXAS',
			'UT' => 'UTAH',
			'VT' => 'VERMONT',
			'VA' => 'VIRGINIA',
			'WV' => 'WEST VIRGINIA',
			'WI' => 'WISCONSIN',
			'WY' => 'WYOMING'
		);

		foreach ($us_state_abbrevs_names as $abbr => $state) {
			if ($state == strtoupper($name)) {
				return strtoupper($abbr);
			}
		}

		return false;
	}
} // end model


class DummyLogger
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
