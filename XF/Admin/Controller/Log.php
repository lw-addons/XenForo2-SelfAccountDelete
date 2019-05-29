<?php

namespace LiamW\AccountDelete\XF\Admin\Controller;

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
}