<?php

namespace LiamW\AccountDelete\Job;

use XF;
use XF\Job\AbstractJob;

class SendDeleteReminders extends AbstractJob
{
	public function run($maxRunTime)
	{
		$startTime = microtime(true);

		$repository = XF::repository('LiamW\AccountDelete:AccountDelete');
		$toRemind = $repository->findAccountsToRemind()->fetch();

		if (!$toRemind->count())
		{
			$nextRemindTime = $repository->getNextRemindTime();

			if ($nextRemindTime)
			{
				$resume = $this->resume();
				$resume->continueDate = $nextRemindTime;

				return $resume;
			}
			else
			{
				// This job will be queued when an account deletion is initiated.

				return $this->complete();
			}
		}

		foreach ($toRemind AS $item)
		{
			XF::service('LiamW\AccountDelete:AccountDelete', $item->User)->sendReminderEmail();

			if (microtime(true) - $startTime >= $maxRunTime)
			{
				break;
			}
		}

		return $this->resume();
	}

	public function getStatusMessage()
	{
		return \XF::phrase('liamw_accountdelete_sending_user_deletion_reminders');
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