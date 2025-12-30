<?php

/**
 * Manages the summer day camp specific imports from Ultracamp into the system.
 * In December 2023, I dropped the year name from the file and class name. Historically
 * each season was dramatically different than the prior, so each year's model was also different.
 * Because that is not the case any more, I'm standardizing everything to a reusable file and a
 * configuration class. (BN)
 */

require 'Model.php';

class SummerModel extends BaseModel
{

	public $transportation_option;
	public $overnightChoice;
	public $busPod;
	public $lunchDays;
	public $earlyWindow;
	public $lateWindow;
	public $queueEntry;
	public $missingWaiver = 0;
	public $overnightBus;
	public $pickupWindow;

	public $model_year;
	public $reservationTable;

	private $config;

	function __construct($logger = null)
	{

		parent::__construct($logger);

		// pull in the seasonal information such as table names and year
		require_once __DIR__ . '/../../../SeasonalConfigElements.php';
		$this->config = new SeasonalConfigElements;

		$this->model_year = $this->config->currentYear;
		$this->reservationTable = $this->config->reservations;
	}

	function getModelYear()
	{
		return $this->model_year;
	}

	function processReservation($reservation)
	{
		$this->reservationId = NULL;
		$this->reservationUCID = NULL;
		$this->personUCID = NULL;
		$this->accountUCID = NULL;
		$this->sessionUCID = NULL;
		$this->sessionName = NULL;
		$this->weekNumber = NULL;
		$this->campName = NULL;
		$this->transportation = NULL;
		$this->transportation_option = NULL;
		$this->busRider = NULL;
		$this->lunchQty = NULL;
		$this->amExtCare = NULL;
		$this->pmExtCare = NULL;
		$this->pod = NULL;
		$this->orderDate = NULL;
		$this->revenue = NULL;
		$this->discounts = NULL;
		$this->overnightBus = NULL;
		$this->overnightChoice = NULL;
		$this->busPod = NULL;
		$this->pickupWindow = NULL;
		$this->queueEntry = 0;


		$this->processBasicInfo($reservation);

		// season specific functions and data
		$this->getWeekNumber();
		$this->getCampName($reservation->SessionOptions);
		$this->getTransportation($reservation->SessionOptions);
		$this->getExtCare($reservation->SessionOptions);
		$this->countLunches($reservation->SessionOptions);
		$this->getPod($reservation->SessionOptions);
		$this->getOvernight($reservation->SessionOptions);
		$this->checkForWaiver($reservation);
		$this->clearMatchingQueues($reservation->SessionOptions);


		$this->storeReservation($reservation);
	}

