<?php

namespace LiamW\AccountDelete\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string user_id
 * @property int initiate_date
 *
 * GETTERS
 * @property mixed end_date
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class AccountDelete extends Entity
{
	public function getEndDate()
	{
		return $this->initiate_date + (\XF::options()->liamw_accountdelete_deletion_delay * 86400); // 86400 seconds in a day
	}

	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_liamw_accountdelete_pending';
		$structure->primaryKey = 'user_id';
		$structure->shortName = 'LiamW\AccountDelete:AccountDelete';
		$structure->columns = [
			'user_id' => ['type' => self::STR, 'required' => true],
			'initiate_date' => ['type' => self::UINT, 'default' => \XF::$time]
		];
		$structure->getters = [
			'end_date' => true
		];
		$structure->relations = [
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id'
			]
		];

		return $structure;
	}
}