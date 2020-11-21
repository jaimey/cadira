<?php
/*+*******************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */
interface VTExpressionEnv
{
	public function get($var);
}

/** END * */
class VTFieldExpressionEvaluater
{
	public function __construct($expr)
	{
		$this->operators = [
			'+'  => '__vt_add',
			'-'  => '__vt_sub',
			'*'  => '__vt_mul',
			'/'  => '__vt_div',
			'==' => '__vt_equals',
			'!=' => '__cb_distinct',
			'<=' => '__vt_ltequals',
			'>=' => '__vt_gtequals',
			'<'  => '__vt_lt',
			'>'  => '__vt_gt',
		];

		$this->functions = [
			'concat'        => '__vt_concat',
			'coalesce'      => '__vt_coalesce',
			'time_diff'     => '__vt_time_diff',
			'time_diffdays' => '__vt_time_diffdays',
			'time_diffweekdays' => '__cb_getWeekdayDifference',
			'add_days'     => '__vt_add_days',
			'sub_days'     => '__vt_sub_days',
			'get_date'     => '__vt_get_date',
			'get_nextdate' => '__vt_next_date',
			'get_nextdatelaborable' => '__cb_next_dateLaborable',
			'format_date' => '__cb_format_date',
			'add_time'    => '__vt_add_time',
			'sub_time'    => '__vt_sub_time',
			'power'	      => '__vt_power',
			'substring'   => '__vt_substring',
			'stringposition' => '__cb_stringposition',
			'stringlength' => '__vt_stringlength',
			'stringreplace' => '__cb_stringreplace',
			'uppercase'      => '__vt_uppercase',
			'lowercase'      => '__vt_lowercase',
			'uppercasefirst' => '__vt_uppercasefirst',
			'uppercasewords' => '__vt_uppercasewords',
			'round' => '__vt_round',
			'ceil'  => '__vt_ceil',
			'floor' => '__vt_floor',
			'modulo' => '__cb_modulo',
			'hash' => '__cb_hash',
		];

		$this->operations = array_merge($this->functions, $this->operators);
		$this->expr = $expr;
	}

	public function evaluate($env)
	{
		$this->env = $env;

		return $this->exec($this->expr);
	}

	public function exec($expr)
	{
		global $logbg;
		if ($expr instanceof VTExpressionSymbol) {
			$loginfo = print_r($expr, true);
			$logbg->debug("VTExpressionSymbol: ${loginfo}");

			return $this->env($expr);
		} elseif ($expr instanceof VTExpressionTreeNode) {
			$op = $expr->getName();
			if ($op->value == 'if') {
				$params = $expr->getParams();
				$cond = $this->exec($params[0]);
				$loginfo = print_r($params, true);
				$logbg->debug("VTExpressionTreeNode: if ${loginfo}");
				$logbg->debug('VTExpressionTreeNode: condition => '.($cond ? 'true' : 'false'));
				if ($cond) {
					return $this->exec($params[1]);
				} else {
					return $this->exec($params[2]);
				}
			} else {
				$params = array_map([$this, 'exec'], $expr->getParams());
				$func = $this->operations[$op->value];
				$loginfo = print_r($params, true);
				$logbg->debug("VTExpressionTreeNode: function ${func} ${loginfo}");

				return $func($params);
			}
		} else {
			$loginfo = print_r($expr, true);
			$logbg->debug("VTExpression: ${loginfo}");

			return $expr;
		}
	}

