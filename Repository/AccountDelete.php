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
	public function findAccountsToRemind()
	{
		$finder = XF::app()->finder('LiamW\AccountDelete:AccountDelete')->where('status', 'pending')->where('reminder_sent', 0)->where('initiation_date', '<=', (XF::$time - (XF::options()->liamw_accountdelete_deletion_delay * 86400)) + (XF::options()->liamw_accountdelete_reminder_threshold * 86400));

		if (!XF::options()->liamw_accountdelete_reminder_threshold)
		{
			$finder->whereImpossible();
		}

		return $finder;
	}

	/**
	 * @return XF\Mvc\Entity\Finder
	 */
	public function findAccountsToDelete()
	{
		return XF::app()->finder('LiamW\AccountDelete:AccountDelete')->where('status', 'pending')
			->where('initiation_date', '<=', XF::$time - (XF::options()->liamw_accountdelete_deletion_delay * 86400));
	}

	public function getNextRemindTime()
	{
		if (!XF::options()->liamw_accountdelete_reminder_threshold)
		{
			return null;
		}

		$nextInitiationDate = $this->db()->fetchOne("SELECT MIN(initiation_date) FROM xf_liamw_accountdelete_account_deletions WHERE status='pending'");

		return $nextInitiationDate ? ($nextInitiationDate + (XF::options()->liamw_accountdelete_deletion_delay * 86400)) - (XF::options()->liamw_accountdelete_reminder_threshold * 86400) : null;
	}

	public function getNextDeletionTime()
	{
		$nextInitiationDate = $this->db()->fetchOne("SELECT MIN(initiation_date) FROM xf_liamw_accountdelete_account_deletions WHERE status='pending'");

		return $nextInitiationDate ? $nextInitiationDate + (XF::options()->liamw_accountdelete_deletion_delay * 86400) : null;
	}

	public function getDeletedUserUsername(User $user)
	{
		return XF::phrase('deleted_member') . " {$user->user_id}";
	}
}