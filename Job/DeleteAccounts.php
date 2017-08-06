<?php

namespace LiamW\AccountDelete\Job;

use XF\Job\AbstractJob;

class DeleteAccounts extends AbstractJob
{
	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$toDelete = $this->app->finder('LiamW\AccountDelete:AccountDelete')->where('end_date', '<', \XF::$time);

		/** @var \LiamW\AccountDelete\Entity\AccountDelete $item */
		foreach ($toDelete AS $item)
		{
			$item->User->delete();

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