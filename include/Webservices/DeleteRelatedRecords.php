<?php
/*
 * Copyright dogiCRM  --  This file is a part of dogiCRM.
 * You can copy, adapt and distribute the work under the "Attribution-NonCommercial-ShareAlike"
 * Vizsage Public License (the "License"). You may not use this file except in compliance with the
 * License. Roughly speaking, non-commercial users may share and modify this code, but must give credit
 * and share improvements. However, for proper details please read the full License, available at
 * http://vizsage.com/license/Vizsage-License-BY-NC-SA.html and the handy reference for understanding
 * the full license at http://vizsage.com/license/Vizsage-Deed-BY-NC-SA.html. Unless required by
 * applicable law or agreed to in writing, any software distributed under the License is distributed
 * on an  "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and limitations under the
 * License terms of Creative Commons Attribution-NonCommercial-ShareAlike 3.0 (the License).
 */

function vtws_delete_related($sourceRecordId, $relatedRecordId, $user = false)
{
	global $log,$adb;

	//Get source record id
	list($moduleSourceId, $elementSourceId) = vtws_getIdComponents($sourceRecordId);
	$webserviceObject = VtigerWebserviceObject::fromId($adb, $moduleSourceId);

	//Get instanes handlers
	$handlerPath = $webserviceObject->getHandlerPath();
	$handlerClass = $webserviceObject->getHandlerClass();

	require_once $handlerPath;
	$handler = new $handlerClass($webserviceObject, $user, $adb, $log);
	$meta = $handler->getMeta();

	//Get objet entity name from record Id.
	$sourceModuleName = $meta->getObjectEntityName($sourceRecordId);

	//Get module permssion for user.
	$types = vtws_listtypes(null, $user);

	//Check permissions for user
	if (! in_array($sourceModuleName, $types['types'])) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to perform the operation is denied');
	}

	//Check equal entites
	if ($sourceModuleName !== $webserviceObject->getEntityName()) {
		throw new WebServiceException(WebServiceErrorCode::$INVALIDID, 'Id specified is incorrect');
	}

	if (! $meta->hasPermission(EntityMeta::$UPDATE, $sourceRecordId)) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to read given object is denied');
	}

	//Check exist record id
	if (! $meta->exists($elementSourceId)) {
		throw new WebServiceException(WebServiceErrorCode::$RECORDNOTFOUND, 'Record you are trying to access is not found');
	}

	//Check record access
	if ($meta->hasWriteAccess() !== true) {
		throw new WebServiceException(WebServiceErrorCode::$ACCESSDENIED, 'Permission to write is denied');
	}

	//Get related record Id
	list($moduleRelatedId, $elementRelatedId) = vtws_getIdComponents($relatedRecordId);

	//Get instance for record id
	$relatedRecordInstance = Vtiger_Record_Model::getInstanceById($elementRelatedId);
	$relatedModuleName = $relatedRecordInstance->getModuleName();

	//Get instance for source module
	$sourceModuleFocus = CRMEntity::getInstance($sourceModuleName);

	if ($sourceModuleFocus && $relatedRecordInstance) {
		//Check modules (source / related) and apply remove link
		if ($sourceModuleName == 'Potentials' && $relatedModuleName == 'Products') {
			$query = 'DELETE FROM vtiger_seproductsrel WHERE crmid=? AND productid=? AND setype=?';
			$adb->pquery($query, [$elementSourceId, $elementRelatedId, 'Potentials']);
		} elseif ($sourceModuleName == 'Potentials' && $relatedModuleName == 'Contacts') {
			$query = 'DELETE FROM vtiger_contpotentialrel WHERE potentialid=? AND contactid=?';
			$adb->pquery($query, [$elementSourceId, $elementRelatedId]);
		} else {
			//Default method to remove link
			$sourceModuleFocus->delete_related_module($sourceModuleName, $elementSourceId, $relatedModuleName, $elementRelatedId);
		}
	}

	//Buffer save
	VTWS_PreserveGlobal::flush();

	return true;
}