	function storeReservation($reservation)
	{
		// ensure that everything exists
		if (
			!$this->reservationUCID ||
			!$this->personUCID ||
			!$this->accountUCID ||
			!$this->sessionUCID ||
			!$this->sessionName
		) {

			PluginLogger::log("Error:: Unable to store the reservation. Required data is missing.");
			return false;
		}

		$this->storeUser($reservation);

		// Check if reservation already exists (to determine if it's new for monitoring)
		$isNewReservation = $this->checkIfNewReservation($reservation->ReservationId);

		// save the step of looking for the reservation in order to know if we should delete it. Just run the delete
		$sql = "DELETE FROM $this->reservationTable WHERE reservation_ucid = " . $reservation->ReservationId;
		try {
			$this->db->runBaseQuery($sql);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to delete the reservation into the database." . $e->getMessage());
		}

		$sql = "INSERT INTO 
						$this->reservationTable 
				 (
						  reservation_ucid
						, person_ucid
						, account_ucid
						, session_ucid
						, session_name
						, week_number
						, camp_name
						, transportation
						, transportation_option
						, bus_rider
						, lunch_qty
						, am_extcare
						, pm_extcare
						, pod
						, order_date
						, revenue
						, discounts
						, overnight_bus
						, overnight_choice
						, bus_pod
						, lunch_days
						, pickup_window
						, queueEntry
						, missing_waiver
				) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

		$parameters = array(
			$this->reservationUCID,
			$this->personUCID,
			$this->accountUCID,
			$this->sessionUCID,
			$this->sessionName,
			$this->weekNumber,
			$this->campName,
			$this->transportation,
			$this->transportation_option,
			$this->busRider,
			$this->lunchQty,
			$this->amExtCare,
			$this->pmExtCare,
			$this->pod,
			$this->orderDate,
			$this->revenue,
			$this->discounts,
			$this->overnightBus,
			$this->overnightChoice,
			$this->busPod,
			$this->lunchDays,
			$this->pickupWindow,
			$this->queueEntry,
			$this->missingWaiver
		);

		$paramType = 'iiiisisssiiiissssssssssi';

		// run it!
		try {
			$result = $this->db->insert($sql, $paramType, $parameters);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to enter the reservation into the database." . $e->getMessage());
		}

		if ($result < 1) {
			PluginLogger::log("Error:: SQL did not complete the reservation insert.");
			PluginLogger::log("Error::" . $sql . $parameters);
			return false;
		}

		// Check for monitored accounts on new reservations
		$this->checkMonitoredAccount($reservation, $isNewReservation);

		// update the reservation count in the campers table
		try {
			$this->updateCamperReservationCount($this->personUCID);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to update the $this->model_year reservation count for camper " . $this->personUCID . ": " . $e->getMessage());
		}

		// mark the first reservation of the summer
		try {
			$this->updateFirstReservation($this->personUCID);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to update the $this->model_year first reservation for camper " . $this->personUCID . ": " . $e->getMessage());
		}

		// check to see if there is any friend data in the reservation
		try {
			$this->recordFriends($reservation);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to store the friend information to the database: ", $e->getMessage());
		}

		// checking for my test reservation
		if ($reservation->ReservationId == 10034988) {
			PluginLogger::log("debug:: Checking test daily reservation");
		}

		// if this is a daily reservation, we need to store the metadata
		try {
			$this->storeDailyReservationDetails($reservation);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to store the daily reservation details: ", $e->getMessage());
		}
	}

	/**
	 * Gets the week number for the current session. Used to have to check the array key, now we just look it up in the DB
	 */
	function getWeekNumber()
	{
		if (!$this->sessionUCID) {
			return false;
		}

		$sql = "SELECT week_num FROM summer_weeks WHERE session_id = ?";
		try {
			$result = $this->db->runQuery($sql, 'i', $this->sessionUCID);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to get the week number for session " . $this->sessionUCID . ": " . $e->getMessage());
			return false;
		}

		$this->weekNumber = $result[0]['week_num'];
		return is_int($this->weekNumber);
	}

	// Looks for the option with the "Summer Day Camp Options" category in order to determine the primary camp name
	// when the camp name is found, the object property is set.
	// Added camper queue as well on 3/17/23 (BN)
	function getCampName($options)
	{

		foreach ($options as $option) {
			if ($option->Category == "Summer Day Camp Options" || $option->Category == "Camp Queue") {
				$this->campName = $option->Name;

				// if Accelerate camp, then check on bus situtation
				if ($this->campName == "Accelerate: Week Long Overnight Camp") {
					$this->getOvernightBus($options);
					PluginLogger::log("debug:: Found Accelerate", $option);
				}

				// note in the reservation that this is a camp queue entry
				if ($option->Category == "Camp Queue") {
					$this->queueEntry = 1;
				}

				return true;
			}
		}

		// if there isn't a camp name found, we're going to return false - proofreader should pick this up later anyway
		return false;
	}

	// we'll grab extended care here - for 2023, LKS Extended care has it's own category for the first time
	function getExtCare($options)
	{
		foreach ($options as $option) {
			//			$this->logger->d_bug("Name", $option->Name);
			//			$this->logger->d_bug("Category", $option->Category);

			// check for the lake stevens extended care options and set their flags too
			if ($option->Name == "Morning Extended Care") {
				$this->amExtCare = 1;
			}
			if ($option->Name == "Afternoon Extended Care") {
				$this->pmExtCare = 1;
			}
		}
	}

