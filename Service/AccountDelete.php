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
use XF\Service\User\Delete;

class AccountDelete extends AbstractService
{
	protected $user;
	protected $controller;

	public function __construct(App $app, User $user, Controller $controller = null)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->controller = $controller;
	}

	public function scheduleDeletion($sendEmail = true, $immediateExecution = true)
	{
		if (!$this->controller)
		{
			throw new InvalidArgumentException("Scheduling account deletion requires controller to be passed to service");
		}

		/** @var \LiamW\AccountDelete\Entity\AccountDelete $accountDeletion */
		$accountDeletion = $this->user->getRelationOrDefault('PendingAccountDeletion');
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
				$this->app->jobManager()->enqueueLater('lwAccountDeleteReminder', $repository->getNextRemindTime(), 'LiamW\AccountDelete:SendDeleteReminders');
			}
			else
			{
				$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
			}

			$this->app->jobManager()->enqueueLater('lwAccountDeleteRunner', $repository->getNextDeletionTime(), 'LiamW\AccountDelete:DeleteAccounts');

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
				$this->app->jobManager()->enqueueLater('lwAccountDeleteReminder', $repository->getNextRemindTime(), 'LiamW\AccountDelete:SendDeleteReminders');
			}
			else
			{
				$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
			}

			if ($repository->getNextDeletionTime())
			{
				$this->app->jobManager()->enqueueLater('lwAccountDeleteRunner', $repository->getNextDeletionTime(), 'LiamW\AccountDelete:DeleteAccounts');
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

		$pendingAccountDeletion = $this->user->PendingAccountDeletion;
		$methodOption = XF::options()->liamw_accountdelete_deletion_method;

		switch ($methodOption['mode'])
		{
			case 'disable':
				if (XF::options()->liamw_accountdelete_randomise_username)
				{
					$this->user->username = $this->repository('LiamW\AccountDelete:AccountDelete')->getDeletedUserUsername($this->user);
				}

				if ($methodOption['disable_options']['remove_email'])
				{
					if ($methodOption['disable_options']['ban_email'])
					{
						$email = $this->user->email;

						if (!$this->repository('XF:Banning')->isEmailBanned($email, XF::app()->get('bannedEmails')))
						{
							$this->repository('XF:Banning')->banEmail($email, \XF::phrase('liamw_accountdelete_automated_ban_user_deleted_self'), $this->user);
						}
					}

					// Entity::setTrusted skips verification
					$this->user->setTrusted('email', '');
				}

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

				$this->user->user_state = 'disabled';
				$this->user->save(false);
				break;
			case 'delete':
				/** @var Delete $userDeleteService */
				$userDeleteService = $this->service('XF:User\Delete', $this->user);

				if (XF::options()->liamw_accountdelete_randomise_username)
				{
					$userDeleteService->renameTo($this->repository('LiamW\AccountDelete:AccountDelete')->getDeletedUserUsername($this->user));
				}

				$userDeleteService->delete();

				if ($methodOption['delete_options']['ban_email'])
				{
					$email = $this->user->email;

					if (!$this->repository('XF:Banning')->isEmailBanned($email, XF::app()->get('bannedEmails')))
					{
						$this->repository('XF:Banning')->banEmail($email, \XF::phrase('liamw_accountdelete_automated_ban_user_deleted_self'), $this->user);
					}
				}
				break;
			default:
				throw new UnexpectedValueException('Unknown option value encountered during member deletion');
		}

		$pendingAccountDeletion->completion_date = XF::$time;
		$pendingAccountDeletion->status = "complete";
		$pendingAccountDeletion->save();

		if ($sendEmail)
		{
			$this->sendCompletedEmail();
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
		if (!$this->user->email || $this->user->user_state != 'valid' || $this->user->PendingAccountDeletion->reminder_sent)
		{
			return;
		}

		$pendingDeletion = $this->user->PendingAccountDeletion;
		$pendingDeletion->reminder_sent = 1;
		$pendingDeletion->save();

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
		if (!$this->user->email || $this->user->user_state != 'valid')
		{
			return;
		}

		$mail = XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_completed', ['user' => $this->user]);
		$mail->send();
	}
}