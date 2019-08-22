<?php

namespace LiamW\AccountDelete\Service;

use InvalidArgumentException;
use UnexpectedValueException;
use XF;
use XF\App;
use XF\ControllerPlugin\Login;
use XF\Entity\User;
use XF\Mvc\Controller;
use XF\Service\AbstractService;

class AccountDelete extends AbstractService
{
	protected $user;
	protected $originalUsername;
	protected $userEmail;
	protected $controller;

	protected $renameTo;
	protected $banEmail;
	protected $removeEmail;

	protected $sendEmail;

	public function __construct(App $app, User $user, Controller $controller = null)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->originalUserName = $user->username;
		$this->userEmail = $user->email;
		$this->controller = $controller;
	}

	public function scheduleDeletion($reason, $sendEmail = true, $immediateExecution = true)
	{
		if (!$this->controller)
		{
			throw new InvalidArgumentException("Scheduling account deletion requires controller to be passed to service");
		}

		/** @var \LiamW\AccountDelete\Entity\AccountDelete $accountDeletion */
		$accountDeletion = $this->user->getRelationOrDefault('PendingAccountDeletion');
		$accountDeletion->reason = $reason;
		$this->user->save();

		/** @var Login $loginPlugin */
		$loginPlugin = $this->controller->plugin('XF:Login');
		$loginPlugin->logoutVisitor();

		if ($immediateExecution && $accountDeletion->end_date <= XF::$time)
		{
			XF::runLater(function() use ($accountDeletion)
			{
				$this->executeDeletion();
			});
		}
		else
		{
			$repository = $this->repository('LiamW\AccountDelete:AccountDelete');

			if ($repository->getNextRemindTime())
			{
				$this->app->jobManager()
					->enqueueLater('lwAccountDeleteReminder', $repository->getNextRemindTime(), 'LiamW\AccountDelete:SendDeleteReminders');
			}
			else
			{
				$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
			}

			$this->app->jobManager()
				->enqueueLater('lwAccountDeleteRunner', $repository->getNextDeletionTime(), 'LiamW\AccountDelete:DeleteAccounts');

			if ($sendEmail)
			{
				$this->sendScheduledEmail();
			}
		}
	}

	public function cancelDeletion($sendEmail = true)
	{
		if ($this->user->PendingAccountDeletion)
		{
			$this->user->PendingAccountDeletion->status = "cancelled";
			$this->user->PendingAccountDeletion->save();

			$repository = $this->repository('LiamW\AccountDelete:AccountDelete');

			if ($repository->getNextRemindTime())
			{
				$this->app->jobManager()
					->enqueueLater('lwAccountDeleteReminder', $repository->getNextRemindTime(), 'LiamW\AccountDelete:SendDeleteReminders');
			}
			else
			{
				$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
			}

			if ($repository->getNextDeletionTime())
			{
				$this->app->jobManager()
					->enqueueLater('lwAccountDeleteRunner', $repository->getNextDeletionTime(), 'LiamW\AccountDelete:DeleteAccounts');
			}
			else
			{
				$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteRunner');
			}

			if ($sendEmail)
			{
				$this->sendCancelledEmail();
			}
		}
	}

	public function executeDeletion($sendEmail = true)
	{
		if (!$this->user->PendingAccountDeletion || $this->user->PendingAccountDeletion->end_date > XF::$time)
		{
			return;
		}

		$this->sendEmail = $sendEmail;

		$methodOption = XF::options()->liamw_accountdelete_deletion_method;

		if (XF::options()->liamw_accountdelete_randomise_username)
		{
			$this->renameTo($this->repository('LiamW\AccountDelete:AccountDelete')
				->getDeletedUserUsername($this->user));
		}

		switch ($methodOption['mode'])
		{
			case 'disable':
				$this->removeEmail($methodOption['disable_options']['remove_email']);
				$this->banEmail($methodOption['disable_options']['ban_email']);

				if ($methodOption['disable_options']['remove_password'])
				{
					$this->user->getRelationOrDefault('Auth')->setNoPassword();

					$userProfile = $this->user->getRelationOrDefault('Profile');

					foreach ($this->user->ConnectedAccounts AS $connectedAccount)
					{
						$connectedAccount->delete();

						/** @var XF\Entity\ConnectedAccountProvider $provider */
						$provider = $this->em()->find('XF:ConnectedAccountProvider', $connectedAccount->provider);
						if ($provider)
						{
							$storageState = $provider->getHandler()->getStorageState($provider, $this->user);
							$storageState->clearProviderData();
						}

						$profileConnectedAccounts = $userProfile->connected_accounts;
						unset($profileConnectedAccounts[$connectedAccount->provider]);
						$userProfile->connected_accounts = $profileConnectedAccounts;
					}
				}

				$this->doDisable();
				break;
			case 'delete':
				$this->banEmail($methodOption['delete_options']['ban_email']);

				$this->doDelete();
				break;
			default:
				throw new UnexpectedValueException('Unknown option value encountered during member deletion');
		}

		$this->finaliseDeleteDisable();
	}

	protected function renameTo($name)
	{
		if ($name === $this->user->username)
		{
			$this->renameTo = null;
		}
		else
		{
			$this->renameTo = $name;
		}
	}

	protected function banEmail($option)
	{
		$this->banEmail = $option;
	}

	protected function removeEmail($option)
	{
		$this->removeEmail = $option;
	}

	protected function doRename()
	{
		if ($this->renameTo)
		{
			$this->user->setTrusted('username', $this->renameTo);
			$this->user->save();
		}
	}

	protected function doDelete()
	{
		$this->user->setOption('liamw_accountdelete_log_manual', false);
		$this->user->setOption('enqueue_rename_cleanup', false);
		$this->user->setOption('enqueue_delete_cleanup', false);

		$this->doRename();

		$this->user->delete();
	}

	protected function doDisable()
	{
		$this->user->setOption('liamw_accountdelete_log_manual', false);
		$this->user->setOption('enqueue_rename_cleanup', false);

		$this->doRename();

		$this->user->user_state = 'disabled';

		if ($disabledGroupId = XF::options()->liamw_accountdelete_disabled_usergroup)
		{
			$secondaryGroups = $this->user->secondary_group_ids;
			if (!in_array($disabledGroupId, $secondaryGroups))
			{
				$secondaryGroups[] = $disabledGroupId;
				$this->user->secondary_group_ids = $secondaryGroups;
			}
		}

		$this->user->save();
	}

	protected function finaliseDeleteDisable()
	{
		$email = $this->userEmail;

		if ($this->sendEmail)
		{
			$this->sendCompletedEmail();
		}

		// Remove email address after sending the completion email
		if ($email && $this->removeEmail && $this->user->exists())
		{
			// setTrusted bypasses validations, allowing us to sent an empty email
			$this->user->setTrusted('email', '');
			$this->user->save();
		}

		if ($email && $this->banEmail)
		{
			if (!$this->repository('XF:Banning')->isEmailBanned($email, XF::app()->get('bannedEmails')))
			{
				$this->repository('XF:Banning')->banEmail($email, \XF::phrase('liamw_accountdelete_automated_ban_user_deleted_self'), $this->user);
			}
		}

		$this->user->PendingAccountDeletion->completion_date = XF::$time;
		$this->user->PendingAccountDeletion->status = "complete";
		$this->user->PendingAccountDeletion->save();

		$this->runPostDeleteJobs();
	}

	protected function runPostDeleteJobs()
	{
		$user = $this->user;

		$jobList = [];
		if ($this->renameTo)
		{
			$jobList[] = [
				'XF:UserRenameCleanUp',
				[
					'originalUserId' => $user->user_id,
					'originalUserName' => $this->originalUsername,
					'newUserName' => $this->renameTo
				]
			];
		}

		if (!$user->exists())
		{
			$jobList[] = [
				'XF:UserDeleteCleanUp',
				[
					'userId' => $user->user_id,
					'username' => $this->renameTo
				]
			];
		}

		if ($jobList)
		{
			$this->app->jobManager()->enqueueUnique('selfAccountDeleteCleanup' . $user->user_id, 'XF:Atomic', [
				'execute' => $jobList
			]);
		}
	}

	public function sendScheduledEmail()
	{
		if (!$this->user->email || $this->user->user_state != 'valid')
		{
			return;
		}

		$mail = XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_scheduled', ['user' => $this->user]);
		$mail->send();
	}

	public function sendReminderEmail()
	{
		$pendingDeletion = $this->user->PendingAccountDeletion;
		$pendingDeletion->reminder_sent = 1;
		$pendingDeletion->save();

		if (!$this->user->email || $this->user->user_state != 'valid' || $this->user->PendingAccountDeletion->reminder_sent)
		{
			return;
		}

		$mail = XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_imminent', ['user' => $this->user]);
		$mail->queue();
	}

	public function sendCancelledEmail()
	{
		if (!$this->user->email || $this->user->user_state != 'valid')
		{
			return;
		}

		$mail = XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_cancelled', ['user' => $this->user]);
		$mail->send();
	}

	public function sendCompletedEmail()
	{
		if (!$this->user->email)
		{
			return;
		}

		$mail = XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_completed', ['user' => $this->user]);
		$mail->send();
	}
}