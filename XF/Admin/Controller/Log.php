<?php

namespace LiamW\AccountDelete\XF\Admin\Controller;

use LiamW\AccountDelete\Entity\AccountDelete;
use XF\Mvc\ParameterBag;

class Log extends XFCP_Log
{
	public function actionAccountDeletion()
	{
		$this->assertAdminPermission('user');

		$page = $this->filterPage();
		$perPage = 20;

		$accountDeletionsFinder = $this->finder('LiamW\AccountDelete:AccountDelete')->setDefaultOrder('initiation_date', 'desc')->limitByPage($page, $perPage);
		$accountDeletions = $accountDeletionsFinder->fetch();

		$viewParams = [
			'accountDeletions' => $accountDeletions,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $accountDeletionsFinder->total()
		];

		return $this->view('Log\AccountDeletion', 'liamw_accountdelete_user_delete_log', $viewParams);
	}

	public function actionAccountDeletionCancel(ParameterBag $params)
	{
		$this->assertAdminPermission('user');

		$accountDelete = $this->assertDeletionExists($params['deletion_id']);

		if ($accountDelete->status != 'pending' || !$accountDelete->User)
		{
			return $this->error(\XF::phrase('liamw_accountdelete_this_scheduled_account_deletion_cannot_be_cancelled_as_it_is_not_pending'));
		}

		if ($this->isPost())
		{
			$this->service('LiamW\AccountDelete:AccountDelete', $accountDelete->User)->cancelDeletion();

			return $this->redirect($this->buildLink('logs/account-deletion'));
		}
		else
		{
			$viewParams = [
				'accountDeletion' => $accountDelete
			];

			return $this->view('XF:Delete\Delete', 'liamw_accountdelete_user_delete_cancel', $viewParams);
		}
	}

	/**
	 * @param $deletionId
	 *
	 * @return \XF\Mvc\Entity\Entity|AccountDelete
	 */
	protected function assertDeletionExists($deletionId)
	{
		return $this->assertRecordExists('LiamW\AccountDelete:AccountDelete', $deletionId, ['User']);
	}
}