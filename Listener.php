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
	public static function optionControllerPreDispatch(\XF\Mvc\Controller $controller, $action, \XF\Mvc\ParameterBag $params)
	{
		if ($controller->isPost() && $action == 'Update' && in_array('liamw_accountdelete_user_criteria', $controller->filter('options_listed', 'array-str')))
		{
			$controller->request()->set('options.liamw_accountdelete_user_criteria', $controller->request()
				->get('user_criteria'));
			$controller->request()->set('user_criteria', null);
		}
	}

	public static function optionFormBlockMacroPreRender(\XF\Template\Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars)
	{
		if ($arguments['group']->group_id == 'liamw_memberselfdelete')
		{
			$template = 'liamw_accountdelete_option_macros';
			$userCriteria = XF::app()
				->criteria('XF:User', $arguments['options']['liamw_accountdelete_user_criteria']->option_value);
			$arguments['userCriteria'] = $userCriteria;
		}
	}

	public static function controllerPreDispatch(Controller $controller, $action, ParameterBag $params)
	{
		if ($controller->app() instanceof XF\Pub\App && XF::visitor()->PendingAccountDeletion && !($controller instanceof XF\Pub\Controller\Logout) && !($controller instanceof Account && ($action == 'Delete' || $action == 'DeleteCancel')) && !$controller->isPost() && !$controller->request()
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
		$valueChanged = $entity->isChanged('option_value');

		XF::runLater(function() use ($valueChanged, $entity)
		{
			/** @var XF\Entity\Option $entity */
			if ($valueChanged)
			{
				if ($entity->option_id == 'liamw_accountdelete_deletion_delay')
				{
					XF::app()->jobManager()
						->enqueueLater('lwAccountDeleteRunner', XF::repository('LiamW\AccountDelete:AccountDelete')
							->getNextDeletionTime($entity->option_value), 'LiamW\AccountDelete:DeleteAccounts');
					XF::app()->jobManager()
						->enqueueLater('lwAccountDeleteReminder', XF::repository('LiamW\AccountDelete:AccountDelete')
							->getNextRemindTime($entity->option_value), 'LiamW\AccountDelete:SendDeleteReminders');
				}
				else if ($entity->option_id == 'liamw_accountdelete_reminder_threshold')
				{
					if ($entity->option_value)
					{
						XF::app()->jobManager()
							->enqueueLater('lwAccountDeleteReminder', XF::repository('LiamW\AccountDelete:AccountDelete')
								->getNextRemindTime(null, $entity->option_value), 'LiamW\AccountDelete:SendDeleteReminders');
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
		if ($entity->getOption('liamw_accountdelete_log_manual') === true && $entity->PendingAccountDeletion)
		{
			$entity->PendingAccountDeletion->status = 'complete_manual';
			$entity->PendingAccountDeletion->completion_date = XF::$time;
			$entity->PendingAccountDeletion->save();
		}
	}

	public static function userEntityPostSave(\XF\Mvc\Entity\Entity $entity)
	{
		if ($entity->getOption('liamw_accountdelete_log_manual') === true && $entity->PendingAccountDeletion && $entity->isStateChanged('user_state', 'disabled') == 'enter')
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

		$structure->options['liamw_accountdelete_log_manual'] = true;
	}

	public static function visitorExtraWith(array &$with)
	{
		if (XF::app() instanceof XF\Pub\App)
		{
			$with[] = 'PendingAccountDeletion';
		}
	}
}