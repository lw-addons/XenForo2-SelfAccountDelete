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
			if ($controller->request()->getRoutePath() != '')
			{
				$reply = $controller->redirect($controller->buildLink('index'));
			}
			else
			{
				$reply = $controller->rerouteController('XF\Pub\Controller\Account', 'Delete');
			}

			throw $controller->exception($reply);
		}
	}

	public static function optionEntityPostSave(\XF\Mvc\Entity\Entity $entity)
	{
		XF::runLater(function() use ($entity)
		{
			/** @var XF\Entity\Option $entity */
			if ($entity->isChanged('option_value'))
			{
				if ($entity->option_id == 'liamw_accountdelete_deletion_delay')
				{
					XF::app()->jobManager()->enqueueLater('lwAccountDeleteRunner', XF::repository('LiamW\AccountDelete:AccountDelete')->getNextDeletionTime(), 'LiamW\AccountDelete:DeleteAccounts');
				}
				elseif ($entity->option_id == 'liamw_accountdelete_reminder_threshold')
				{
					if ($entity->option_value)
					{
						XF::app()->jobManager()->enqueueLater('lwAccountDeleteReminder', XF::repository('LiamW\AccountDelete:AccountDelete')->getNextRemindTime(), 'LiamW\AccountDelete:SendDeleteReminders');
					}
					else
					{
						XF::app()->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
					}
				}
			}
		});
	}

	public static function userEntityPostDelete(\XF\Mvc\Entity\Entity $entity)
	{
		if ($entity->getOption('admin_edit') === true && $entity->PendingAccountDeletion)
		{
			$entity->PendingAccountDeletion->status = 'complete_manual';
			$entity->PendingAccountDeletion->completion_date = XF::$time;
			$entity->PendingAccountDeletion->save();
		}
	}

	public static function userEntityPostSave(\XF\Mvc\Entity\Entity $entity)
	{
		if ($entity->getOption('admin_edit') === true && $entity->PendingAccountDeletion && $entity->isStateChanged('user_state', 'disabled') == 'enter')
		{
			$entity->PendingAccountDeletion->status = 'complete_manual';
			$entity->PendingAccountDeletion->completion_date = XF::$time;
			$entity->PendingAccountDeletion->save();
		}
	}

	public static function userEntityStructure(Manager $em, Structure &$structure)
	{
		$structure->relations['AccountDeletionLogs'] = [
			'entity' => 'LiamW\AccountDelete:AccountDelete',
			'type' => Entity::TO_MANY,
			'conditions' => 'user_id'
		];

		$structure->relations['PendingAccountDeletion'] = [
			'entity' => 'LiamW\AccountDelete:AccountDelete',
			'type' => Entity::TO_ONE,
			'conditions' => ['user_id', ['status', '=', 'pending']]
		];
	}
}