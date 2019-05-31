<?php

namespace LiamW\AccountDelete\Entity;

use XF;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 *
 * @property int deletion_id
 * @property int user_id
 * @property string username
 * @property string|null reason
 * @property int initiation_date
 * @property int|null completion_date
 * @property string status
 *
 * GETTERS
 * @property mixed end_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class AccountDelete extends Entity
{
	protected function _setupDefaults()
	{
		$this->username = $this->_getDeferredValue(function()
		{
			return $this->User->username;
		});
	}

	public function getEndDate()
	{
		return $this->initiation_date + (XF::options()->liamw_accountdelete_deletion_delay * 86400); // 86400 seconds in a day
	}

	protected final function _preDelete()
	{
		throw new \BadMethodCallException("account deletion records cannot be deleted");
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_liamw_accountdelete_account_deletions';
		$structure->primaryKey = 'deletion_id';
		$structure->shortName = 'LiamW\AccountDelete:AccountDelete';
		$structure->columns = [
			'deletion_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'username' => ['type' => self::STR, 'required' => true, 'maxLength' => 50],
			'reason' => ['type' => self::STR, 'nullable' => true],
			'initiation_date' => ['type' => self::UINT, 'default' => XF::$time],
			'completion_date' => ['type' => self::UINT, 'nullable' => true],
			'status' => ['type' => self::STR, 'allowedValues' => ['pending', 'complete', 'complete_manual', 'cancelled']],
		];
		$structure->getters = [
			'end_date' => true
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			]
		];

		$structure->defaultWith = ['User'];

		return $structure;
	}
}