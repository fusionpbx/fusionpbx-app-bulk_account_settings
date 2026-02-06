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
	Portions created by the Initial Developer are Copyright (C) 2008-2016
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('bulk_account_settings_voicemails')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set domain and user uuids
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';

//set valid option and action choices
	$voicemail_options = [];
	$voicemail_options[] = 'voicemail_file';
	$voicemail_options[] = 'voicemail_enabled';
	$voicemail_options[] = 'voicemail_local_after_email';
	$voicemail_options[] = 'voicemail_transcription_enabled';
	$voicemail_options[] = 'voicemail_password';
	$voicemail_options[] = 'voicemail_option_0';
	$voicemail_options[] = 'voicemail_option_1';
	$voicemail_options[] = 'voicemail_option_2';
	$voicemail_options[] = 'voicemail_option_3';
	$voicemail_options[] = 'voicemail_option_4';
	$voicemail_options[] = 'voicemail_option_5';
	$voicemail_options[] = 'voicemail_option_6';
	$voicemail_options[] = 'voicemail_option_7';
	$voicemail_options[] = 'voicemail_option_8';
	$voicemail_options[] = 'voicemail_option_9';
	$voicemail_options[] = 'voicemail_option_star';
	if (permission_exists('bulk_account_settings_pound')) {
		$voicemail_options[] = 'voicemail_option_pound';
	}
	$voicemail_actions = [];
	$voicemail_actions[] = 'add';
	$voicemail_actions[] = 'remove';

//use connected database
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);

//check for the ids
	if (!empty($_REQUEST)) {

		$voicemail_uuids = $_REQUEST["id"];
		$option_selected = $_REQUEST["option_selected"];
		$new_setting = $_REQUEST["new_setting"];
		$option_action = $_REQUEST["option_action"];
		$voicemail_option_param = $_REQUEST["voicemail_option_param"];
		$voicemail_option_order = (int)$_REQUEST["voicemail_option_order"];
		$voicemail_option_description = $_REQUEST["voicemail_option_description"];

		//validate the option_selected
		if (!empty($option_selected) && !in_array($option_selected, $voicemail_options, true)) {
			die('invalid option');
		}

		//validate the option_action
		if (!empty($option_action) && !in_array($option_action, $voicemail_actions, true)) {
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

		//seperate the action and the param
		$option_array = explode(":", $voicemail_option_param);
		$voicemail_option_action = array_shift($option_array);
		$voicemail_option_param = join(':', $option_array);
		preg_match ('/voicemail_option_(.)/',$option_selected, $matches);
		$voicemail_option_digits = $matches[1];

		foreach($voicemail_uuids as $voicemail_uuid) {
			$voicemail_uuid = check_str($voicemail_uuid);
			if ($voicemail_uuid != '') {
			//Voicemail Options
				if (preg_match ('/voicemail_option_/',$option_selected)) {
					//Add Options
					if ($option_action == 'add'){

						$sql = "select * from v_voicemail_options ";
						$sql .= "where domain_uuid = :domain_uuid ";
						$sql .= "and voicemail_uuid = :voicemail_uuid ";
						$sql .= "and voicemail_option_digits = voicemail_option_digits ";
						$sql .= "and voicemail_option_order = :voicemail_option_order ";
						$parameters = [];
						$parameters['domain_uuid'] = $domain_uuid;
						$parameters['voicemail_uuid'] = $voicemail_uuid;
						$parameters['voicemail_option_digits'] = $voicemail_option_digits;
						$parameters['voicemail_option_order'] = $voicemail_option_order;
						$voicemails = $database->select($sql, $parameters, 'all');
						if (is_array($voicemails)) {
							foreach ($voicemails as $row) {
								$voicemail_option_uuid = $row["voicemail_option_uuid"];
							}
						}
						if (empty($voicemail_option_uuid)) {
							$voicemail_option_uuid = uuid();
						}

						$i=0;
						$array["voicemail_options"][$i]["voicemail_option_uuid"] = $voicemail_option_uuid;
						$array["voicemail_options"][$i]["domain_uuid"] = $domain_uuid;
						$array["voicemail_options"][$i]["voicemail_uuid"] = $voicemail_uuid;
						$array["voicemail_options"][$i]["voicemail_option_digits"] = $voicemail_option_digits;
						$array["voicemail_options"][$i]["voicemail_option_description"] = $voicemail_option_description;
						$array["voicemail_options"][$i]["voicemail_option_order"] = (int)$voicemail_option_order;
						$array["voicemail_options"][$i]["voicemail_option_action"] = trim($voicemail_option_action);
						$array["voicemail_options"][$i]["voicemail_option_param"] = trim($voicemail_option_param);

						$database->app_name = 'bulk_account_settings';
						$database->app_uuid = '6b4e03c9-c302-4eaa-b16d-e1c5c08a2eb7';
						$database->save($array);
						$message = $database->message;

						unset($array,$i,$voicemail_option_uuid);

					} elseif ($option_action == 'remove') {
					//delete the voicemail option
						$sql = "delete from v_voicemail_options ";
						$sql .= "where domain_uuid = :domain_uuid ";
						$sql .= "and voicemail_uuid = :voicemail_uuid ";
						$sql .= "and voicemail_option_digits = :voicemail_option_digits ";
						$parameters = [];
						$parameters['domain_uuid'] = $domain_uuid;
						$parameters['voicemail_uuid'] = $voicemail_uuid;
						$parameters['voicemail_option_digits'] = $voicemail_option_digits;
						$database->execute($sql, $parameters);
						unset($parameters, $sql);
					}

				} else {
				//All other Voicemail properties
				//get the voicemails array
					$sql = "select * from v_voicemails ";
					$sql .= "where domain_uuid = :domain_uuid ";
					$sql .= "and voicemail_uuid = :voicemail_uuid ";
					$parameters = [];
					$parameters['domain_uuid'] = $domain_uuid;
					$parameters['voicemail_uuid'] = $voicemail_uuid;
					$voicemails = $database->select($sql, $parameters, 'all');
					if (is_array($voicemails)) {
						foreach ($voicemails as $row) {
							$voicemail = $row["voicemail"];
						}

						$array["voicemails"][$i]["domain_uuid"] = $domain_uuid;
						$array["voicemails"][$i]["voicemail_uuid"] = $voicemail_uuid;
						$array["voicemails"][$i][$option_selected] = $new_setting;

						$database->app_name = 'bulk_account_settings';
						$database->app_uuid = '6b4e03c9-c302-4eaa-b16d-e1c5c08a2eb7';
						$database->save($array);
						$message = $database->message;

						unset($array,$i);
					}
				}
			}
		}
	}

//redirect the browser
	$_SESSION["message"] = $text['message-update'];
	header("Location: bulk_account_settings_voicemails.php?option_selected=".$option_selected."");
	return;

?>
