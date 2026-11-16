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
	if (!permission_exists('bulk_account_settings_users')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//validate the token
	$token = new token;
	if (!$token->validate_request(dirname($_SERVER['PHP_SELF']) . '/bulk_account_settings_users_update.php')) {
		message::add($text['message-invalid_token'],'negative');
		header('Location: bulk_account_settings_users.php');
		exit;
	}

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//set valid user options
	$user_options = [];
	$user_options[] = 'user_enabled';
	$user_options[] = 'user_status';
	$user_options[] = 'password';
	$user_options[] = 'time_zone';
	$user_options[] = 'group';

//get list of groups where group_uuid => group_name
	$groups = [];
	$sql = "select group_uuid, group_name, group_level from v_groups";
	$rows = $database->select($sql);
	if (!empty($rows)) {
		foreach ($rows as $row) {
			$groups[$row['group_uuid']] = $row['group_name'];
			$group_levels[$row['group_uuid']] = intval($row['group_level'] ?? 0);
		}
	}

//ensure the user level is available
	if (empty($_SESSION['user_level'])) {
		$sql = "select max(group_level) as level from v_user_groups ug";
		$sql .= " left join v_groups g on g.group_uuid = ug.group_uuid";
		$sql .= " where user_uuid = :user_uuid";
		$parameters = [];
		$parameters['user_uuid'] = $_SESSION['user_uuid'];
		$result = $database->select($sql, $parameters, 'column');
		if (!empty($result)) {
			$_SESSION['user_level'] = intval($result);
		}
	}

//check for the ids
	if (!empty($_REQUEST)) {
		$user_uuids = preg_replace('#[^a-fA-F0-9_\-]#', '', $_REQUEST['id'] ?? '');
		$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_REQUEST['option_selected'] ?? '');
		$action = preg_replace('#[^a-zA-Z0-9_]#', '', $_REQUEST['action'] ?? 'update');
		if (!in_array($option_selected, $user_options, true)) {
			header('HTTP/1.1 400 Bad Request');
			echo "<!DOCTYPE html>\n";
			echo "<html>\n";
			echo "  <head><title>400 Bad Request</title></head>\n";
			echo "  <body bgcolor=\"white\">\n";
			echo "    <center><h1>400 Bad Request</h1></center>\n";
			echo "  </body>\n";
			echo "</html>\n";
			exit();
		}
		//set parameter for query
		$parameters = [];
		$parameters['domain_uuid'] = $domain_uuid;
		foreach ($user_uuids as $i => $user_uuid) {
			//ensure valid uuid
			if (!is_uuid($user_uuid)) {
				continue;
			}
			//only modify users that belong to the current domain
			$sql = "select user_uuid from v_users ";
			$sql .= "where domain_uuid = :domain_uuid ";
			$sql .= "and user_uuid = :user_uuid ";
			$parameters['user_uuid'] = $user_uuid;
			$user_row = $database->select($sql, $parameters, 'row');
			if (empty($user_row)) {
				continue;
			}
			switch ($option_selected) {
				case 'user_status':
				case 'user_enabled':
					$array["users"][$i]["domain_uuid"] = $domain_uuid;
					$array["users"][$i]["user_uuid"] = $user_uuid;
					$array["users"][$i][$option_selected] = $_REQUEST["new_setting"];
					break;
				case 'password':
					$array["users"][$i]["domain_uuid"] = $domain_uuid;
					$array["users"][$i]["user_uuid"] = $user_uuid;
					// set strength from 0 to 4
					$password_strength = 0;
					$password_strength += intval($settings->get('user', 'password_number'   , true));
					$password_strength += intval($settings->get('user', 'password_lowercase', true));
					$password_strength += intval($settings->get('user', 'password_uppercase', true));
					$password_strength += intval($settings->get('user', 'password_special'  , true));
					//generate a password using strength and length (default of 20 characters if not set)
					$array['users'][$i]['password'] = generate_password($settings->get('user', 'password_length', 20), $password_strength);
					break;
				case 'time_zone':
					// get the existing user_setting_uuid
					$sql = "select user_setting_uuid from v_user_settings"
						. " where"
						. "  user_uuid = :user_uuid"
						. " and user_setting_category = 'domain'"
						. " and user_setting_subcategory = 'time_zone'"
						. " and user_setting_enabled = 'true' "
						. "limit 1";
					$parameters = [];
					$parameters['user_uuid'] = $user_uuid;
					$user_setting_uuid = $database->select($sql, $parameters, 'column');
					if (empty($user_setting_uuid) || !is_uuid($user_setting_uuid)) {
						$user_setting_uuid = uuid();
						//enable new records
						$array["user_settings"][$i]["user_setting_enabled"] = true;
					}
					$array['user_settings'][$i]['domain_uuid'             ] = $domain_uuid;
					$array['user_settings'][$i]['user_uuid'               ] = $user_uuid;
					$array['user_settings'][$i]['user_setting_uuid'       ] = $user_setting_uuid;
					$array['user_settings'][$i]['user_setting_category'   ] = 'domain';
					$array['user_settings'][$i]['user_setting_name'       ] = 'name';
					$array['user_settings'][$i]['user_setting_subcategory'] = 'time_zone';
					$array['user_settings'][$i]['user_setting_value'      ] = $_REQUEST["new_setting"];
					break;
				case 'group':
					$group_uuid = $_REQUEST['group_uuid'];
					if (is_uuid($group_uuid) && !empty($groups[$group_uuid])) {
						//check current user is not trying to assign to a higher level group
						if ($_SESSION['user_level'] >= $group_levels[$group_uuid]) {
							$array['user_groups'][$i]['domain_uuid'] = $domain_uuid;
							$array['user_groups'][$i]['user_uuid'  ] = $user_uuid;
							$array['user_groups'][$i]['group_uuid' ] = $group_uuid;
							$array['user_groups'][$i]['group_name' ] = $groups[$group_uuid];
						}
					}
					break;
			}
		}
		if (!empty($array) && ($action == 'update' || $action == 'add')) {
			$database->save($array);
			$message = $database->message;
		}
		if (!empty($array) && $action == 'remove') {
			$database->delete($array);
			$message = $database->message;
		}
	}

//redirect the browser
	$_SESSION["message"] = $text['message-update'];
	header("Location: bulk_account_settings_users.php?option_selected=".$option_selected."");
	return;
