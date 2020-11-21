<?php
/*+*******************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */
require_once 'include/events/SqlResultIterator.inc';
class VTExpressionsManager
{
	/** Caching logic */
	private static $cache = [];

	public function __construct($adb)
	{
		$this->adb = $adb;
	}

	public static function addToCache($key, $value)
	{
		self::$cache[$key] = $value;
	}

	public static function fromCache($key)
	{
		if (isset(self::$cache[$key])) {
			return self::$cache[$key];
		}

		return false;
	}

	public static function clearCache()
	{
		self::$cache = [];
	}

	/** END */
	public function fields($moduleName)
	{
		global $current_user;
		$result = vtws_describe($moduleName, $current_user);
		$fields = $result['fields'];
		$arr = [];
		foreach ($fields as $field) {
			$arr[$field['name']] = $field['label'];
		}

		return $arr;
	}

	public function expressionFunctions()
	{
		$return = [
			'concat'                       => 'concat(a,b)',
			'coalesce'                     => 'coalesce(a,...,n)',
			'time_diffdays(a,b)'           => 'time_diffdays(a,b)',
			'time_diffdays(a)'             => 'time_diffdays(a)',
			'time_diffweekdays(a,b)'       => 'time_diffweekdays(a,b)',
			'time_diffweekdays(a)'         => 'time_diffweekdays(a)',
			'time_diff(a,b)'               => 'time_diff(a,b)',
			'time_diff(a)'                 => 'time_diff(a)',
			'add_days'                     => 'add_days(datefield, noofdays)',
			'sub_days'                     => 'sub_days(datefield, noofdays)',
			'add_time(timefield, minutes)' => 'add_time(timefield, minutes)',
			'sub_time(timefield, minutes)' => 'sub_time(timefield, minutes)',
			'today'                        => "get_date('today')",
			'tomorrow'                     => "get_date('tomorrow')",
			'yesterday'                    => "get_date('yesterday')",
			'time'                         => "get_date('time')",
			'format_date'                  => 'format_date(date,format)',
			'next_date'                    => 'get_nextdate(startDate,days,holidays,include_weekend)',
			'next_date_laborable'          => 'get_nextdatelaborable(startDate,days,holidays,saturday_laborable)',
			'stringposition'               => 'stringposition(haystack,needle)',
			'stringlength'                 => 'stringlength(string)',
			'stringreplace'                => 'stringreplace(search,replace,subject)',
			'power(base,exponential)'      => 'power(base,exponential)',
			'substring'                    => 'substring(stringfield,start,end)',
			'uppercase'                    => 'uppercase(stringfield)',
			'lowercase'                    => 'lowercase(stringfield)',
			'uppercasefirst'               => 'uppercasefirst(stringfield)',
			'uppercasewords'               => 'uppercasewords(stringfield)',
			'round'                        => 'round(numericfield,decimals)',
			'ceil'                         => 'ceil(numericfield)',
			'floor'                        => 'floor(numericfield)',
			'modulo'                       => 'modulo(numericfield)',
			'hash'                         => 'hash(field, method)',
		];

		return $return;
	}
}
