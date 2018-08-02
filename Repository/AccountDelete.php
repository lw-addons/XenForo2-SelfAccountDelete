<?php

namespace LiamW\AccountDelete\Repository;

use XF\Entity\User;
use XF\Mvc\Entity\Repository;

class AccountDelete extends Repository
{
	/**
	 * @return \LiamW\AccountDelete\Entity\AccountDelete[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getAccountsToDelete()
	{
		return \XF::app()->finder('LiamW\AccountDelete:AccountDelete')
				  ->where('initiate_date', '<=', \XF::$time - (\XF::options()->liamw_accountdelete_deletion_delay * 86400))
				  ->fetch();
	}

	public function getRandomisedUsername(User $user)
	{
		return 'deleted' . $user->user_id;
	}
}