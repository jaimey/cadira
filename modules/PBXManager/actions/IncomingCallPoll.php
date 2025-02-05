<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */
include_once 'include/Webservices/Create.php';
include_once 'include/utils/utils.php';

class PBXManager_IncomingCallPoll_Action extends Vtiger_Action_Controller
{
	public function __construct()
	{
		$this->exposeMethod('searchIncomingCalls');
		$this->exposeMethod('createRecord');
		$this->exposeMethod('getCallStatus');
		$this->exposeMethod('checkModuleViewPermission');
		$this->exposeMethod('checkPermissionForPolling');
	}

	public function process(Vtiger_Request $request)
	{
		$mode = $request->getMode();
		if (! empty($mode) && $this->isMethodExposed($mode)) {
			$this->invokeExposedMethod($mode, $request);

			return;
		}
	}

	public function checkPermission(Vtiger_Request $request)
	{
		$moduleName = $request->getModule();
		$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$userPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$permission = $userPrivilegesModel->hasModulePermission($moduleModel->getId());

		if (! $permission) {
			throw new AppException('LBL_PERMISSION_DENIED');
		}
	}

	public function checkModuleViewPermission(Vtiger_Request $request)
	{
		$response = new Vtiger_Response();
		$modules = ['Contacts', 'Leads'];
		$view = $request->get('view');
		Users_Privileges_Model::getCurrentUserPrivilegesModel();
		foreach ($modules as $module) {
			if (Users_Privileges_Model::isPermitted($module, $view)) {
				$result['modules'][$module] = true;
			} else {
				$result['modules'][$module] = false;
			}
		}
		$response->setResult($result);
		$response->emit();
	}

	public function searchIncomingCalls(Vtiger_Request $request)
	{
		$recordModel = PBXManager_Record_Model::getCleanInstance();
		$response = new Vtiger_Response();
		$user = Users_Record_Model::getCurrentUserModel();

		$recordModels = $recordModel->searchIncomingCall();
		// To check whether user have permission on caller record
		if ($recordModels) {
			foreach ($recordModels as $recordModel) {
				// To check whether the user has permission to see contact name in popup
				$recordModel->set('callername', null);

				if ($user->id != $recordModel->get('user')) {
					continue;
				}
				$callerid = $recordModel->get('customer');

				if ($callerid && isRecordExists($callerid)) {
					$moduleName = $recordModel->get('customertype');

					$callerRecordModel = Vtiger_Record_Model::getInstanceById($callerid, $moduleName);
					$ownerId = $callerRecordModel->get('assigned_user_id');
					$ownername = (getUserFullName($ownerId) != '') ? getUserFullName($ownerId) : getGroupFullName($ownerId);
					$recordModel->set('ownername', $ownername);

					if (! Users_Privileges_Model::isPermitted($moduleName, 'DetailView', $callerid)) {
						$name = $recordModel->get('customernumber').vtranslate('LBL_HIDDEN', 'PBXManager');
						$recordModel->set('callername', $name);
					} else {
						$entityNames = getEntityName($moduleName, [$callerid]);
						$callerName = $entityNames[$callerid];
						$recordModel->set('callername', $callerName);
					}
				} else {
					$recordModel->set('customer', null);
				}

				$direction = $recordModel->get('direction');
				if ($direction == 'inbound') {
					$userid = $recordModel->get('user');
					if ($userid) {
						$entityNames = getEntityName('Users', [$userid]);
						$userName = $entityNames[$userid];
						$recordModel->set('answeredby', $userName);
					}
				}
				$recordModel->set('current_user_id', $user->id);
				$calls[] = $recordModel->getData();
			}
		}
		$response->setResult($calls);
		$response->emit();
	}

	public function createRecord(Vtiger_Request $request)
	{
		$user = Users_Record_Model::getCurrentUserModel();
		$moduleName = $request->get('modulename');
		$name = explode('@', $request->get('email'));
		$element['lastname'] = $request->get('lastname');
		$element['firstname'] = $request->get('firstname');
		$element['email'] = $request->get('email');
		$element['phone'] = $request->get('number');
		$element['assigned_user_id'] = vtws_getWebserviceEntityId('Users', $user->id);

		$moduleInstance = Vtiger_Module_Model::getInstance($moduleName);
		$mandatoryFieldModels = $moduleInstance->getMandatoryFieldModels();
		foreach ($mandatoryFieldModels as $mandatoryField) {
			$fieldName = $mandatoryField->get('name');
			$fieldType = $mandatoryField->getFieldDataType();
			$defaultValue = decode_html($mandatoryField->get('defaultvalue'));
			if (! empty($element[$fieldName])) {
				continue;
			} else {
				$fieldValue = $defaultValue;
				if (empty($fieldValue)) {
					$fieldValue = Vtiger_Util_Helper::getDefaultMandatoryValue($fieldType);
				}
				$element[$fieldName] = $fieldValue ;
			}
		}

		$entity = vtws_create($moduleName, $element, $user);
		$this->updateCustomerInPhoneCalls($entity, $request);
		$response = new Vtiger_Response();
		$response->setResult(true);
		$response->emit();
	}

	public function updateCustomerInPhoneCalls($customer, $request)
	{
		$id = vtws_getIdComponents($customer['id']);
		$sourceuuid = $request->get('callid');
		$module = $request->get('modulename');

		$currentUser = Users_Record_Model::getCurrentUserModel();
		$user = ['id' => $currentUser->id];
		$recordModel = PBXManager_Record_Model::getInstanceBySourceUUID($sourceuuid, $user);
		$recordModel->updateCallDetails(['customer'=>$id[1], 'customertype'=>$module], $user);
	}

	public function getCallStatus($request)
	{
		$phonecallsid = $request->get('callid');
		$recordModel = PBXManager_Record_Model::getInstanceById($phonecallsid);
		$response = new Vtiger_Response();
		$response->setResult($recordModel->get('callstatus'));
		$response->emit();
	}

	public function checkPermissionForPolling(Vtiger_Request $request)
	{
		Users_Privileges_Model::getCurrentUserPrivilegesModel();
		$callPermission = Users_Privileges_Model::isPermitted('PBXManager', 'ReceiveIncomingCalls');

		$serverModel = PBXManager_Server_Model::getInstance();
		$gateway = $serverModel->get('gateway');

		$user = Users_Record_Model::getCurrentUserModel();
		$userNumber = $user->phone_crm_extension;

		$result = false;
		if ($callPermission && $userNumber && $gateway) {
			$result = true;
		}

		$response = new Vtiger_Response();
		$response->setResult($result);
		$response->emit();
	}
}
