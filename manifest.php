<?php

// $GLOBALS['sugar_config']['moduleInstaller']['disableFileScan'] = true;

$manifest = array(
	'acceptable_sugar_flavors' => array(
		'CE',
		'PRO',
		'ENT',
		'CORP',
		'ULT',
	),
	'acceptable_sugar_versions' => array(
		'6*',
		'7*',
	),
	'is_uninstallable' => true,
	'name' => 'Backup Scheduler',
	'author' => 'Audox Ingenieria SpA.',
	'description' => 'Backup Scheduler',
	'published_date' => '2018-11-30 00:00:00',
	'version' => 'v1.0',
	'type' => 'module',
);

$installdefs = array(
	'id' => 'Backup Scheduler',
	'language' => array(
		array(
			'from' => '<basepath>/en_us.BackupScheduler.php',
			'to_module' => 'Schedulers',
			'language' => 'en_us',
		),
	),
	'scheduledefs' => array(
		array(
			'from' => '<basepath>/BackupScheduler_job.php',
		),
	),
);

?>