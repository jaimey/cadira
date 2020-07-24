<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Products_Detail_View extends Vtiger_Detail_View
{
	public function __construct()
	{
		parent::__construct();
		$this->exposeMethod('showBundleTotalCostView');
	}

	public function requiresPermission(Vtiger_Request $request)
	{
		$permissions = parent::requiresPermission($request);
		$mode = $request->getMode();
		if (! empty($mode)) {
			switch ($mode) {
				case 'showBundleTotalCostView':
					$permissions[] = ['module_parameter' => 'module', 'action' => 'DetailView', 'record_parameter' => 'record'];
					$permissions[] = ['module_parameter' => 'relatedModule', 'action' => 'DetailView'];

					break;
			}
		}

		return $permissions;
	}

	public function showModuleSummaryView($request)
	{
		$recordId = $request->get('record');
		$moduleName = $request->getModule();

		if (! $this->record) {
			$this->record = Vtiger_DetailView_Model::getInstance($moduleName, $recordId);
		}
		$recordModel = $this->record->getRecord();
		$recordStrucure = Vtiger_RecordStructure_Model::getInstanceFromRecordModel($recordModel, Vtiger_RecordStructure_Model::RECORD_STRUCTURE_MODE_SUMMARY);

		$moduleModel = $recordModel->getModule();
		$viewer = $this->getViewer($request);
		$viewer->assign('RECORD', $recordModel);
		$viewer->assign('BLOCK_LIST', $moduleModel->getBlocks());
		$viewer->assign('USER_MODEL', Users_Record_Model::getCurrentUserModel());

		$viewer->assign('MODULE_NAME', $moduleName);
		$viewer->assign('IS_AJAX_ENABLED', $this->isAjaxEnabled($recordModel));
		$viewer->assign('SUMMARY_RECORD_STRUCTURE', $recordStrucure->getStructure());
		$viewer->assign('RELATED_ACTIVITIES', $this->getActivities($request));

		return $viewer->view('ModuleSummaryView.tpl', $moduleName, true);
	}

	public function preProcess(Vtiger_Request $request, $display = true)
	{
		$recordId = $request->get('record');
		$moduleName = $request->getModule();

		$recordModel = Vtiger_Record_Model::getInstanceById($recordId, $moduleName);
		$baseCurrenctDetails = $recordModel->getBaseCurrencyDetails();

		$viewer = $this->getViewer($request);
		$viewer->assign('BASE_CURRENCY_SYMBOL', $baseCurrenctDetails['symbol']);

		parent::preProcess($request, $display);
	}

	public function showModuleDetailView(Vtiger_Request $request)
	{
		$recordId = $request->get('record');
		$moduleName = $request->getModule();

		$recordModel = Vtiger_Record_Model::getInstanceById($recordId, $moduleName);
		$baseCurrenctDetails = $recordModel->getBaseCurrencyDetails();

		$viewer = $this->getViewer($request);
		$viewer->assign('BASE_CURRENCY_SYMBOL', $baseCurrenctDetails['symbol']);
		$viewer->assign('TAXCLASS_DETAILS', $recordModel->getTaxClassDetails());
		$viewer->assign('IMAGE_DETAILS', $recordModel->getImageDetails());

		return parent::showModuleDetailView($request);
	}

	public function getOverlayHeaderScripts(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$moduleDetailFile = 'modules.'.$moduleName.'.resources.Detail';
		$jsFileNames = [
			'~libraries/jquery/boxslider/jquery.bxslider.min.js',
			'modules.PriceBooks.resources.Detail',
		];
		$jsFileNames[] = $moduleDetailFile;

		return $this->checkAndConvertJsScripts($jsFileNames);
	}

	public function getHeaderScripts(Vtiger_Request $request)
	{
		$headerScriptInstances = parent::getHeaderScripts($request);
		$moduleName = $request->getModule();
		$moduleDetailFile = 'modules.'.$moduleName.'.resources.Detail';
		$moduleRelatedListFile = 'modules.'.$moduleName.'.resources.RelatedList';
		unset($headerScriptInstances[$moduleDetailFile], $headerScriptInstances[$moduleRelatedListFile]);

		$jsFileNames = [
			'~libraries/jquery/jquery.cycle.min.js',
			'~libraries/jquery/boxslider/jquery.bxslider.min.js',
			'modules.PriceBooks.resources.Detail',
			'modules.PriceBooks.resources.RelatedList',
		];

		$jsFileNames[] = $moduleDetailFile;
		$jsFileNames[] = $moduleRelatedListFile;

		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);

		return array_merge($headerScriptInstances, $jsScriptInstances);
	}

	public function showBundleTotalCostView(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$relatedModuleName = $request->get('relatedModule');
		$parentRecordId = $request->get('record');
		$tabLabel = $request->get('tabLabel');

		if ($moduleName === $relatedModuleName && $tabLabel === 'Product Bundles') {//Products && Child Products
			$parentRecordModel = Vtiger_Record_Model::getInstanceById($parentRecordId, $moduleName);
			$parentModuleModel = $parentRecordModel->getModule();
			$parentRecordModel->set('currency_id', getProductBaseCurrency($parentRecordId, $parentModuleModel->getName()));

			$subProductsCostsInfo = $parentRecordModel->getSubProductsCostsAndTotalCostInUserCurrency();
			$subProductsTotalCost = $subProductsCostsInfo['subProductsTotalCost'];
			$subProductsCostsInfo = $subProductsCostsInfo['subProductsCosts'];

			$viewer = $this->getViewer($request);
			$viewer->assign('MODULE', $moduleName);
			$viewer->assign('TAB_LABEL', $tabLabel);
			$viewer->assign('PARENT_RECORD', $parentRecordModel);
			$viewer->assign('SUB_PRODUCTS_TOTAL_COST', $subProductsTotalCost);
			$viewer->assign('SUB_PRODUCTS_COSTS_INFO', $subProductsCostsInfo);
			$viewer->assign('USER_MODEL', Users_Record_Model::getCurrentUserModel());

			return $viewer->view('BundleCostView.tpl', $moduleName, 'true');
		}
	}
}
