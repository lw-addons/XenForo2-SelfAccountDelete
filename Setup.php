<?php

namespace LiamW\AccountDelete;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Util\Php;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;

	public function installStep1()
	{
		$this->schemaManager()->createTable('xf_liamw_accountdelete_account_deletions', function(Create $table)
		{
			$table->addColumn('deletion_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50);
			$table->addColumn('reason', 'text')->nullable()->setDefault(null);
			$table->addColumn('initiation_date', 'int');
			$table->addColumn('completion_date', 'int')->nullable()->setDefault(null);
			$table->addColumn('status', 'enum', ['pending', 'complete', 'complete_manual', 'cancelled'])->setDefault('pending');
			$table->addColumn('reminder_sent', 'bool')->setDefault(0);
			$table->addKey('user_id');
			$table->addKey('username');
		});
	}

	public function upgrade1010010Step1()
	{
		$this->schemaManager()->alterTable('xf_liamw_accountdelete_pending', function(Alter $table)
		{
			$table->renameTo('xf_liamw_accountdelete_account_deletions');
			$table->dropPrimaryKey();
			$table->addColumn('deletion_id', 'int')->autoIncrement();
			$table->addColumn('username', 'varchar', 50)->after('user_id');
			$table->addColumn('reason', 'text')->nullable()->setDefault(null);
			$table->renameColumn('initiate_date', 'initiation_date');
			$table->addColumn('completion_date', 'int')->nullable()->setDefault(null);
			$table->addColumn('status', 'enum', ['pending', 'complete', 'complete_manual', 'cancelled'])->setDefault('pending');
			$table->addColumn('reminder_sent', 'bool')->setDefault(0);
			$table->addKey('user_id');
			$table->addKey('username');
		});
	}

	public function upgrade1010033Step1()
	{
		$this->schemaManager()->alterTable('xf_liamw_accountdelete_account_deletions', function(Alter $table)
		{
			$table->changeColumn('status', 'enum', ['pending', 'complete', 'complete_manual', 'cancelled'])->setDefault('pending');
		});
	}

	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
		$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteRunner');

		// Schedule the reminder/deletion jobs
		$repository = \XF::repository('LiamW\AccountDelete:AccountDelete');

		if ($nextRemindTime = $repository->getNextRemindTime())
		{
			$this->app->jobManager()->enqueueLater('lwAccountDeleteReminder', $nextRemindTime, 'LiamW\AccountDelete:SendDeleteReminders');
		}

		if ($nextDeletionTime = $repository->getNextDeletionTime())
		{
			$this->app->jobManager()->enqueueLater('lwAccountDeleteRunner', $nextDeletionTime, 'LiamW\AccountDelete:DeleteAccounts');
		}
	}

	public function onActiveChange($newActive, array &$jobList)
	{
		if ($newActive)
		{
			// Can't use the jobList array as the atomic runner doesn't support future resumes
			$this->app->jobManager()->enqueueUnique('lwAccountDeleteReminder', 'LiamW\AccountDelete:SendDeleteReminders');
			$this->app->jobManager()->enqueueUnique('lwAccountDeleteRunner', 'LiamW\AccountDelete:DeleteAccounts');
		}
		else
		{
			$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteReminder');
			$this->app->jobManager()->cancelUniqueJob('lwAccountDeleteRunner');
		}
	}

	public function uninstall(array $stepParams = [])
	{
		$this->schemaManager()->dropTable('xf_liamw_accountdelete_account_deletions');
	}
}