	// works through the options to identify the transportation options selected
	function getTransportation($options)
	{

		foreach ($options as $option) {
			//	$this->logger->d_bug("Name", $option->Name);
			//	$this->logger->d_bug("Category", $option->Category);
			// for the early bothell bus, flag early extended care
			if ($option->Name == "Bothell Early Bus") {
				$this->amExtCare = 1;
			}

			if ($option->Category == "Transportation") {

				$this->transportation_option = $option->Name;

				if ($option->Subcategory == "Lake Stevens Drop-Off") {
					$this->transportation = "Lake Stevens";
					$this->busRider = 0;
					$this->getBusPod($options);
					return true;
				}

				// need to re-name the bothell options to remove the extended care info
				if ($option->Subcategory == "Transportation - Bothell") {
					$this->busRider = 1;
					$this->transportation = "Bothell";
					$this->getBusPod($options);
					return true;
				}

				// we're not looking at Bothell or Lake Stevens, so just store the transportation option
				$this->transportation = substr($option->Name, 0, -9); // removes the " Bus Stop" from the end of the string
				$this->busRider = 1;
				$this->getBusPod($options);
				return true;
			}
		}

		return false;
	}

	function getBusPod($options)
	{

		foreach ($options as $option) {
			if ($option->Category == "Bus") {
				$this->busPod = $option->Name;
				return true;
			}

			// the parent pickup adds some complexity that we need to work through
			if ($option->Category == "Drop off and Pick up Times") {
				if ($option->Name == "Window A") {
					$this->pickupWindow = 'Window A';
				} else {
					$this->pickupWindow = 'Window B'; // we don't explicitly set this option in UC for PM ext care, so we'll assume it's true if early isn't
				}
			}

			if ($option->Category == "Drop off and Pick up Zones") {
				$this->busPod = $option->Name;
			}
		}
	}

	function getOvernight($options)
	{

		foreach ($options as $option) {
			if ($option->Category == "Keep the Fun Alive") {
				$this->overnightChoice = $option->Name;
				return true;
			}
		}
	}

	function countLunches($options)
	{
		$count = 0;

		// need to store the exact days for reporting
		$lunchDays = array();

		foreach ($options as $option) {
			if ($option->Category == "Camp Hot Lunch") {
				$count++;
				$lunchDays[] = $option->Name;
			}
		}

		$this->lunchQty = $count;

		// There is an odd bug where Ultracamp is inconsistent with the order of the lunch options coming in from their API
		// When the order doesn't match what is stored in the lunchDays JSON, changelogs sees this as a modification, and sends the customer an email, sometimes repeatidly.
		// Therefore, we're going to sort the lunch days array everytime it comes in, to ensure that order isn't random (4/9/2024)

		// Define the correct order of the days
		$dayOrder = ['Monday Lunch', 'Tuesday Lunch', 'Wednesday Lunch', 'Thursday Lunch', 'Friday Lunch'];

		// Sort the $lunchDays array according to the order in $dayOrder
		usort($lunchDays, function ($a, $b) use ($dayOrder) {
			$posA = array_search($a, $dayOrder);
			$posB = array_search($b, $dayOrder);
			return $posA - $posB;
		});

		$this->lunchDays = json_encode($lunchDays);

		return true;
	}


	function getPod($options)
	{
		foreach ($options as $option) {

			if ($option->Category == "Camp Pods") {
				$this->pod = $option->Name;
				return true;
			}
		}
		return false;
	}

	function getOvernightBus($options)
	{

		foreach ($options as $option) {
			if ($option->Category == "Overnight Transportation") {
				$this->overnightBus = $option->Name;
				return true;
			}
		}
	}

