<?php
/*+*******************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 */
include_once 'vtlib/Vtiger/Utils.php';
require_once 'includes/runtime/Cache.php';

/**
 * Provides API to work with vtiger CRM Module Blocks
 * @package vtlib
 */
class Vtiger_Block
{
	/** ID of this block instance */
	public $id;
	/** Label for this block instance */
	public $label;
	public $sequence;
	public $showtitle = 0;
	public $visible = 0;
	public $increateview = 0;
	public $ineditview = 0;
	public $indetailview = 0;
	public $display_status = 1;
	public $iscustom = 0;
	public $module;

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Get unquie id for this instance
	 * @access private
	 */
	public function __getUniqueId()
	{
		global $adb;

		/** Sequence table was added from 5.1.0 */
		$maxblockid = $adb->getUniqueID('vtiger_blocks');

		return $maxblockid;
	}

	/**
	 * Get next sequence value to use for this block instance
	 * @access private
	 */
	public function __getNextSequence()
	{
		global $adb;
		$result = $adb->pquery('SELECT MAX(sequence) as max_sequence from vtiger_blocks where tabid = ?', [$this->module->id]);
		$maxseq = 0;
		if ($adb->num_rows($result)) {
			$maxseq = $adb->query_result($result, 0, 'max_sequence');
		}

		return ++$maxseq;
	}

	/**
	 * Create vtiger CRM block
	 * @access private
	 * @param mixed $moduleInstance
	 */
	public function __create($moduleInstance)
	{
		global $adb;

		$this->module = $moduleInstance;

		$this->id = $this->__getUniqueId();
		if (! $this->sequence) {
			$this->sequence = $this->__getNextSequence();
		}

		$sql = 'INSERT INTO vtiger_blocks(blockid,tabid,blocklabel,sequence,show_title,visible,create_view,edit_view,detail_view,iscustom)
					VALUES(?,?,?,?,?,?,?,?,?,?)';

		$adb->pquery($sql, [$this->id, $this->module->id, $this->label, $this->sequence,
			$this->showtitle, $this->visible, $this->increateview, $this->ineditview, $this->indetailview, $this->iscustom]);

		self::log("Creating Block {$this->label} ... DONE");
		self::log("Module language entry for {$this->label} ... CHECK");
	}

	/**
	 * Update vtiger CRM block
	 * @access private
	 * @internal TODO
	 */
	public function __update()
	{
		self::log("Updating Block {$this->label} ... DONE");
	}

	/**
	 * Delete this instance
	 * @access private
	 */
	public function __delete()
	{
		global $adb;
		self::log("Deleting Block {$this->label} ... ", false);
		$adb->pquery('DELETE FROM vtiger_blocks WHERE blockid=?', [$this->id]);
		self::log('DONE');
	}

	/**
	 * Initialize this block instance
	 * @param Array Map of column name and value
	 * @param Vtiger_Module Instance of module to which this block is associated
	 * @param mixed $valuemap
	 * @param mixed $moduleInstance
	 * @access private
	 */
	public function initialize($valuemap, $moduleInstance = false)
	{
		$this->id = $valuemap['blockid'];
		$this->label = $valuemap['blocklabel'];
		$this->display_status = $valuemap['display_status'];
		$this->sequence = $valuemap['sequence'];
		$this->iscustom = $valuemap['iscustom'];
		$this->module = $moduleInstance ? $moduleInstance : Vtiger_Module::getInstance($valuemap['tabid']);
	}

	/**
	 * Save this block instance
	 * @param Vtiger_Module Instance of the module to which this block is associated
	 * @param mixed $moduleInstance
	 */
	public function save($moduleInstance = false)
	{
		if ($this->id) {
			$this->__update();
		} else {
			$this->__create($moduleInstance);
		}

		return $this->id;
	}

	/**
	 * Delete block instance
	 * @param Boolean True to delete associated fields, False to avoid it
	 * @param mixed $recursive
	 */
	public function delete($recursive = true)
	{
		if ($recursive) {
			$fields = Vtiger_Field::getAllForBlock($this);
			if (! empty($fields)) {
				foreach ($fields as $fieldInstance) {
					$fieldInstance->delete($recursive);
				}
			}
		}
		$this->__delete();
	}

	/**
	 * Add field to this block
	 * @param Vtiger_Field Instance of field to add to this block.
	 * @param mixed $fieldInstance
	 * @return Reference to this block instance
	 */
	public function addField($fieldInstance)
	{
		$fieldInstance->save($this);

		return $this;
	}

	/**
	 * Helper function to log messages
	 * @param String Message to log
	 * @param Boolean true appends linebreak, false to avoid it
	 * @param mixed $message
	 * @param mixed $delim
	 * @access private
	 */
	public static function log($message, $delim = true)
	{
		Vtiger_Utils::Log($message, $delim);
	}

	/**
	 * Get instance of block
	 * @param mixed block id or block label
	 * @param Vtiger_Module Instance of the module if block label is passed
	 * @param mixed $value
	 * @param mixed $moduleInstance
	 */
	public static function getInstance($value, $moduleInstance = false)
	{
		global $adb;
		$cache = Vtiger_Cache::getInstance();
		if ($moduleInstance && $cache->getBlockInstance($value, $moduleInstance->id)) {
			return $cache->getBlockInstance($value, $moduleInstance->id);
		} else {
			$instance = false;
			$query = false;
			$queryParams = false;
			if (Vtiger_Utils::isNumber($value)) {
				$query = 'SELECT * FROM vtiger_blocks WHERE blockid=?';
				$queryParams = [$value];
			} else {
				$query = 'SELECT * FROM vtiger_blocks WHERE blocklabel=? AND tabid=?';
				$queryParams = [$value, $moduleInstance->id];
			}
			$result = $adb->pquery($query, $queryParams);
			if ($adb->num_rows($result)) {
				$instance = new self();
				$instance->initialize($adb->fetch_array($result), $moduleInstance);
			}
			$cache->setBlockInstance($value, $instance->module->id, $instance);

			return $instance;
		}
	}

	/**
	 * Get all block instances associated with the module
	 * @param Vtiger_Module Instance of the module
	 * @param mixed $moduleInstance
	 */
	public static function getAllForModule($moduleInstance)
	{
		global $adb;
		$instances = false;

		$query = 'SELECT * FROM vtiger_blocks WHERE tabid=? ORDER BY sequence';
		$queryParams = [$moduleInstance->id];

		$result = $adb->pquery($query, $queryParams);
		for ($index = 0; $index < $adb->num_rows($result); ++$index) {
			$instance = new self();
			$instance->initialize($adb->fetch_array($result), $moduleInstance);
			$instances[] = $instance;
		}

		return $instances;
	}

	/**
	 * Delete all blocks associated with module
	 * @param Vtiger_Module Instnace of module to use
	 * @param Boolean true to delete associated fields, false otherwise
	 * @param mixed $moduleInstance
	 * @param mixed $recursive
	 * @access private
	 */
	public static function deleteForModule($moduleInstance, $recursive = true)
	{
		global $adb;
		if ($recursive) {
			Vtiger_Field::deleteForModule($moduleInstance);
		}
		$adb->pquery('DELETE FROM vtiger_blocks WHERE tabid=?', [$moduleInstance->id]);
		self::log('Deleting blocks for module ... DONE');
	}
}
