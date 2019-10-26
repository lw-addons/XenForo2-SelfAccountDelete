<?php

namespace LiamW\AccountDelete\Option;

use XF\Entity\Option;
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

	public static function verifyOption(&$optionValue, Option $option, $optionId)
	{
		$disableOptions = [
			'remove_email' => false,
			'ban_email' => false,
			'remove_password' => false,
			'disabled_group_id' => 0
		];

		if (empty($optionValue['disable_options']))
		{
			$optionValue['disable_options'] = $disableOptions;
		}

		$optionValue['disable_options'] = array_replace($disableOptions, $optionValue['disable_options']);

		$deleteOptions = [
			'ban_email' => false
		];

		if (empty($optionValue['delete_options']))
		{
			$optionValue['delete_options'] = $deleteOptions;
		}

		$optionValue['delete_options'] = array_replace($deleteOptions, $optionValue['delete_options']);

		return true;
	}
}