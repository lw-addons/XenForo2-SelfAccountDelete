<?php

namespace LiamW\AccountDelete\Job;

use XF\Job\AbstractJob;

class DeleteAccounts extends AbstractJob
{
	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$toDelete = \XF::repository('LiamW\AccountDelete:AccountDelete')->getAccountsToDelete();

		foreach ($toDelete AS $item)
		{
			\XF::service('LiamW\AccountDelete:AccountDelete', $item->User)->executeDeletion();

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				return $this->resume();
			}
		}

		return $this->complete();
	}

	public function getStatusMessage()
	{
		return \XF::phrase('liamw_accountdelete_deleting_accounts...');
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}