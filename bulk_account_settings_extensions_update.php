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
	if (!permission_exists('bulk_account_settings_extensions')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set options
	$extension_options = [];
	$extension_options[] = 'accountcode';
	$extension_options[] = 'call_group';
	$extension_options[] = 'call_timeout';
	$extension_options[] = 'context';
	$extension_options[] = 'emergency_caller_id_name';
	$extension_options[] = 'emergency_caller_id_number';
	$extension_options[] = 'enabled';
	$extension_options[] = 'directory_visible';
	$extension_options[] = 'user_record';
	$extension_options[] = 'hold_music';
	$extension_options[] = 'limit_max';
	$extension_options[] = 'outbound_caller_id_name';
	$extension_options[] = 'outbound_caller_id_number';
	$extension_options[] = 'toll_allow';
	$extension_options[] = 'sip_force_contact';
	$extension_options[] = 'sip_force_expires';
	$extension_options[] = 'sip_bypass_media';
	$extension_options[] = 'mwi_account';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//check for the ids
	if (!empty($_REQUEST)) {
		$extension_uuids = preg_replace('#[^a-fA-F0-9\-]#', '', $_REQUEST["id"] ?? '');
		$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_REQUEST["option_selected"] ?? '');
		//stop loading if it is not a valid value
		if (!empty($option_selected) && !in_array($option_selected, $extension_options)) {
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
		foreach($extension_uuids as $i => $extension_uuid) {
			if (is_uuid($extension_uuid)) {
				//get the extensions array
				$sql = "select extension, user_context, number_alias from v_extensions ";
				$sql .= "where domain_uuid = :domain_uuid ";
				$sql .= "and extension_uuid = :extension_uuid ";
				$parameters['extension_uuid'] = $extension_uuid;
				$extensions = $database->select($sql, $parameters, 'all');
				if (is_array($extensions)) {
					foreach ($extensions as $row) {
						$extension = $row["extension"];
						$user_context = $row["user_context"];
						$number_alias = $row["number_alias"];
					}
				}

				$array["extensions"][$i]["domain_uuid"] = $domain_uuid;
				$array["extensions"][$i]["extension_uuid"] = $extension_uuid;
				if ($option_selected == 'context') {
					$array["extensions"][$i]["user_context"] = $new_setting;
				}
				else {
					$array["extensions"][$i][$option_selected] = $new_setting;
				}

				//clear the cache
				$cache->delete("directory:".$extension."@".$user_context);
				if (permission_exists('number_alias') && strlen($number_alias) > 0) {
					$cache->delete("directory:".$number_alias."@".$user_context);
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
	header("Location: bulk_account_settings_extensions.php?option_selected=".$option_selected."");
	return;
