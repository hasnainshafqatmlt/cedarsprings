<?php

//Built February 2021 by Ben Nyquist
//Updated for 2022 on December 2021 by Ben Nyquist
//Updated for 2023 on November 2022 by Ben Nyquist
//Standardized for 2024 to hopefully not need to change it every year by Ben Nyquist

date_default_timezone_set('America/Los_Angeles');

require_once(__DIR__ . '/../../../../db/conn.php');
require_once(__DIR__ . '/../../../../db/db-reservations.php');
require_once __DIR__ . '/../../../SeasonalConfigElements.php';


class registrationCount
{
	protected $db;
	protected $wkNum;
	protected $name;
	protected $sessionName;
	protected $templateid;
	protected $category;
	protected $capacity;
	protected $optionParent;
	private   $modelYear;
	protected $config;
	public $logger;

	public function __construct($logger)
	{
		$this->setLogger($logger);

		$this->config = new SeasonalConfigElements($this->logger);

		$this->modelYear = $this->config->currentYear;

		$this->db = new reservationsDb();
		$this->countRegistrations();
		$this->countLunches();
	}

	function setLogger($logger)
	{
		$this->logger = $logger;
	}

	// create a debug log entry
	function d_bug($message, $data = null)
	{

		// moved this function to the logger object
		return PluginLogger::log("debug" . $message . $data);
	}


	function countRegistrations()
	{

		/* Because the categories in Ultracamp don't always match the columns in my report, I need to manually match them up.
		   Therefore, we're going to pull down each cateogry and map it to the right column in the database (i.e. Ultracamp category Camp Pod translates to DB column Pod).
		
		   Also, we're confirming that there are not any missed by checking each of the categories returned from the options list and ensuring that it gets assigned in the database as anything missed is data that we're not capturing in the report.	   	

		   This works simply by taking the Ultracamp category name (i.e. Summer Day Camp Options, and the column in the database which will hold the option name with that category (i.e. camp_name) and running the method updateReport().)

	  */

		// Get the list of option categories

		$this->d_bug("Starting countRegistration()");

		$sqlOptions = "SELECT DISTINCT(category) FROM camp_options";
		$optionCategories = $this->db->runBaseQuery($sqlOptions);

		foreach ($optionCategories as $category) {

			switch ($category['category']) {
				case 'Summer Day Camp Options':
					$this->updateReport('Summer Day Camp Options', 'camp_name');
					break;

				case 'Transportation':
					$this->updateReport('Transportation', 'transportation_option');
					break;

				case 'Overnight Transportation':
					$this->updateReport('Overnight Transportation', 'overnight_bus');
					break;

				case 'Camp Pods':
					$this->updateReport('Camp Pods', 'pod');
					break;

				case 'Camp Hot Lunch':
					// lunches are special, they're handeled elsewhere
					break;

				case 'Bus':
					$this->updateReport('Bus', 'bus_pod');
					break;

				case 'Drop off and Pick up Times':
					$this->updateReport('Drop off and Pick up Times', 'pickup_window');
					break;

				case 'Drop off and Pick up Zones':
					$this->updateReport('Drop off and Pick up Zones', 'bus_pod');
					break;

				case 'Extended Care':
					$this->updateReport('Extended Care', 'ext_care');
					break;

				case 'Keep the Fun Alive':
					$this->updateReport('Keep the Fun Alive', 'overnight_choice');
					break;

				// don't error on camper queue stuff 
				case 'Camp Queue':
				case 'Administration':
				case 'Play Pass Pods': // stop errors until we're ready to deal with the play pass categories in reports
				case 'Play Pass Days':

					break;

				default:
					PluginLogger::log("warning:: Session Option Category '" . $category['category'] . "' was found in the Options Table, but it is not mapped in the code (registrationCount-$this->modelYear.php).");
					break;
			}
		}
	}

