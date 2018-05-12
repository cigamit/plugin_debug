<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_debug_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/debug/INFO', true);
	return $info['info'];
}

function plugin_debug_install() {
	api_plugin_register_hook('debug', 'config_arrays', 'debug_config_arrays', 'setup.php');
	api_plugin_register_hook('debug', 'poller_output', 'debug_poller_output', 'setup.php');
	api_plugin_register_hook('debug', 'poller_bottom', 'debug_poller_bottom', 'setup.php');
	api_plugin_register_realm('debug', 'debug.php', 'Debug', 1);

	plugin_debug_setup_table();
}

function plugin_debug_uninstall() {
}

function plugin_debug_check_config() {
	return true;
}

function plugin_debug_upgrade() {
	return false;
}

function debug_config_arrays() {
	global $menu;
	$menu[__('Utilities')]['plugins/debug/debug.php'] = __('Debug');
}

function debug_poller_output (&$rrd_update_array) {
	$checks = db_fetch_assoc('SELECT * FROM plugin_debug WHERE `done` = 0');
	
	foreach ($checks as $c) {
		foreach($rrd_update_array as $item) {
			if ($c['datasource'] == $item['local_data_id']) {
				if (isset($item['times'][key($item['times'])])) {
					$c['info'] = unserialize($c['info']);
					$c['info']['last_result'] = $item['times'][key($item['times'])];
					$c['info'] = serialize($c['info']);
					db_execute_prepared('UPDATE plugin_debug SET `info` = ? WHERE `id` = ?', array($c['info'], $c['id']));
				}
			}
		}
	}
	return $rrd_update_array;
}

function debug_poller_bottom () {
	global $config;
	$checks = db_fetch_assoc('SELECT * FROM plugin_debug WHERE `done` = 0');

	if (!empty($checks)) {
		clearstatcache();

		foreach ($checks as $c) {
			$c['issue'] = array();
			$info = unserialize($c['info']);

			$dtd = db_fetch_row_prepared('SELECT * from data_template_data WHERE local_data_id = ?', array($c['datasource']));

			if (!isset($dtd['local_data_id'])) {
				$c['issue'][] = __('Data Source does not exist','DSDEBUG');
				$c['done'] = 1;
			} else {
				if (read_config_option('boost_rrd_update_enable') == 'on') {
					boost_process_poller_output($c['datasource']);
				}

				$real_pth = str_replace('<path_rra>', $config['rra_path'], $dtd['data_source_path']);

				// rrd_folder_writable
				$info['rrd_folder_writable'] = (is_writable(dirname($real_pth)) ? 1 : 0);

				// rrd_exists
				$info['rrd_exists'] = (file_exists($real_pth) ? 1 : 0);

				// rrd_writable
				$info['rrd_writable'] = (is_writable($real_pth) ? 1 : 0);

				// active
				$info['active'] = $dtd['active'];
				// owner
				$o = posix_getpwuid(fileowner($real_pth));
				$o = $o['name'];
				$g = posix_getgrgid(filegroup($real_pth));
				$g = $g['name'];
				$info['owner'] =  $o . ':' . $g;

				// poller_runas
				$processUser = posix_getpwuid(posix_geteuid());
				$info['poller_runas'] = $processUser['name'];

				// convert_name
				$info['convert_name'] = (strpos('|', get_data_source_title($c['datasource'])) === FALSE ? 1 : 0);

				// last_result  (processed by hook)
				if (is_array($info['last_result']) && !empty($info['last_result']) && $info['valid_data'] == '') {
					$info['valid_data'] = 1;
					foreach ($info['last_result'] as $k => $l) {
						if ($l == 'U') {
							cacti_log("Bad Data Found", false, 'DSDEBUG');
							$info['valid_data'] = 0;
						}
					}
				}

				// rrd_match
				$rrdinfo = rrdtool_function_info($c['datasource']);
				$comp = rrdtool_cacti_compare($c['datasource'], $rrdinfo);
				$info['rrd_match'] = (is_array($comp) && empty($comp) ? 1 : 0);
				$info['rrd_match_array'] = $comp;
				$info['rrd_info'] = $rrdinfo;

				// rra_timestamp
				if ($info['rra_timestamp'] != '' && isset($rrdinfo['last_update']) && $info['rra_timestamp'] != $rrdinfo['last_update']) {
					$info['rra_timestamp2'] = $rrdinfo['last_update'];
				}
				if (isset($rrdinfo['last_update']) && $info['rra_timestamp'] == '') {
					$info['rra_timestamp'] = $rrdinfo['last_update'];
				}

				$c['done'] = 1;
				foreach ($info as $k => $v) {
					if ($v === '') {
						$c['done'] = 0;
						break;
					}
				}

				if ($c['started'] < time() - ($dtd['rrd_step'] * 5)) {
					$c['done'] = 1;
					$c['issue'][] = __('Debug not completed after 5 pollings');
				}

				if ($c['done'] == 1) {
					// Try to determine issue
					// Not set as Active
					if ($info['active'] != 'on') {
						$c['issue'][] = __('Data Source is not set as Active');
					}

					// File Permissions
					if ((!$info['rrd_exists'] || !$info['rrd_writable']) && !$info['rrd_folder_writable']) {
						$c['issue'][] = __('RRD Folder is not writable by Poller.  RRD Owner: ') . $o . __(' Poller RunAs: ') . $info['poller_runas'];
					} elseif (!$info['rrd_writable']) {
						$c['issue'][] = __('RRD File is not writable by Poller.  RRD Owner: ') . $o . __(' Poller RunAs: ') . $info['poller_runas'];
					}

					if ($info['rrd_match'] == 0) {
						$c['issue'][] = __('RRD File does not match Data Profile');
					}

					if ($info['rra_timestamp2'] == '') {
						$c['issue'][] = __('RRD File not updated after polling');
					}
					if (is_array($info['last_result']) && !empty($info['last_result'])) {
						foreach ($info['last_result'] as $k => $l) {
							if ($l == 'U') {
								$c['issue'][] = __('Data Source returned Bad Results for ' . $k);
							}
						}
					} elseif ($info['last_result'] == '') {
						$c['issue'][] = __('Data Source was not polled');
					}

					if ($c['issue'] == '') {
						$c['issue'][] = __('No issues found');
					}
				}
			}

			$info = serialize($info);
			db_execute_prepared('UPDATE plugin_debug SET `done` = ?, `info` = ?, `issue` = ? WHERE id = ?', array($c['done'], $info, trim(implode("\n", $c['issue'])), $c['id']));
		}
	}
}

function plugin_debug_setup_table() {
	$data = array();
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Datasource Debugger Information';

	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'started', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'done', 'type' => 'int(11)', 'NULL' => false, 'DEFAULT' => '0');
	$data['columns'][] = array('name' => 'user', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'datasource', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'info', 'type' => 'text', 'NULL' => false);
	$data['columns'][] = array('name' => 'issue', 'type' => 'text', 'NULL' => false);

	$data['keys'][] = array('name' => 'user', 'columns' => 'user');
	$data['keys'][] = array('name' => 'done', 'columns' => 'done');
	$data['keys'][] = array('name' => 'datasource', 'columns' => 'datasource');
	$data['keys'][] = array('name' => 'started', 'columns' => 'started');

	api_plugin_db_table_create ('debug', 'plugin_debug', $data);
}