	function findReservationId($reservationUcid)
	{
		//check to ensure that the user doesn't already exist
		$sql = "SELECT reservation_id FROM $this->reservationTable WHERE reservation_ucid = ?";
		try {
			$result = $this->db->runQuery($sql, 'i', array($reservationUcid));
		} catch (Exception $e) {
			throw new Exception("Unable to query the database to find a person.", 0, $e);
			return false;
		}

		return $result[0];
	}

	/** 
	 * Sets the missing waiver flag if the reservations shows that the form is incomplete
	 */
	function checkForWaiver($reservation)
	{
		if (empty($reservation->UncompletedForms)) {
			// there are no pending forms - we're good
			$this->missingWaiver = 0;
			return true;
		}

		foreach ($reservation->UncompletedForms as $forms) {
			if ($forms == 'General Camp Waiver') {
				$this->missingWaiver = 1;
				return true;
			}
		}

		// if we don't find anything, the default is 0, so we should be good to not take any action
		return true;
	}

	/**
	 * Takes the option information and clears any in the waitlist that match, marking them as fullfilled
	 */
	function clearMatchingQueues($options)
	{
		// Track all options we need to process
		$matchingOptions = [];

		// First pass - categorize all options
		foreach ($options as $option) {
			switch ($option->Category) {
				case "Summer Day Camp Options":
					$matchingOptions['camp'] = $option->SessionOptionId;
					break;
				case "Camp Queue":
					$matchingOptions['queue'] = $option->SessionOptionId;
					break;
				case "Keep the Fun Alive":
					$matchingOptions['overnight'] = $option->SessionOptionId;
					break;
			}
		}

		// No valid options found
		if (empty($matchingOptions)) {
			PluginLogger::log("info:: clearMatchingQueues found a reservation without any matching options.");
			return false;
		}

		PluginLogger::log("debug:: Found options to process", $matchingOptions);

		// Process each option type
		foreach ($matchingOptions as $type => $optionId) {
			// Convert option ID to template ID
			$sql = "SELECT m.templateid, o.name, o.category 
					FROM camp_option_mapping m, camp_options o 
					WHERE m.optionid = ? AND o.templateid = m.templateid";

			try {
				$template = $this->db->runQuery($sql, 'i', $optionId);
				if (empty($template)) {
					PluginLogger::log("warning:: No template found for option $optionId");
					continue;
				}

				// Handle queue conversion if needed
				if ($template[0]['category'] === "Camp Queue") {
					$sql = "SELECT templateid FROM camp_options 
							WHERE category IN ('Summer Day Camp Options', 'Keep the Fun Alive') AND name = ?";

					$template = $this->db->runQuery($sql, 's', $template[0]['name']);
					if (empty($template)) {
						PluginLogger::log("warning:: Could not convert queue template for option $optionId");
						continue;
					}
				}

				// Check and update waitlist
				$sql = "SELECT id FROM waitlist 
						WHERE camperId = ? AND week_num = ? AND campId = ? AND active > 0";

				$waitlist = $this->db->runQuery($sql, 'iii', [
					$this->personUCID,
					$this->weekNumber,
					$template[0]['templateid']
				]);

				if (!empty($waitlist)) {
					$this->db->update(
						'UPDATE waitlist SET fulfilled = ?, active = 0 WHERE id = ?',
						'ii',
						[$this->reservationUCID, $waitlist[0]['id']]
					);
					PluginLogger::log("info:: Marked waitlist entry {$waitlist[0]['id']} as fulfilled for type $type");
				}
			} catch (Exception $e) {
				PluginLogger::log("Error:: Error processing option $optionId: " . $e->getMessage());
				continue;
			}
		}

		return true;
	}


