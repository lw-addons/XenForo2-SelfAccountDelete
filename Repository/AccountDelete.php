<?php

namespace LiamW\AccountDelete\Repository;

use XF;
use XF\Entity\User;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

class AccountDelete extends Repository
{
	/**
	 * @return \LiamW\AccountDelete\Entity\AccountDelete[]|ArrayCollection
	 */
	public function getAccountsToDelete()
	{
		return XF::app()->finder('LiamW\AccountDelete:AccountDelete')->where('status', 'pending')
				  ->where('initiation_date', '<=', XF::$time - (XF::options()->liamw_accountdelete_deletion_delay * 86400))
				  ->fetch();
	}

	public function getRandomisedUsername(User $user)
	{
		return 'deleted' . $user->user_id;
	}
}