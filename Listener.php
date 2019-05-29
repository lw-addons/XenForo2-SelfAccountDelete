<?php

namespace LiamW\AccountDelete;

use XF;
use XF\Mvc\Controller;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\Account;

class Listener
{
	public static function controllerPreDispatch(Controller $controller, $action, ParameterBag $params)
	{
		if (XF::visitor()->PendingAccountDeletion && !($controller instanceof Account && ($action == 'Delete' || $action == 'DeleteCancel')) && !$controller->isPost() && !$controller->request()
				->isXhr())
		{
			$reply = $controller->rerouteController('XF\Pub\Controller\Account', 'Delete');

			if ($controller->request()->getRoutePath() != '')
			{
				$reply = $controller->redirect($controller->buildLink('index'));
			}

			throw $controller->exception($reply);
		}
	}

	public static function userEntityStructure(Manager $em, Structure &$structure)
	{
		$structure->relations['AccountDeletionLogs'] = [
			'entity' => 'LiamW\AccountDelete:AccountDelete',
			'type' => Entity::TO_MANY,
			'conditions' => 'user_id',
			'primary' => true
		];

		$structure->relations['PendingAccountDeletion'] = [
			'entity' => 'LiamW\AccountDelete:AccountDelete',
			'type' => Entity::TO_ONE,
			'conditions' => ['user_id', ['status', '=', 'pending']],
			'primary' => true
		];
	}
}