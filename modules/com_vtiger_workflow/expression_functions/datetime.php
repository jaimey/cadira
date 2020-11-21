<?php
/*+*******************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */

/* Date difference between (input times) or (current time and input time)
 *
 * @param Array $a $a[0] - Input time1, $a[1] - Input time2
 * (if $a[1] is not available $a[0] = Current Time, $a[1] = Input time1)
 * @return int difference timestamp
 */
function __vt_time_diff($arr)
{
	$storedTimeZone = date_default_timezone_get();
	$defaultTimezone = vglobal('default_timezone');
	date_default_timezone_set($defaultTimezone);
	$time_operand1 = $time_operand2 = 0;
	if (count($arr) > 1) {
		$time_operand1 = $time1 = $arr[0];
		$time_operand2 = $time2 = $arr[1];

		$trimmedOperand1 = trim($time_operand1);
		$trimmedOperand2 = trim($time_operand2);
		list($date1, $time1) = explode(' ', $trimmedOperand1);
		list($date2, $time2) = explode(' ', $trimmedOperand2);
		if (strpos($date1, ':')) {
			$time1 = $date1;
			$date1 = '';
		}
		if (strpos($date2, ':')) {
			$time2 = $date2;
			$date2 = '';
		}
		if (empty($time1)) {
			$time_operand1 = $time_operand1.' 00:00:00';
		} else {
			$time_operand1 = $userStartDateTime = DateTimeField::convertToUserTimeZone($time_operand1); // convert to user time
			$time_operand1 = $time_operand1->format('Y-m-d H:i:s');
		}
		if (empty($time2)) {
			$time_operand2 = $time_operand2.' 00:00:00';
		} else {
			$time_operand2 = $userStartDateTime = DateTimeField::convertToUserTimeZone($time_operand2); // convert to user time
			$time_operand2 = $time_operand2->format('Y-m-d H:i:s');
		}
	} else {
		// Added as we need to compare with the values based on the user date format and timezone

		$time_operand1 = date('Y-m-d H:i:s'); // Current time
		$time_operand1 = $userStartDateTime = DateTimeField::convertToUserTimeZone($time_operand1); // convert to user time
		$time_operand1 = $time_operand1->format('Y-m-d H:i:s');

		$time_operand2 = $arr[0];
		$trimmedOperand = trim($time_operand2);
		list($date, $time) = explode(' ', $trimmedOperand);
		if (empty($time)) {
			$time_operand2 = $time_operand2.' 00:00:00';
		} else {
			$time_operand2 = $userStartDateTime = DateTimeField::convertToUserTimeZone($time_operand2); // convert to user time
			$time_operand2 = $time_operand2->format('Y-m-d H:i:s');
		}
	}

	if (empty($time_operand1) || empty($time_operand2)) {
		return 0;
	}

	$time_operand1 = getValidDBInsertDateTimeValue($time_operand1);
	$time_operand2 = getValidDBInsertDateTimeValue($time_operand2);

	//to give the difference if it is only time field
	if (empty($time_operand1) && empty($time_operand2)) {
		$pattern = '/([01]?[0-9]|2[0-3]):[0-5][0-9]/';
		if (preg_match($pattern, $time1) && preg_match($pattern, $time2)) {
			$timeDiff = strtotime($time1) - strtotime($time2);

			return date('H:i:s', $timeDiff);
		}
	}
	date_default_timezone_set($storedTimeZone);

	return (strtotime($time_operand1) - strtotime($time_operand2));
}

/**
 * Calculate the time difference (input times) or (current time and input time) and
 * convert it into number of days.
 * @param Array $a $a[0] - Input time1, $a[1] - Input time2
 * (if $a[1] is not available $a[0] = Current Time, $a[1] = Input time1)
 * @param mixed $arr
 * @return int number of days
 */
function __vt_time_diffdays($arr)
{
	$timediff = __vt_time_diff($arr);

	return floor($timediff / (60 * 60 * 24));
}

function __cb_getWeekdayDifference($arr)
{
	if (count($arr) > 1) {
		$time_operand1 = $arr[0];
		$time_operand2 = $arr[1];
	} else {
		$time_operand1 = date('Y-m-d H:i:s'); // Current time
		$time_operand2 = $arr[0];
	}

	if (empty($time_operand1) || empty($time_operand2)) {
		return 0;
	}
	$startDate = new DateTime($time_operand1);
	$endDate = new DateTime($time_operand2);
	if ($startDate > $endDate) {
		$h = $startDate;
		$startDate = $endDate;
		$endDate = $h;
	}
	$days = 0;
	$oneDay = new DateInterval('P1D');
	while ($startDate->diff($endDate)->days > 0) {
		$days += $startDate->format('N') < 6 ? 1 : 0;
		$startDate = $startDate->add($oneDay);
	}

	return $days;
}

