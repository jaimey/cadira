<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */

function getAdvancedSearchCriteriaList($advft_criteria, $advft_criteria_groups, $module = '')
{
	global $currentModule, $current_user;
	if (empty($module)) {
		$module = $currentModule;
	}

	$advfilterlist = [];
	$moduleHandler = vtws_getModuleHandlerFromName($module, $current_user);
	$moduleMeta = $moduleHandler->getMeta();
	$moduleFields = $moduleMeta->getModuleFields();

	if (is_array($advft_criteria)) {
		foreach ($advft_criteria as $column_condition) {
			if (empty($column_condition)) {
				continue;
			}

			$adv_filter_column = $column_condition['columnname'];
			$adv_filter_comparator = $column_condition['comparator'];
			$adv_filter_value = $column_condition['value'];
			$adv_filter_column_condition = $column_condition['columncondition'];
			$adv_filter_groupid = $column_condition['groupid'];

			$column_info = explode(':', $adv_filter_column);

			$fieldName = $column_info[2];
			$fieldObj = $moduleFields[$fieldName];
			if (is_object($fieldObj)) {
				$fieldType = $fieldObj->getFieldDataType();

				if ($fieldType == 'currency') {
					// Some of the currency fields like Unit Price, Total, Sub-total etc of Inventory modules, do not need currency conversion
					if ($fieldObj->getUIType() == '72') {
						$adv_filter_value = CurrencyField::convertToDBFormat($adv_filter_value, null, true);
					} else {
						$currencyField = new CurrencyField($adv_filter_value);
						if ($module == 'Potentials' && $fieldName == 'amount') {
							$currencyField->setNumberofDecimals(2);
						}
						$adv_filter_value = $currencyField->getDBInsertedValue();
					}
				}
			}
			$criteria = [];
			$criteria['columnname'] = $adv_filter_column;
			$criteria['comparator'] = $adv_filter_comparator;
			$criteria['value'] = $adv_filter_value;
			$criteria['column_condition'] = $adv_filter_column_condition;

			$advfilterlist[$adv_filter_groupid]['columns'][] = $criteria;
		}
	}
	if (is_array($advft_criteria_groups)) {
		foreach ($advft_criteria_groups as $group_index => $group_condition_info) {
			if (empty($group_condition_info)) {
				continue;
			}
			if (empty($advfilterlist[$group_index])) {
				continue;
			}
			$advfilterlist[$group_index]['condition'] = $group_condition_info['groupcondition'];
			$noOfGroupColumns = count($advfilterlist[$group_index]['columns']);
			if (! empty($advfilterlist[$group_index]['columns'][$noOfGroupColumns - 1]['column_condition'])) {
				$advfilterlist[$group_index]['columns'][$noOfGroupColumns - 1]['column_condition'] = '';
			}
		}
	}
	$noOfGroups = count($advfilterlist);
	if (! empty($advfilterlist[$noOfGroups]['condition'])) {
		$advfilterlist[$noOfGroups]['condition'] = '';
	}

	return $advfilterlist;
}
