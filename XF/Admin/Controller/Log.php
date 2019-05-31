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

		return $this->view('Log\AccountDeletion', 'liamw_accountdelete_account_deletion_log', $viewParams);
	}

	public function actionAccountDeletionCancel(ParameterBag $params)
	{
		$accountDelete = $this->assertDeletionExists($params['deletion_id']);

		if ($accountDelete->status != 'pending' || !$accountDelete->User)
		{
			return $this->error("This account deletion cannot be cancelled as it has already been completed.");
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

			return $this->view('XF:Delete\Delete', 'liamw_accountdelete_account_deletion_cancel', $viewParams);
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