	// runs a sql query to ensure that every reservation that is in the table also exists in the user table
	function ensureAllUsersExist()
	{
		$sql = "SELECT 
				r.person_ucid,
				r.account_ucid,
				r.session_name,
				r.camp_name,
				r.reservation_ucid
			 FROM $this->reservationTable r
			LEFT JOIN campers c ON r.person_ucid = c.person_ucid
			WHERE c.person_ucid IS NULL";

		$result = $this->db->runBaseQuery($sql);
		if ($result) {
			if (count($result) > 0) {
				PluginLogger::log("warning:: Reservations exists in Summer $this->model_year that do not have associated users.", $result);
			}
		}
	}

	// make sure that canceled reservations are removed from the database
	function removeCanceledReservations($reservationUcid)
	{

		// check to see if the reservation is still in the database
		$sqlSelect = "SELECT reservation_id, person_ucid FROM $this->reservationTable WHERE reservation_ucid = ?";

		try {
			$result = $this->db->runQuery($sqlSelect, 'i', array($reservationUcid));
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to select reservation from $this->reservationTable to check for cancelations. " . $e->getMessage());
			throw new Exception("Unable to select reservation from $this->reservationTable to check for cancelations. ", 0, $e);
			return false;
		}

		// if it is, delete it and report to logger that there was a delete made
		if (is_array($result)) {

			$sql = "DELETE FROM $this->reservationTable WHERE reservation_ucid = " . $reservationUcid;
			try {
				$this->db->runBaseQuery($sql);
			} catch (Exception $e) {
				PluginLogger::log("Error:: Failed to remove canceled reservation. " . $e->getMessage());
				throw new Exception("Failed to remove canceled reservation. ", 0, $e);
				return false;
			}

			$this->updateCamperReservationCount($result[0]['person_ucid']); // added 9/27/22 because the mailing system noticed cancelations don't reduce the count
			PluginLogger::log("info:: Reservation $reservationUcid was deleted from the database due to cancelation");
		}

		return true;
	}

	// counts the reservations that an individual has and updates the count_{$model_year} column in the campers table
	function updateCamperReservationCount($person)
	{

		$sqlSelect = "SELECT count(reservation_id) AS count FROM $this->reservationTable WHERE person_ucid = ?";
		try {
			$result = $this->db->runQuery($sqlSelect, 'i', array($person));
		} catch (Exception $e) {
			throw new Exception("Unable to query the database to count reservations.", 0, $e);
			return false;
		}

		$count = $result[0]['count'];

		$sqlUpdate = "UPDATE campers SET count_$this->model_year = ? WHERE person_ucid = ? LIMIT 1";
		try {
			$result = $this->db->update($sqlUpdate, 'ii', array($count, $person));
		} catch (Exception $e) {
			throw new Exception("Unable to update the campers table to modify the count_$this->model_year column for $person.", 0, $e);
			return false;
		}

		return true;
	}

	// as of 5/13/22 - this notes the first reservation of the summer - we don't use the Ultrcamp flag here because off-season camps will trigger that incorrectly
	function updateFirstReservation($person)
	{

		$sqlSelect = "SELECT reservation_ucid 
						FROM $this->reservationTable 
						WHERE person_ucid =  ?
						ORDER BY week_number ASC LIMIT 1";
		try {
			$result = $this->db->runQuery($sqlSelect, 'i', array($person));
		} catch (Exception $e) {
			throw new Exception("Unable to mark the first record of the season for $person.", 0, $e);
			return false;
		}

		if (empty($result[0]['reservation_ucid'])) {
			throw new Exception("Unable to mark retrieve the reservation ID for the first record of the season for $person.", 0);
			return false;
		}

		$sqlUpdate = "UPDATE $this->reservationTable 
			SET first_of_summer = 1 
			WHERE reservation_ucid = ?
			LIMIT 1";

		try {
			$result = $this->db->update($sqlUpdate, 'i', array($result[0]['reservation_ucid']));
		} catch (Exception $e) {
			throw new Exception("Unable to mark the first record of the season for $person.", 0, $e);
			return false;
		}

		return true;
	}

