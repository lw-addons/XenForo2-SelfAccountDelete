<?php

namespace LiamW\AccountDelete\Service;

use XF\Entity\User;
use XF\Mail\Mail;
use XF\Mvc\Controller;
use XF\Service\AbstractService;

class AccountDelete extends AbstractService
{
	protected $user;
	protected $controller;

	public function __construct(\XF\App $app, User $user, Controller $controller)
	{
		parent::__construct($app);

		$this->user = $user;
		$this->controller = $controller;
	}

	public function scheduleDeletion($sendEmail = true)
	{
		$accountDeletion = $this->user->getRelationOrDefault('AccountDelete');
		$this->user->save();

		/** @var \XF\ControllerPlugin\Login $loginPlugin */
		$loginPlugin = $this->controller->plugin('XF:Login');
		$loginPlugin->logoutVisitor();

		if ($accountDeletion->end_date <= \XF::$time)
		{
			\XF::runLater(function () use ($accountDeletion) {
				$accountDeletion->User->delete();
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

	public function sendScheduledEmail()
	{
		$mail = \XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_scheduled', ['user' => $this->user]);
		$mail->send();
	}

	public function sendCancelledEmail()
	{
		$mail = \XF::mailer()->newMail();
		$mail->setToUser($this->user);
		$mail->setTemplate('liamw_accountdelete_delete_cancelled', ['user' => $this->user]);
		$mail->send();
	}
}