	public function env($sym)
	{
		if ($this->env) {
			global $current_user;
			$fieldName = $sym->value;
			//if the field is reference fields field name
			// format (account_id : (Accounts) phone)
			preg_match('/\((\w+) : \((\w+)\) (\w+)\)/', $fieldName, $matches);
			if (count($matches) > 0) {
				//reference field update
				$referenceField = $matches[1];
				$referencedModule = $matches[2];
				$referencedFieldName = $matches[3];
				$referenceRecordId = $this->env->get($referenceField);
				if (empty($referenceRecordId)) {
					return '';
				} else {
					global $current_user;
					$referenceRecordEntity = VTEntityCache::getCachedEntity($referenceRecordId);
					if (empty($referenceRecordEntity)) {
						$referenceEntity = new VTEntityCache($current_user);
						$referenceRecordEntity = $referenceEntity->forId($referenceRecordId);
						VTEntityCache::setCachedEntity($referenceRecordId, $referenceRecordEntity);
					}
					$referenceModuleHandler = vtws_getModuleHandlerFromName($referencedModule, $current_user);
					$referenceModuleMeta = $referenceModuleHandler->getMeta();
					$referenceModuleFields = $referenceModuleMeta->getModuleFields();
					$referenceFieldInstance = $referenceModuleFields[$referencedFieldName];
					if (! empty($referenceFieldInstance) && ($referenceFieldInstance->getFieldDataType() == 'reference')) {
						$referenceWsRecordId = $referenceRecordEntity->get($referencedFieldName);
						$referenceRecordIdComponents = vtws_getIdComponents($referenceWsRecordId);
						$referenceRecordId = $referenceRecordIdComponents[1];
						if (! empty($referenceRecordId)) {
							$referenceList = $referenceFieldInstance->getReferenceList();
							if ((count($referenceList) == 1) && $referenceList[0] == 'Users') {
								$userRecordLabels = Vtiger_Functions::getOwnerRecordLabels($referenceRecordId);

								return $userRecordLabels[$referenceRecordId];
							}

							return Vtiger_Util_Helper::getRecordName($referenceRecordId);
						}

						return $referenceRecordId;
					}

					if ($referenceFieldInstance && $referenceFieldInstance->getUIType() == '72') {
						$currencyConversionRate = $referenceRecordEntity->get('conversion_rate');
						if (! empty($currencyConversionRate)) {
							if (! empty($this->fieldInstance) && $this->fieldInstance->getFieldDataType() == 'currency' && $this->fieldInstance->getUIType() != 72) {
								$rawBaseCurrencyValue = CurrencyField::convertToDollar($referenceRecordEntity->get($referencedFieldName), $currencyConversionRate);

								return $rawBaseCurrencyValue;
							}
						}
					}

					return $referenceRecordEntity->get($referencedFieldName);
				}
			}

			$moduleName = $this->env->getModuleName();
			$moduleHandler = vtws_getModuleHandlerFromName($moduleName, $current_user);
			$handlerMeta = $moduleHandler->getMeta();
			$moduleFields = $handlerMeta->getModuleFields();
			$fieldInstance = $moduleFields[$sym->value];
			if (! empty($fieldInstance) && ($fieldInstance->getFieldDataType() == 'reference')) {
				$referenceWsRecordId = $this->env->get($sym->value);
				$referenceRecordIdComponents = vtws_getIdComponents($referenceWsRecordId);
				$referenceRecordId = $referenceRecordIdComponents[1];
				if (! empty($referenceRecordId)) {
					$referenceList = $fieldInstance->getReferenceList();
					if ((count($referenceList) == 1) && $referenceList[0] == 'Users') {
						$userRecordLabels = Vtiger_Functions::getOwnerRecordLabels($referenceRecordId);

						return $userRecordLabels[$referenceRecordId];
					}

					return Vtiger_Util_Helper::getRecordName($referenceRecordId);
				}

				return $referenceRecordId;
			}

			if (! empty($fieldInstance) && $fieldInstance->getUIType() == '72') {
				$currencyConversionRate = $this->env->get('conversion_rate');
				if (! empty($currencyConversionRate)) {
					if (! empty($this->fieldInstance) && $this->fieldInstance->getFieldDataType() == 'currency' && $this->fieldInstance->getUIType() != 72) {
						$rawBaseCurrencyValue = CurrencyField::convertToDollar($this->env->get($sym->value), $currencyConversionRate);

						return $rawBaseCurrencyValue;
					}
				}
			}

			return $this->env->get($sym->value);
		} else {
			return $sym->value;
		}
	}
}

// Load expression functions
foreach (glob('modules/com_vtiger_workflow/expression_functions/*.{php}', GLOB_BRACE) as $fcode) {
	include $fcode;
}