	// if they have friend information stored in the custom question, store it in the database so that friend finder can deal with it at its slow pace
	function recordFriends($reservation)
	{
		$customQuestions = (isset($reservation->CustomQuestions) ? $reservation->CustomQuestions : null);

		// data, if it exists, looks like this
		// ["Friend Request: Kobey Salvage, Kaden Salvage, Lauren Schirer, Delphina Beecroft, Kenzie Beecroft"]
		// Need to Srip off the label, and hang on to everything else

		// first, check that Friend Request: is in the list of custom questions
		if (isset($customQuestions)) {
			foreach ($customQuestions as $q) {
				if (substr($q, 0, 16) == "Friend Request: ") {
					$friends = trim(substr($q, 16));
				} else {
					// else there wasn't a friend request in the custom question, so we're done here.
					continue;
				}
			}
		}

		// look to store anything found to the database
		// we do this if there isn't already and identical record for this person
		if (isset($friends) && strlen($friends) > 0) {
			$sqlCheckForFriends = "SELECT friend_data FROM ultracamp_friend_requests WHERE person_ucid = ? AND friend_data = ?";

			try {
				$result = $this->db->runQuery($sqlCheckForFriends, 'is', array($reservation->PersonId, $friends));
			} catch (Exception $e) {
				PluginLogger::log("Error:: Unable to check the database for friend data. " . $e->getMessage());
				throw new Exception("Unable to check the database for friend data. " . $e->getMessage());
			}

			// if we don't have a record for this friend data, create one
			if (empty($result)) {
				$sql = "INSERT INTO ultracamp_friend_requests (reservation_id, person_ucid, friend_data) VALUES (?,?,?)";
				PluginLogger::log("info:: Inserting friend data: ", array($reservation->ReservationId, $reservation->PersonId, $friends));

				try {
					$this->db->insert($sql, 'iis', array($reservation->ReservationId, $reservation->PersonId, $friends));
				} catch (Exception $e) {
					PluginLogger::log("Error:: Unable to insert friend value into the database for friend data. " . $e->getMessage());
					throw new Exception("Unable to insert friend value into the database for friend data. " . $e->getMessage());
				}
			}
		}
	}

	// Returns the current week number, aligning the date with the database, and dealing with 4th of July landing on Friday through Monday and 
	// causing weeks to perhaps not be listed in the database as having a full 5 days
	function getCurrentWeek()
	{
		$currentDate = date('Y-m-d');
		$queryDate = $currentDate;

		// If July 4th falls on a Monday, use July 5th
		if ($currentDate == date('Y') . '-07-04' && date('N', strtotime($currentDate)) == 1) {
			$queryDate = date('Y') . '-07-05';
		}

		// If July 4th falls on a Friday, or it's July 4th weekend, match against July 3rd
		if (($currentDate == date('Y') . '-07-04' && date('N', strtotime($currentDate)) == 5) ||
			($currentDate == date('Y') . '-07-05' || $currentDate == date('Y') . '-07-06')
		) {
			$queryDate = date('Y') . '-07-03';
		}

		$sql = 'SELECT week_num 
				FROM summer_weeks 
				WHERE (? BETWEEN start_date AND end_date)  # Weekday check
				   OR (? BETWEEN DATE(end_date + INTERVAL 1 DAY) AND DATE(end_date + INTERVAL 2 DAY))  # Weekend check
				ORDER BY start_date DESC 
				LIMIT 1';

		try {
			$result = $this->db->runQuery($sql, 'ss', array($queryDate, $queryDate));
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to select the week number from the summer_weeks database");
			PluginLogger::log($e->getMessage());
			return 0;
		}

		// return zero if we're out of range for the summer 
		if (empty($result)) {
			return 0;
		}

		return $result[0]['week_num'];
	}

