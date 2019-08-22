<?php

namespace LiamW\AccountDelete\XF\Pub\Controller;

use LiamW\AccountDelete\Service\AccountDelete;
use XF;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Account extends XFCP_Account
{
	public function actionDelete()
	{
		if (XF::visitor()->PendingAccountDeletion)
		{
			return $this->view('LiamW\AccountDelete:AccountDelete\Pending', 'liamw_accountdelete_pending');
		}

		if (XF::visitor()->is_staff || XF::visitor()->is_admin || XF::visitor()->is_moderator)
		{
			return $this->noPermission(\XF::phrase('liamw_accountdelete_you_cannot_delete_your_account_using_this_system_as_you_member_of'));
		}

		if (!XF::visitor()->hasPermission('general', 'lw_deleteAccount'))
		{
			return $this->noPermission();
		}

		$this->assertAccountDeletePasswordVerified();

		if ($this->isPost())
		{
			$confirmation = $this->filter('confirmation', 'bool');

			if (!$confirmation)
			{
				return $this->error(XF::phrase('liamw_accountdelete_please_confirm_deletion_by_checking_the_checkbox'));
			}

			if (!$this->filter('reason_requested', 'bool'))
			{
				return $this->view('LiamW\AccountDelete:AccountDelete\Reason', 'liamw_accountdelete_reason_form');
			}

			if (!$reason = $this->filter('reason', 'str'))
			{
				return $this->error(\XF::phrase('liamw_accountdelete_please_enter_reason_for_deleting_your_account'));
			}

			/** @var AccountDelete $deleteService */
			$deleteService = $this->service('LiamW\AccountDelete:AccountDelete', XF::visitor(), $this);
			$deleteService->scheduleDeletion($reason);

			return $this->redirect($this->buildLink('index'), XF::phrase('liamw_accountdelete_account_deletion_scheduled'));
		}
		else
		{
			return $this->addAccountWrapperParams($this->view('LiamW\AccountDelete:AccountDelete\Confirm', 'liamw_accountdelete_form'), 'liamw_accountdelete_delete_account');
		}
	}

	public function actionDeleteCancel()
	{
		/** @var AccountDelete $deleteService */
		$deleteService = $this->service('LiamW\AccountDelete:AccountDelete', XF::visitor(), $this);
		$deleteService->cancelDeletion();

		return $this->redirect($this->buildLink('index'), \XF::phrase('liamw_accountdelete_account_deletion_cancelled'));
	}

	protected function canUpdateSessionActivity($action, ParameterBag $params, AbstractReply &$reply, &$viewState)
	{
		if (XF::visitor()->PendingAccountDeletion)
		{
			return false;
		}

		return parent::canUpdateSessionActivity($action, $params, $reply, $viewState);
	}

	protected function assertAccountDeletePasswordVerified()
	{
		$this->assertPasswordVerified(300, null, function($view)
		{
			return $this->addAccountWrapperParams($view, 'liamw_accountdelete_delete_account');
		});
	}
}