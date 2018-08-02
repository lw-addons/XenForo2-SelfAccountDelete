<?php

namespace LiamW\AccountDelete;

use XF\Mvc\Entity\Entity;
use XF\Pub\Controller\Account;

class Listener
{
	public static function controllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
	{
		if (\XF::visitor()->AccountDelete && !($controller instanceof Account && ($action == 'Delete' || $action == 'DeleteCancel')) && !$controller->isPost() && !$controller->request()
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

	public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
	{
		$structure->relations['AccountDelete'] = [
			'entity' => 'LiamW\AccountDelete:AccountDelete',
			'type' => Entity::TO_ONE,
			'conditions' => 'user_id',
			'primary' => true,
			'cascadeDelete' => true
		];
	}
}