<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('bulk_account_settings_devices')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//validate the token
	$token = new token;
	if (!$token->validate_request(dirname($_SERVER['PHP_SELF']) . '/bulk_account_settings_devices_update.php')) {
		message::add($text['message-invalid_token'],'negative');
		header('Location: bulk_account_settings_devices.php');
		exit;
	}

//device options
	$device_options = [];
	$device_options[] = 'device_enabled';
	$device_options[] = 'device_profile_uuid';
	$device_options[] = 'device_template';
	$device_options[] = 'line_1_server_address';
	$device_options[] = 'line_1_server_address_primary';
	$device_options[] = 'line_1_server_address_secondary';
	$device_options[] = 'line_1_outbound_proxy_primary';
	$device_options[] = 'line_1_outbound_proxy_secondary';
	$device_options[] = 'line_1_sip_port';
	$device_options[] = 'line_1_sip_transport';
	$device_options[] = 'line_1_register_expires';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//check for the ids
	if (!empty($_REQUEST)) {

		$device_uuids = preg_replace('#[^a-fA-F0-9\-]#', '', $_REQUEST["id"] ?? '');
		$option_selected = preg_replace('#[^a-zA-Z0-9_\-]#', '', $_REQUEST["option_selected"] ?? '');
		$new_setting = preg_replace('#[^a-zA-Z0-9_/.\-]#', '', $_REQUEST["new_setting"] ?? '');

		//ensure the option selected is valid
		if (!empty($option_selected) && !in_array($option_selected, $device_options)) {
			header("HTTP/1.1 400 Bad Request");
			echo "<!DOCTYPE html>\n";
			echo "<html>\n";
			echo "  <head><title>400 Bad Request</title></head>\n";
			echo "  <body bgcolor=\"white\">\n";
			echo "    <center><h1>400 Bad Request</h1></center>\n";
			echo "  </body>\n";
			echo "</html>\n";
			exit();
		}

		foreach($device_uuids as $device_uuid) {
			if (is_uuid($device_uuid)) {
				//line settings
				if (preg_match ('/line/', $option_selected)) {

					preg_match ('/line_(.)/', $option_selected, $matches);
					$line_number = $matches[1];
					$matches = null;
					preg_match ('/line_._(.*$)/', $option_selected, $matches);
					$option_line = $matches[1];

					//reset so a previous device's line is never reused
					$device_line_uuid = '';

					$sql = "select * from v_device_lines ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and device_uuid = :device_uuid ";
					$sql .= "and line_number = :line_number ";
					$parameters = [];
					$parameters['domain_uuid'] = $domain_uuid;
					$parameters['device_uuid'] = $device_uuid;
					$parameters['line_number'] = $line_number;
					$devices = $database->select($sql, $parameters, 'all');
					if (is_array($devices)) {
						foreach ($devices as &$row) {
							$device_line_uuid = $row["device_line_uuid"];
						}
					}

					//only modify device lines that belong to the current domain
					if (!empty($device_line_uuid)) {
						$array["device_lines"][0]["device_line_uuid"] = $device_line_uuid;
						$array["device_lines"][0][$option_line] = $new_setting;
						$array["device_lines"][0]["domain_uuid"] = $domain_uuid;
						$array["device_lines"][0]["device_uuid"] = $device_uuid;
						$database->save($array);
					}
				}
				//other device settings
				else {
					//verify the device belongs to the current domain
					$sql = "select device_uuid from v_devices ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and device_uuid = :device_uuid ";
					$parameters = [];
					$parameters['domain_uuid'] = $domain_uuid;
					$parameters['device_uuid'] = $device_uuid;
					$device_row = $database->select($sql, $parameters, 'row');

					if (empty($device_row)) {
						continue;
					}

					$array["devices"][0]["domain_uuid"] = $domain_uuid;
					$array["devices"][0]["device_uuid"] = $device_uuid;
					$array["devices"][0][$option_selected] = $new_setting;
					$database->save($array);
				}
			}
		}
	}

//redirect the browser
	$_SESSION["message"] = $text['message-update'];
	header("Location: bulk_account_settings_devices.php?option_selected=".$option_selected);
	return;
