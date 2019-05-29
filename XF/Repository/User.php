<?php

namespace LiamW\AccountDelete\XF\Repository;

class User extends XFCP_User
{
	public function getVisitorWith(array $with = [])
	{
		$with = parent::getVisitorWith($with);

		$with[] = 'PendingAccountDeletion';

		return $with;
	}
}