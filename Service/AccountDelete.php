<?php

namespace LiamW\AccountDelete\Service;

use XF\Entity\User;
use XF\Mvc\Controller;
use XF\Service\AbstractService;

class AccountDelete extends AbstractService
{
	protected $user;
	protected $controller;

	public function __construct(\XF\App $app, User $user, Controller $controller = \null)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->controller = $controller;
	}

	public function scheduleDeletion($sendEmail = true, $executeImmediate = true)
	{
		if (!$this->controller)
		{
			throw new \InvalidArgumentException("Self Account Deletion: controller required when scheduling delete");
		}

		$accountDeletion = $this->user->getRelationOrDefault('AccountDelete');
		$this->user->save();

		/** @var \XF\ControllerPlugin\Login $loginPlugin */
		$loginPlugin = $this->controller->plugin('XF:Login');
		$loginPlugin->logoutVisitor();

		if ($executeImmediate && $accountDeletion->end_date <= \XF::$time)
		{
			\XF::runLater(function () use ($accountDeletion) {
				$this->executeDeletion();
			});
		}
		else if ($sendEmail)
		{
			$this->sendScheduledEmail();
		}
	}

	public function cancelDeletion($sendEmail = true)
	{
		if ($this->user->AccountDelete)
		{
			$this->user->AccountDelete->delete();

			if ($sendEmail)
			{
				$this->sendCancelledEmail();
			}
		}
	}

	public function executeDeletion($sendEmail = true)
	{
		if (!$this->user->AccountDelete || $this->user->AccountDelete->end_date > \XF::$time)
		{
			return;
		}

		if (\XF::options()->liamw_accountdelete_randomise_username)
		{
			$this->user->username = $this->repository('LiamW\AccountDelete:AccountDelete')
										 ->getRandomisedUsername($this->user);
			$this->user->save();
		}

		switch (\XF::options()->liamw_accountdelete_deletion_method)
		{
			case 'disable':
				$this->user->user_state = 'disabled';
				$this->user->save();

				$this->user->AccountDelete->delete();
				break;
			case 'delete':
				$this->user->delete();
				break;
			default:
				throw new \UnexpectedValueException('Self Account Deletion: unknown deletion method');
		}

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

		$mail = \XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_scheduled', ['user' => $this->user]);
		$mail->send();
	}

	public function sendCancelledEmail()
	{
		if (!$this->user->email || $this->user->user_state != 'valid')
		{
			return;
		}

		$mail = \XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_cancelled', ['user' => $this->user]);
		$mail->queue();
	}

	public function sendCompletedEmail()
	{
		if (!$this->user->email || $this->user->user_state != 'valid')
		{
			return;
		}

		$mail = \XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_completed', ['user' => $this->user]);
		$mail->send();
	}
}