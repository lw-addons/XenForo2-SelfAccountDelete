<?php

namespace LiamW\AccountDelete\Cron;

class DeleteAccounts
{
	public static function deleteAccounts()
	{
		\XF::app()->jobManager()
		   ->enqueueUnique('liamw_deleteAccounts', 'LiamW\AccountDelete:DeleteAccounts', [], false);
	}
}