<?php
// List settings here in the format: setting_key => default_value.  Escape any "s. (" => \")
$mod_settings = array(
	'enableSubAccounts' => 1,
	'subaccountesInheritParentGroup' => 0,
	'subaccountesShowInMemberlist' => 1,
	'subaccountsShowInProfile' => 1,
);

$column_info = array(
	'table' => '{db_prefix}members',
	'name' => 'is_shareable',
	'default' => 0,
	'type' => 'MEDIUMINT',
	'size' => 8,
	'null' => 0,
);

$table = array(
	'table_name' => '{db_prefix}subaccounts',
	'if_exists' => 'ignore',
	'error' => 'fatal',
	'parameters' => array(),
	'columns' => array(
		array(
			'name' => 'id_member',
			'auto' => false,
			'default' => 0,
			'type' => 'mediumint',
			'size' => 8,
			'null' => false,
		),
		array(
			'name' => 'id_parent',
			'auto' => false,
			'default' => 0,
			'type' => 'mediumint',
			'size' => 8,
			'null' => false,
		),
	),
	'indexes' => array(
		array(
			'columns' => array('id_parent', 'id_member'),
			'type' => 'unique',
			'name' => 'id_parent',
		),
	),
);
/******************************************************************************/

// If SSI.php is in the same place as this file, and SMF isn't defined, this is being run standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
// Hmm... no SSI.php and no SMF?
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

updateSettings($mod_settings);

db_extend('packages');

$smcFunc['db_create_table']($table['table_name'], $table['columns'], $table['indexes'], $table['parameters'], $table['if_exists'], $table['error']);

$smcFunc['db_add_column']($column_info['table'], $column_info);

if (SMF == 'SSI')
	echo 'Successfully added settings to database';

?>
