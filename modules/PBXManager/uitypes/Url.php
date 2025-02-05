<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: SalesPlatform Ltd
 * The Initial Developer of the Original Code is SalesPlatform Ltd.
 * All Rights Reserved.
 * If you have any questions or comments, please email: devel@salesplatform.ru
 */

class PBXManager_Url_UIType extends Vtiger_Base_UIType
{
	/**
	 * Function to get the Template name for the current UI Type object
	 * @return string <String> - Template Name
	 */
	public function getTemplateName()
	{
		return 'uitypes/Url.tpl';
	}

	public function getDisplayValue($value)
	{
		return $value;
	}
}
