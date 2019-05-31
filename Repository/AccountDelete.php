<?php

namespace LiamW\AccountDelete\Repository;

use XF;
use XF\Entity\User;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

class AccountDelete extends Repository
{
	/**
	 * @return XF\Mvc\Entity\Finder
	 */
	public function findAccountsToDelete()
	{
		return XF::app()->finder('LiamW\AccountDelete:AccountDelete')->where('status', 'pending')
				  ->where('initiation_date', '<=', XF::$time - (XF::options()->liamw_accountdelete_deletion_delay * 86400));
	}

	public function getDeletedUserUsername(User $user)
	{
		return XF::phrase('deleted_member') . " {$user->user_id}";
	}
}