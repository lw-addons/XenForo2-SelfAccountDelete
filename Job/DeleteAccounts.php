<?php

namespace LiamW\AccountDelete\Job;

use XF;
use XF\Job\AbstractJob;

class DeleteAccounts extends AbstractJob
{
	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$toDelete = XF::repository('LiamW\AccountDelete:AccountDelete')->getAccountsToDelete();

		if (!$toDelete->count())
		{
			return $this->complete();
		}

		foreach ($toDelete AS $item)
		{
			XF::service('LiamW\AccountDelete:AccountDelete', $item->User)->executeDeletion();

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}

		return $this->resume();
	}

	public function getStatusMessage()
	{
		return XF::phrase('liamw_accountdelete_deleting_accounts...');
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