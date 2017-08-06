<?php

namespace LiamW\AccountDelete\XF\Pub\Controller;

class Account extends XFCP_Account
{
	public function actionDelete()
	{
		if (\XF::visitor()->AccountDelete)
		{
			return $this->view('LiamW\AccountDelete:AccountDelete\Existing', 'liamw_accountdelete_pending');
		}

		if ($this->isPost())
		{
			$confirmation = $this->filter('confirmation', 'bool');

			if (!$confirmation)
			{
				return $this->error(\XF::phrase('liamw_accountdelete_please_confirm_deletion_by_checking_the_checkbox'));
			}

			/** @var \LiamW\AccountDelete\Service\AccountDelete $deleteService */
			$deleteService = $this->service('LiamW\AccountDelete:AccountDelete', \XF::visitor(), $this);
			$deleteService->scheduleDeletion();

			return $this->redirect($this->buildLink('index'), \XF::phrase('liamw_accountdelete_account_deletion_scheduled'));
		}
		else
		{
			return $this->view('LiamW\AccountDelete:AccountDelete\Confirm', 'liamw_accountdelete_form');
		}
	}

	public function actionDeleteCancel()
	{
		/** @var \LiamW\AccountDelete\Service\AccountDelete $deleteService */
		$deleteService = $this->service('LiamW\AccountDelete:AccountDelete', \XF::visitor(), $this);
		$deleteService->cancelDeletion();

		return $this->redirect($this->buildLink('index'), \XF::phrase('liamw_accountdelete_deletion_cancelled'));
	}
}