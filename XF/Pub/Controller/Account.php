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
			return $this->error(XF::phrase('liamw_accountdelete_as_you_are_staff_you_cannot_use_this_system'));
		}

		$this->assertAccountDeletePasswordVerified();

		if ($this->isPost())
		{
			$confirmation = $this->filter('confirmation', 'bool');

			if (!$confirmation)
			{
				return $this->error(XF::phrase('liamw_accountdelete_please_confirm_deletion_by_checking_the_checkbox'));
			}

			/** @var AccountDelete $deleteService */
			$deleteService = $this->service('LiamW\AccountDelete:AccountDelete', XF::visitor(), $this);
			$deleteService->scheduleDeletion();

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

		return $this->redirect($this->buildLink('index'), XF::phrase('liamw_accountdelete_deletion_cancelled'));
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
		$this->assertPasswordVerified(300, null, function ($view) {
			return $this->addAccountWrapperParams($view, 'liamw_accountdelete_delete_account');
		});
	}
}