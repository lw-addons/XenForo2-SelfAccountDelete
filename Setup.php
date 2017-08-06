<?php

namespace LiamW\AccountDelete;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
	public function install(array $stepParams = [])
	{
		$this->db()->getSchemaManager()->createTable('xf_liamw_accountdelete_pending', function (Create $table)
		{
			$table->addColumn('user_id', 'int')->primaryKey();
			$table->addColumn('initiate_date', 'int');
		});
	}

	public function upgrade(array $stepParams = [])
	{
		// TODO: Implement upgrade() method.
	}

	public function uninstall(array $stepParams = [])
	{
		$this->db()->getSchemaManager()->dropTable('xf_liamw_accountdelete_pending');
	}
}