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
	Portions created by the Initial Developer are Copyright (C) 2008-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('bulk_account_settings_gateways')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set options
	$gateway_options = [];
	$gateway_options[] = 'gateway';
	$gateway_options[] = 'proxy';
	$gateway_options[] = 'context';
	$gateway_options[] = 'enabled';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//check for the ids
	if (!empty($_REQUEST)) {
		$gateway_uuids = preg_replace('#[^a-fA-F0-9\-]#', '', $_REQUEST["id"] ?? '');
		$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_REQUEST["option_selected"] ?? '');
		//stop loading if it is not a valid value
		if (!empty($option_selected) && !in_array($option_selected, $gateway_options)) {
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
		$new_setting = preg_replace('#[^a-zA-Z0-9_ \-/@\.\$\:]#', '',$_REQUEST["new_setting"] ?? '');
		//prohibit double dash --
		$new_setting = str_replace('--', '', $new_setting);
		//set parameter for query
		$parameters = [];
		$parameters['domain_uuid'] = $domain_uuid;
		//set the index and array for the save array
		$array = [];
		$cache = new cache;
		foreach($gateway_uuids as $i => $gateway_uuid) {
			if (is_uuid($gateway_uuid)) {
				//get the gateways array
				$sql = "select * from v_gateways ";
				$sql .= "where domain_uuid = :domain_uuid ";
				$sql .= "and gateway_uuid = :gateway_uuid ";
				$parameters['gateway_uuid'] = $gateway_uuid;
				$gateways = $database->select($sql, $parameters, 'all');
				if (is_array($gateways)) {
					foreach ($gateways as $row) {
						$gateway = $row["gateway"];
					}
				}

				$array["gateways"][$i]["domain_uuid"] = $domain_uuid;
				$array["gateways"][$i]["gateway_uuid"] = $gateway_uuid;
				$array["gateways"][$i][$option_selected] = $new_setting;
			}
		}
		if (!empty($array)) {
			//save modifications
			$database->save($array);
			$message = $database->message;
		}

	}

//redirect the browser
	$_SESSION["message"] = $text['message-update'];
	header("Location: bulk_account_settings_gateways.php?option_selected=".$option_selected."");
	return;