	// now we load the reservations and count how many instances of the particular element appear for each week
	// this information is then saved into the report table, making it easy (And quick) to load the report as the counts are already aggregated
	function updateReport($optionCategory, $reservationCategory)
	{

		$sql = "SELECT name, templateid FROM camp_options WHERE category = '$optionCategory'";
		// SELECT name, templateid FROM camp_options WHERE category = 'Camp Pods'
		// 97349

		try {
			$options = $this->db->runBaseQuery($sql);
		} catch (Exception $e) {
			PluginLogger::log("error:: Unable to select the option information in function updateReport.");
			PluginLogger::log("error::" . $e->getMessage());
			PluginLogger::log("info:: " . $sql);
			return false;
		}

		if (gettype($options) != "array" && gettype($options) != "object") {
			PluginLogger::log("error:: Invalid response when selecting the options from the database.");
			PluginLogger::log("error:: This often happens when there is an invalid category in camp_options.");
			PluginLogger::log("info:: " . $sql);
			return false;
		}

		/*
		if($optionCategory == 'Camp Pods') {
			$this->d_bug("Looking through camp Pods", $options);
		}
		*/

		foreach ($options as $option) {

			for ($week = 1; $week <= $this->config->numberOfWeeks; $week++) {

				// we have some hacking in place for the two bus options in an Accelerate bus - therefore, we have to deal with that hack using unique sql
				// we're putting the go to camp transportation in transportation_option and the come home in overnight_bus
				// the report however tracks each element on its own, so the sql here has to retrieve them from the column that they're in in the reservations table
				if ($optionCategory == "Overnight Transportation") {
					//	$this->logger->d_bug("Were in the overnight transportation category.", $option['name']);

					$sqlCamps = "SELECT 
									count(reservation_id) as cnt 
								FROM 
									reservations_summer_$this->modelYear 
								WHERE 
									(overnight_bus = ? OR transportation_option = ?) 
								AND 
									week_number = ?";

					try {
						$result = $this->db->runQuery($sqlCamps, 'ssi', array($option['name'], $option['name'], $week));
					} catch (Exception $e) {
						PluginLogger::log("error:: Unable to query the overnight bus  count from reservations_summer_$this->modelYear. " . $e->getMessage());
						throw new Exception("Unable to query the overnight bus count from reservations_summer_$this->modelYear. ", 0, $e);
					}
				}
				// more specialized work (hacking is a harsh term) to capture the extended care option as a single UC category feeds two DB columns
				// this is new in 2023 as it appears that in prior years, extended care was treated as a bus pod
				else if ($optionCategory == "Extended Care") {

					// there are two options for extended care, morning and afternoon, and the reservation can have any combination of them
					if ($option['name'] == "Afternoon Extended Care") {
						$sqlCamps = "SELECT count(reservation_id) as cnt FROM reservations_summer_$this->modelYear WHERE pm_extcare = 1 AND week_number = ?";
					}

					if ($option['name'] == "Morning Extended Care") {
						$sqlCamps = "SELECT count(reservation_id) as cnt FROM reservations_summer_$this->modelYear WHERE am_extcare = 1 AND week_number = ?";
					}

					try {
						$result = $this->db->runQuery($sqlCamps, 'i', array($week));
					} catch (Exception $e) {
						PluginLogger::log("error:: Unable to query the extended care count from reservations_summer_$this->modelYear. " . $e->getMessage());
						throw new Exception("Unable to query the  extended care count from reservations_summer_$this->modelYear. ", 0, $e);
					}
				} else {
					$sqlCamps = "SELECT count(reservation_id) as cnt FROM reservations_summer_$this->modelYear WHERE $reservationCategory = ? AND week_number = ?";
					// SELECT count(reservation_id) as cnt FROM reservations_summer_2021 WHERE pod = 'FA1' AND week_number = 9

					// $this->logger->d_bug("Category, Name, Week", array($reservationCategory, $option['name'], $week));

					try {
						$result = $this->db->runQuery($sqlCamps, 'si', array($option['name'], $week));
					} catch (Exception $e) {
						PluginLogger::log("error:: Unable to query the reservation count from reservations_summer_$this->modelYear. " . $e->getMessage());
						throw new Exception("Unable to query the reservation count from reservations_summer_$this->modelYear. ", 0, $e);
					}
				}


				$count = isset($result) ? $result[0]['cnt'] : 0;


				// set week to a zeropadded string
				if (strlen($week) == 1) {
					$strWk = "0" . $week;
				} else {
					$strWk = $week;
				}

				$updateCamps = "UPDATE report_summer SET reg" . $strWk . " = ? WHERE ucid = ? LIMIT 1";
				// UPDATE report_summer SET reg09 = 9 WHERE ucid = 97349 LIMIT 1
				try {
					$this->db->update($updateCamps, 'ii', array($count, $option['templateid']));
				} catch (Exception $e) {
					PluginLogger::log("error:: Unable update the report_summer count. " . $e->getMessage());
					throw new Exception("Unable update the report_summer count. ", 0, $e);
				}
			}
		}
	}

