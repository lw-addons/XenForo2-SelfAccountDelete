<?php

namespace LiamW\AccountDelete\XF\Pub\Controller;

use LiamW\AccountDelete\Service\AccountDelete;
use LiamW\AccountDelete\Utils as AccountDeleteUtils;
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

		if (!AccountDeleteUtils::visitor()->canDeleteSelf($error))
		{
			return $this->noPermission($error);
		}

		$this->assertAccountDeletePasswordVerified();

		if ($this->isPost())
		{
			$confirmation = $this->filter('confirmation', 'bool');

			if (!$confirmation)
			{
				return $this->error(XF::phrase('liamw_accountdelete_please_confirm_deletion_by_checking_the_checkbox'));
			}

			$reason = null;
			if (XF::options()->liamw_accountdelete_reason['request'])
			{
				if (!$this->filter('reason_requested', 'bool'))
				{
					return $this->view('LiamW\AccountDelete:AccountDelete\Reason', 'liamw_accountdelete_reason_form');
				}

				if (XF::options()->liamw_accountdelete_reason['require'] && !($reason = $this->filter('reason', '?str')))
				{
					return $this->error(\XF::phrase('liamw_accountdelete_please_enter_reason_for_deleting_your_account'));
				}
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
		$this->assertPasswordVerified(300, null, function ($view)
		{
			return $this->addAccountWrapperParams($view, 'liamw_accountdelete_delete_account');
		});
	}
}