function __vt_add_days($arr)
{
	if (count($arr) > 1) {
		$baseDate = $arr[0];
		$noOfDays = $arr[1];
	} else {
		$noOfDays = $arr[0];
	}
	if (empty($baseDate)) {
		$baseDate = date('Y-m-d'); // Current date
	}
	preg_match('/\d\d\d\d-\d\d-\d\d/', $baseDate, $match);
	$baseDate = strtotime($match[0]);

	return strftime('%Y-%m-%d', $baseDate + ($noOfDays * 24 * 60 * 60));
}

function __vt_sub_days($arr)
{
	if (count($arr) > 1) {
		$baseDate = $arr[0];
		$noOfDays = $arr[1];
	} else {
		$noOfDays = $arr[0];
	}
	if (empty($baseDate)) {
		$baseDate = date('Y-m-d'); // Current date
	}
	preg_match('/\d\d\d\d-\d\d-\d\d/', $baseDate, $match);
	$baseDate = strtotime($match[0]);

	return strftime('%Y-%m-%d', $baseDate - ($noOfDays * 24 * 60 * 60));
}

function __vt_get_date($arr)
{
	switch (strtolower($arr[0])) {
		case 'today':
			return date('Y-m-d');

			break;
		case 'tomorrow':
			return date('Y-m-d', strtotime('+1 day'));

			break;
		case 'yesterday':
			return date('Y-m-d', strtotime('-1 day'));

			break;
		case 'time':
			return date('H:i:s');

			break;
		default:
			return date('Y-m-d');

			break;
	}
}

function __cb_format_date($arr)
{
	$fmt = empty($arr[1]) ? 'Y-m-d' : $arr[1];
	list($y, $m, $d) = explode('-', $arr[0]);
	$dt = mktime(0, 0, 0, $m, $d, $y);

	return date($fmt, $dt);
}

function __vt_add_time($arr)
{
	if (count($arr) > 1) {
		$baseTime = $arr[0];
		$minutes = $arr[1];
	} else {
		$baseTime = date('H:i:s');
		$minutes = $arr[0];
	}
	$endTime = strtotime("+${minutes} minutes", strtotime($baseTime));

	return date('H:i:s', $endTime);
}

function __vt_sub_time($arr)
{
	if (count($arr) > 1) {
		$baseTime = $arr[0];
		$minutes = $arr[1];
	} else {
		$baseTime = date('H:i:s');
		$minutes = $arr[0];
	}
	$endTime = strtotime("-${minutes} minutes", strtotime($baseTime));

	return date('H:i:s', $endTime);
}

/* get next date that falls on the closest given days
 * @param ISO start date "2017-06-16
 * @param comma separated string of month days "15,30"
 * @param comma separated string of ISO holiday dates
 * @param boolean 0 exclude saturday and sunday, 1 include them, default not included
 */
function __cb_next_date($arr)
{
	$startDate = new DateTime($arr[0]);
	$endDate = new DateTime(__vt_add_days([$arr[0], 180])); // 180 days to make sure we catch next occurrence
	$nextDays = explode(',', $arr[1]);
	if (isset($arr[2]) && trim($arr[2]) != '') { // list of holidays
		$holiday = explode(',', $arr[2]);
	} else {
		$holiday = [];
	}
	if (empty($arr[3])) { // include weekends or not
		$lastdow = 6;
	} else {
		$lastdow = 8;
	}
	$interval = new DateInterval('P1D'); // set the interval as 1 day
	$daterange = new DatePeriod($startDate, $interval, $endDate);
	$result = '';
	foreach ($daterange as $date) {
		if ($date->format('N') < $lastdow && ! in_array($date->format('Y-m-d'), $holiday)) {
			if (in_array($date->format('d'), $nextDays)) {
				$result = $date->format('Y-m-d');

				break;
			}
		}
	}

	return $result;
}

/* get next laborable date that falls after the closest given days
 * @param ISO start date "2017-06-16
 * @param comma separated string of month days "15,30"
 * @param comma separated string of ISO holiday dates
 * @param boolean 0 saturday is not laborable, 1 it is, default it isn't
 */
function __cb_next_dateLaborable($arr)
{
	$startDate = new DateTime($arr[0]);
	$endDate = new DateTime(__vt_add_days([$arr[0], 180])); // 180 days to make sure we catch next occurrence
	$nextDays = explode(',', $arr[1]);
	if (isset($arr[2]) && trim($arr[2]) != '') { // list of holidays
		$holiday = explode(',', $arr[2]);
	} else {
		$holiday = [];
	}
	if (empty($arr[3])) { // saturday is not laborable
		$weekend = [6, 7];
	} else {
		$weekend = [7];
	}
	$interval = new DateInterval('P1D'); // set the interval as 1 day
	$daterange = new DatePeriod($startDate, $interval, $endDate);
	$found = false;
	foreach ($daterange as $date) {
		if (in_array($date->format('d'), $nextDays)) {
			$found = $date;

			break;
		}
	}
	if ($found) {
		while ((in_array($found->format('N'), $weekend) || in_array($found->format('Y-m-d'), $holiday))) {
			$found->add($interval);
		}

		return $found->format('Y-m-d');
	} else {
		return '';
	}
}