	// Looks through the session options and triggers true if the session option is listed as a template Id for daily behavior
	// 3/24/25
	function isDailyReservation($reservation)
	{

		PluginLogger::log("debug:: Checking reservation {$reservation->ReservationId} for daily template IDs.");

		// ensure that there are daily templates, and session options
		if (!is_array($this->config->dailyOptionTemplates) || empty($this->config->dailyOptionTemplates)) {
			// there are not any daily templates, so the incoming session cannot be one of them
			return false;
		}

		if (!is_array($reservation->SessionOptions) || empty($reservation->SessionOptions)) {
			PluginLogger::log("warning:: Sessions Options for reservation {$reservation->ReservationId} is empty or not an array.");
			return false;
		}

		// List session option ids
		$values = [];
		$params = '';

		// Build placeholders for options and templates
		$optionCount = implode(',', array_fill(0, count($reservation->SessionOptions), '?'));
		$templateCount = implode(',', array_fill(0, count($this->config->dailyOptionTemplates), '?'));

		// Map session options to extract SessionOptionId values
		$optionValues = array_map(function ($option) {
			return $option->SessionOptionId;
		}, $reservation->SessionOptions);

		// Merge the session option values with the template ids
		$values = array_merge($this->config->dailyOptionTemplates, $optionValues);

		// Build the parameter type string (assuming all integers)
		$params = str_repeat('i', count($values));

		$sql = "SELECT optionid FROM camp_option_mapping WHERE templateid IN ($templateCount) AND optionid IN ($optionCount)";

		/*
		PluginLogger::log("debug:: SQL: $sql");
		PluginLogger::log("debug:: Params: $params");
		PluginLogger::log("debug:: Values", $values);
		*/

		try {
			$result = $this->db->runQuery($sql, $params, $values);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to process isDailyReservation() due to a sql error: " . $e->getMessage());
			return false;
		}

		if (is_array($result) && count($result) > 0) {
			PluginLogger::log("debug:: Reservation {$reservation->ReservationId} was found to be a daily reservation.");
			return true;
		}

		//PluginLogger::log("debug:: Reservation {$reservation->ReservationId} was found to NOT be a daily reservation.", $result);
		return false;
	}

	// Identifies if we have a daily reservation.
	// When a daily reservation is identified, stores the daily reservation details
	function storeDailyReservationDetails($reservation)
	{
		if (!$this->isDailyReservation($reservation)) {
			// return if this isn't a daily reservation
			return true;
		}

		PluginLogger::log("debug:: Starting Daily reservation insert process.");

		// unlike any other place in the database, i'm gonig to store optionIDs rather than names
		// this is partially because the name for this daily camp hasn't been set yet and partially
		// because this tool has evolved alot since I built it as a simple reporting database in 2018/19
		// and  I have the option_mapping table, which is relativley new


		// Get the foriegn key from the reservations table
		try {
			$reservationResult = $this->db->runQuery("SELECT reservation_id FROM {$this->config->reservations} WHERE reservation_ucid = ? LIMIT 1", 'i', [$reservation->ReservationId]);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Unable to get the reservation_id for reservation ucid {$reservation->ReservationId}: " . $e->getMessage());
			return false;
		}

		if (is_array($reservationResult)) {
			$reservationId = $reservationResult[0]['reservation_id'];
		} else {
			PluginLogger::log("Error:: Unable to retrieve the foreign key from the summer reservations table.");
			return false;
		}

		// get the optionid for the chosen camp
		$campOptionId = null;
		foreach ($reservation->SessionOptions as $option) {
			if ($option->Category === 'Summer Day Camp Options') {
				$campOptionId = $option->SessionOptionId;
				break;
			}
		}

		$sqlInsert = "INSERT INTO {$this->config->daily_reservations} (reservation_id, type, day, session_template) VALUES (?,?,?,?)";

		// We're going to itterate the session options, and based on their category. We'll put them in the database based on that
		foreach ($reservation->SessionOptions as $option) {
			// start with the camp days - stores the camp option name
			PluginLogger::log("debug:: Option Category for Daily: {$option->Category}");
			switch ($option->Category) {
				case "Play Pass Days":
					$optType = 'camp';
					$optValue = $campOptionId;
					$optDay = date('N', strtotime($option->Name)); // Monday is 1, Sunday is 7
					break;

				case "Extended Care":
					$optType = 'ExtendedCare';
					$optValue = $option->SessionOptionId;
					$optDay = date('N', strtotime(trim(explode(' - ', $option->Name)[0])));
					break;

				case "Play Pass Pods":
					$optType = 'pod';
					$optValue = $option->SessionOptionId;
					$optDay = date('N', strtotime(trim(explode(' - ', $option->Name)[0])));
					break;

				case "Play Pass Transportation":
					$optType = 'transportation';
					$optValue = $option->SessionOptionId;
					$optDay = date('N', strtotime(trim(explode(' - ', $option->Name)[0])));
					break;

				default:
					$optType = null;
			}

			if ($optType) {
				try {
					$this->db->insert($sqlInsert, 'isii', [$reservationId, $optType, $optDay, $optValue]);
				} catch (Exception $e) {
					PluginLogger::log("Error:: Unable to store the daily reservation data: " . $e->getMessage());
					PluginLogger::log("Error:: SQL: $sqlInsert", [$reservationId, $optType, $optDay, $optValue]);
				}
			}
		} // end switch


	}

