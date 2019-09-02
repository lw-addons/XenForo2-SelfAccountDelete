<?php

namespace LiamW\AccountDelete\Option;

use XF\Option\AbstractOption;

class AccountDeleteMethod extends AbstractOption
{
	public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
	{
		/** @var \XF\Repository\UserGroup $userGroupRepo */
		$userGroupRepo = \XF::repository('XF:UserGroup');

		$userGroupChoices = $userGroupRepo->getUserGroupTitlePairs();

		return self::getTemplate('admin:liamw_accountdelete_option_template_deletion_method', $option, $htmlParams, [
			'userGroups' => $userGroupChoices
		]);
	}
}