	// lunches work dramatically different than everything else, so we're going to process them differently
	// there is a field that shows the count of reservations with lunch, and a field that has a json array of the days
	// we need to tease that array out, and store each count in the report table.
	function countLunches()
	{

		// we're going to take this one week at a time
		for ($week = 1; $week <= $this->config->numberOfWeeks; $week++) {
			// set week to a zeropadded string
			if (strlen($week) == 1) {
				$strWk = "0" . $week;
			} else {
				$strWk = $week;
			}

			// load reservations that have lunch attached
			$sql = "SELECT lunch_days, week_number FROM reservations_summer_$this->modelYear WHERE lunch_qty > 0 AND week_number = $week";
			try {
				$result = $this->db->runBaseQuery($sql);
			} catch (Exception $e) {
				PluginLogger::log("error:: Unable count lunches. " . $e->getMessage());
				throw new Exception("Unable count lunches. ", 0, $e);
			}

			// we have a list of all of the registrations that have lunch for the current week in the loop, and a JSON array of the exact days
			// need to tease those out into individual variables, count them, and store them in the report

			// create zeroed out counters for each day and store their UCID here
			$Monday = array('count' => 0, 'ucid' => 18994);
			$Tuesday = array('count' => 0, 'ucid' => 18995);
			$Wednesday = array('count' => 0, 'ucid' => 18996);
			$Thursday = array('count' => 0, 'ucid' => 18997);
			$Friday = array('count' => 0, 'ucid' => 18998);

			if (!empty($result)) {
				foreach ($result as $row) {

					$food = json_decode($row['lunch_days']);

					// add the days found to the specific counters
					foreach ($food as $lunch) {

						// change "Monday Lunch" to be "Monday"
						// then, increment the appropriate daily variable by one.

						$current_day = substr($lunch, 0, -6);
						$$current_day['count'] += 1;
					}
				}
			}


			// with the completion of the results for the current week, update the database
			$updateSql = "UPDATE report_summer SET reg" . $strWk . " = ? WHERE ucid = ? LIMIT 1";

			try {
				$this->db->update($updateSql, 'ii', array($Monday['count'], $Monday['ucid']));
				$this->db->update($updateSql, 'ii', array($Tuesday['count'], $Tuesday['ucid']));
				$this->db->update($updateSql, 'ii', array($Wednesday['count'], $Wednesday['ucid']));
				$this->db->update($updateSql, 'ii', array($Thursday['count'], $Thursday['ucid']));
				$this->db->update($updateSql, 'ii', array($Friday['count'], $Friday['ucid']));
			} catch (Exception $e) {
				PluginLogger::log("error:: Unable update lunches. " . $e->getMessage());
				throw new Exception("Unable update lunches. ", 0, $e);
			}
		}
	}
}
