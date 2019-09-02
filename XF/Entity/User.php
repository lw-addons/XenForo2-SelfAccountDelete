<?php

namespace LiamW\AccountDelete\XF\Entity;

class User extends XFCP_User
{
	public function canDeleteSelf(&$error = null)
	{
		if ($this->is_staff || $this->is_admin || $this->is_moderator)
		{
			$error = \XF::phraseDeferred('liamw_accountdelete_you_cannot_delete_your_account_using_this_system_as_you_member_of');

			return false;
		}

		if (!$this->hasPermission('general', 'lw_deleteAccount'))
		{
			return false;
		}

		$criteria = $this->app()->criteria('XF:User', \XF::options()->liamw_accountdelete_user_criteria);
		$criteria->setMatchOnEmpty(true);

		if (!$criteria->isMatched($this))
		{
			$error = \XF::phraseDeferred('liamw_accountdelete_you_cannot_delete_your_account');

			return false;
		}

		return true;
	}
}