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
	if (!permission_exists('bulk_account_settings_destinations')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//validate the token
	$token = new token;
	if (!$token->validate_request(dirname($_SERVER['PHP_SELF']) . '/bulk_account_settings_destinations_update.php')) {
		message::add($text['message-invalid_token'],'negative');
		header('Location: bulk_account_settings_destinations.php');
		exit;
	}

//set options
	$destination_options = [];
	$destination_options[] = 'destination_actions';
	$destination_options[] = 'destination_hold_music';
	$destination_options[] = 'destination_record';
	$destination_options[] = 'destination_accountcode';
	$destination_options[] = 'destination_cid_name_prefix';
	$destination_options[] = 'destination_enabled';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//check for the ids
	if (!empty($_REQUEST)) {
		$destination_uuids = preg_replace('#[^a-fA-F0-9\-]#', '', $_REQUEST["id"] ?? '');
		$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_REQUEST["option_selected"] ?? '');
		//stop loading if it is not a valid value
		if (!empty($option_selected) && !in_array($option_selected, $destination_options)) {
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
		$new_setting = $_REQUEST["new_setting"] ?? '';
		
		//sanitize new_setting based on option type
		if ($option_selected == 'destination_actions') {
			//keep as-is for destination actions (will be parsed below)
		}
		elseif ($option_selected == 'destination_enabled' || $option_selected == 'destination_record') {
			$new_setting = ($new_setting == 'true') ? 'true' : (($new_setting == 'false') ? 'false' : '');
		}
		else {
			$new_setting = preg_replace('#[^a-zA-Z0-9_ \-/@\.\$\:]#', '', $new_setting);
			//prohibit double dash --
			$new_setting = str_replace('--', '', $new_setting);
		}
		
		//set parameter for query
		$parameters = [];
		$parameters['domain_uuid'] = $domain_uuid;
		//set the index and array for the save array
		$array = [];
		$cache = new cache;
		
		foreach($destination_uuids as $i => $destination_uuid) {
			if (is_uuid($destination_uuid)) {
				//get the destination details
				$sql = "select destination_number, destination_context from v_destinations ";
				$sql .= "where domain_uuid = :domain_uuid ";
				$sql .= "and destination_uuid = :destination_uuid ";
				$parameters['destination_uuid'] = $destination_uuid;
				$dest_row = $database->select($sql, $parameters, 'row');
				
				if (!empty($dest_row)) {
					$destination_number = $dest_row["destination_number"];
					$destination_context = $dest_row["destination_context"];
					
					$array["destinations"][$i]["domain_uuid"] = $domain_uuid;
					$array["destinations"][$i]["destination_uuid"] = $destination_uuid;
					
					//handle destination_actions specially - it's a JSON field
					if ($option_selected == 'destination_actions') {
						//parse the new_setting which is in format "app:data"
						if (!empty($new_setting)) {
							$action_parts = explode(':', $new_setting, 2);
							$destination_app = $action_parts[0] ?? '';
							$destination_data = $action_parts[1] ?? '';
							
							$actions_array = [];
							$actions_array[0]['destination_app'] = $destination_app;
							$actions_array[0]['destination_data'] = $destination_data;
							
							$array["destinations"][$i]["destination_actions"] = json_encode($actions_array);
							$array["destinations"][$i]["destination_app"] = $destination_app;
							$array["destinations"][$i]["destination_data"] = $destination_data;
						}
					}
					else {
						$array["destinations"][$i][$option_selected] = $new_setting;
					}

					//clear the cache
					$cache->delete("dialplan:".$destination_context);
				}
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
	header("Location: bulk_account_settings_destinations.php?option_selected=".$option_selected."");
	return;
