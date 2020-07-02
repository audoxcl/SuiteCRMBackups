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
		'8*',
		'9*',
	),
	'is_uninstallable' => true,
	'name' => 'Backups Scheduler',
	'author' => 'Audox Ingeniería SpA.',
	'description' => 'Backups Scheduler',
	'published_date' => '2020-03-12 00:00:00',
	'version' => 'v2.5',
	'type' => 'module',
);

$installdefs = array(
	'id' => 'Backups Scheduler 2.3',
	'language' => array(
		array(
			'from' => '<basepath>/en_us.backup.lang.php',
			'to_module' => 'Schedulers',
			'language' => 'en_us',
		),
	),
	'scheduledefs' => array(
		array(
			'from' => '<basepath>/backup.php',
		),
	),
);