	/**
	 * Check if a reservation is new (doesn't exist in database yet)
	 * 
	 * @param string $reservationId Reservation UCID
	 * @return bool True if new reservation, false if existing
	 */
	private function checkIfNewReservation($reservationId)
	{
		try {
			$sql = "SELECT COUNT(*) as count FROM {$this->reservationTable} WHERE reservation_ucid = ?";
			$result = $this->db->runQuery($sql, 'i', [$reservationId]);

			// If count is 0, it's a new reservation
			return ($result[0]['count'] == 0);
		} catch (Exception $e) {
			PluginLogger::log("Error:: Error checking if reservation is new: " . $e->getMessage());
			// Default to false (assume existing) to avoid duplicate alerts
			return false;
		}
	}

	/**
	 * Check if this reservation is from a monitored account and send alert if needed
	 * 
	 * @param object $reservation Reservation object from Ultracamp
	 * @param bool $isNewReservation Whether this is a new reservation
	 */
	private function checkMonitoredAccount($reservation, $isNewReservation = false)
	{
		try {
			// If reservation already exists, it's not new - skip monitoring
			if (!$isNewReservation) {
				return;
			}

			// This is a new reservation - check if account is monitored

			// commented for later use

			// require_once(__DIR__ . '/../../ticketmaster/classes/MonitoredAccounts.php');
			// $monitor = new MonitoredAccounts($this->logger);

			// $camperName = trim($reservation->FirstName . ' ' . $reservation->LastName);

			// $monitoringData = $monitor->checkAccountMonitored($this->accountUCID, $camperName);

			// if ($monitoringData && !$monitor->isReservationNotified($this->reservationUCID)) {
			// 	// Send alert email
			// 	$alertSent = $monitor->sendAlert($this->accountUCID, $camperName, $this->reservationUCID, $monitoringData);

			// 	if ($alertSent) {
			// 		// Record the notification
			// 		$monitor->recordNotification($this->reservationUCID, $this->accountUCID, $camperName);

			// PluginLogger::log("info:: Monitored account alert sent successfully", [
			// 			'account_id' => $this->accountUCID,
			// 			'camper_name' => $camperName,
			// 			'reservation_ucid' => $this->reservationUCID,
			// 			'monitoring_notes' => $monitoringData['notes']
			// 		]);
			// 	}
			// }

		} catch (Exception $e) {
			// Log error but don't break reservation processing
			PluginLogger::log("Error:: Error checking monitored account for reservation {$this->reservationUCID}: " . $e->getMessage());
		}
	}
}
