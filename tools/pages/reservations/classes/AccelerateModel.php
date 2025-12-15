<?php

require_once 'SummerModel.php';

class AccelerateModel extends SummerModel
{

	function __construct($logger = null)
	{

		parent::__construct($logger);
	}

	/**
	 * Gets session information from the database. This replaces the old manually typed array.
	 */
	function getSessions()
	{
		$sql = "SELECT session_id as ucid, short_name as name, long_name, week_num  FROM accelerate_weeks ORDER by week_num";
		try {
			$sessions = $this->db->runBaseQuery($sql);
		} catch (Exception $e) {
			PluginLogger::log("error:: Unable to retrieve the accelerate session list from the database in the reservations Model: " . $e->getMessage());
			return false;
		}

		return $sessions;
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
		$this->campName = "Accelerate: Week Long Overnight Camp";
		$this->getOvernightBus($reservation->SessionOptions);

		// may need this later
		//$this->getPod($reservation->SessionOptions);

		$this->storeReservation($reservation);
	}


	function getWeekNumber()
	{
		if (!$this->sessionName) {
			PluginLogger::log("error:: Unable to get the Accelerate weeknumber as no session name has been set.");
			return false;
		}

		$sessions = $this->getSessions();

		PluginLogger::log("d_bug:: Sessions", $sessions);
		PluginLogger::log("d_bug:: Session Name", $this->sessionName);

		foreach ($sessions as $s) {
			if ($this->sessionName == $s['long_name']) {
				PluginLogger::log("d_bug:: Accel Week Number: " . $s['week_num']);
				$this->weekNumber = $s['week_num'];
				return $s['week_num'];
			}
		}

		//return a zero if we don't find the week
		PluginLogger::log("error:: Unable to find a week number for Accelerate Session " . $this->sessionName);
		$this->weekNumber = 0;
		return 0;
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
		// we're going to hack this
		// there are two transportation options, going there and coming back
		// we'll use the transportation_option column for the going, and the overnight_bus for the return

		foreach ($options as $option) {
			if ($option->Category == "Overnight Transportation") {
				if ($option->Name == "Monday Ride to Camp" || $option->Name == "Monday Direct Drop Off") {
					$this->transportation_option = $option->Name;
				} else {
					$this->overnightBus = $option->Name;
				}

				// set the bus rider flag - because we may as well
				if ($option->Name == "Monday Ride to Camp" || $option->Name == "Friday Ride Home From Camp") {
					$this->busRider = 1;
				}
			}
		}
		return true;
	}
}
