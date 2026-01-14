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

*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('bulk_account_settings_destinations')) {
		die("access denied");
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set options
	$destination_options = [];
	$destination_options[] = 'destination_actions';
	$destination_options[] = 'destination_hold_music';
	$destination_options[] = 'destination_record';
	$destination_options[] = 'destination_cid_name_prefix';
	$destination_options[] = 'destination_enabled';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//get the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');

//validate the option_selected
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

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_ \-\+]#', '', $_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_mod = "and ( ";
		$sql_mod .= "lower(destination_number) like :search ";
		$sql_mod .= "or lower(destination_description) like :search ";
		$sql_mod .= "or lower(destination_accountcode) like :search ";
		$sql_mod .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if (empty($order_by)) {
		$order_by = "destination_number";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get total destination count from the database
	$sql = "select count(destination_uuid) as num_rows from v_destinations where domain_uuid = :domain_uuid and destination_type = 'inbound' ".$sql_mod." ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	if (!empty($result)) {
		$total_destinations = intval($result);
	} else {
		$total_destinations = 0;
	}
	unset($sql);

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_destinations, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_destinations, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get all the destinations from the database
	$sql = "SELECT ";
	$sql .= "destination_uuid, ";
	$sql .= "destination_number, ";
	$sql .= "destination_description, ";
	$sql .= "destination_accountcode, ";
	$sql .= "destination_cid_name_prefix, ";
	$sql .= "destination_hold_music, ";
	$sql .= "destination_record, ";
	$sql .= "destination_actions, ";
	$sql .= "destination_enabled ";
	$sql .= "FROM v_destinations ";
	$sql .= "WHERE domain_uuid = :domain_uuid ";
	$sql .= "AND destination_type = 'inbound' ";
	//add search mod from above
	if (!empty($sql_mod)) {
		$sql .= $sql_mod;
	}
	if ($rows_per_page > 0) {
		$sql .= "ORDER BY $order_by $order ";
		$sql .= "limit $rows_per_page offset $offset ";
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$destinations = $database->select($sql, $parameters, 'all');
	if ($destinations === false) {
		$destinations = [];
	}

//get the destination select list for action name display
	$destination_obj = new destinations;
	$destination_array = $destination_obj->all('dialplan');

//function to return the action names in the order defined
	function action_name($destination_array, $destination_actions, $settings) {
		$actions = [];
		if (!empty($destination_array) && is_array($destination_array)) {
			if (!empty($destination_actions) && is_array($destination_actions)) {
				foreach ($destination_actions as $destination_action) {
					if (!empty($destination_action)) {
						foreach ($destination_array as $group => $row) {
							if (!empty($row) && is_array($row)) {
								foreach ($row as $key => $value) {
									if ($destination_action == $value) {
										if ($group == 'other') {
											if (file_exists(dirname(__DIR__, 2)."/app/dialplans/app_languages.php")) {
												$language2 = new text;
												$text2 = $language2->get($settings->get('domain', 'language', 'en-us'), 'app/dialplans');
											}
											$actions[] = trim($text2['title-other'].' &#x203A; '.$text2['option-'.str_replace('&lowbar;','_',$key)]);
										}
										else {
											// For extensions, just show the extension number and description (the $key value)
											// For other apps, show the app name with the key
											if ($group == 'extensions') {
												$actions[] = trim($key);
											}
											else {
												if (file_exists(dirname(__DIR__, 2)."/app/".$group."/app_languages.php")) {
													$language3 = new text;
													$text3 = $language3->get($settings->get('domain', 'language', 'en-us'), 'app/'.$group);
													$actions[] = trim($text3['title-'.$group].' &#x203A; '.$key);
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return $actions;
	}

//additional includes
	$document['title'] = $text['title-destination_settings'];
	require_once "resources/header.php";



//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-destinations']."</b><div class='count'>".number_format($total_destinations)."</div><br><br>\n";
	echo "		".$text['description-destination_settings']."\n";
	echo "	</div>\n";

	echo "	<div class='actions'>\n";
	echo "		<form method='get' action=''>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','style'=>'margin-right: 15px; position: sticky; z-index: 5;','onclick'=>"window.location='bulk_account_settings.php'"]);
	echo 			"<input type='text' class='txt list-search' name='search' id='search' style='margin-left: 0 !important;' value=\"".escape($search)."\" placeholder=\"".$text['label-search']."\" onkeydown=''>";
	echo "			<input type='hidden' class='txt' style='width: 150px' name='option_selected' id='option_selected' value='".escape($option_selected)."'>";
	echo "			<form id='form_search' class='inline' method='get'>\n";
	echo button::create(['label'=>$text['button-search'],'icon'=>$settings->get('theme', 'button_icon_search'),'type'=>'submit','id'=>'btn_search']);
	if (!empty($paging_controls_mini)) {
		echo "			<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "			</form>\n";
	echo "		</form>\n";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//options list
	echo "<div class='card'>\n";
	echo "<div class='form_grid'>\n";

	echo "	<div class='form_set'>\n";
	echo "		<div class='label'>\n";
	echo "			".$text['label-setting']."\n";
	echo "		</div>\n";
	echo "		<div class='field'>\n";
	echo "			<form name='frm' method='get' id='option_selected'>\n";
	echo "			<select class='formfld' name='option_selected' onchange=\"this.form.submit();\">\n";
	echo "				<option value=''></option>\n";
	foreach ($destination_options as $option) {
		echo "			<option value='".$option."' ".($option_selected === $option ? "selected='selected'" : null).">".$text['label-'.$option]."</option>\n";
	}
	echo "  		</select>\n";
	echo "			</form>\n";
	echo "		</div>\n";
	echo "	</div>\n";

	if (!empty($option_selected)) {

		echo "	<div class='form_set'>\n";
		echo "		<div class='label'>\n";
		echo "			".$text['label-value']."";
		echo "		</div>\n";
		echo "		<div class='field'>\n";

		echo "			<form name='destinations' method='post' action='bulk_account_settings_destinations_update.php'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//destination_actions - use destinations select
		if ($option_selected == 'destination_actions') {
			$destination_obj = new destinations;
			echo $destination_obj->select('dialplan', 'new_setting', '');
		}

		//text input
		if ($option_selected == 'destination_cid_name_prefix') {
			echo "		<input class='formfld' type='text' name='new_setting' maxlength='255' value=''>\n";
		}

		//enabled
		if ($option_selected === 'destination_enabled') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//record
		if ($option_selected == 'destination_record') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//hold music
		if ($option_selected == 'destination_hold_music') {
			if (is_dir($_SERVER["DOCUMENT_ROOT"].PROJECT_PATH.'/app/music_on_hold')) {
				require_once "app/music_on_hold/resources/classes/switch_music_on_hold.php";
				$options = '';
				$moh = new switch_music_on_hold;
				echo $moh->select('new_setting', '', $options);
			}
		}

		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','link'=>'bulk_account_settings_destinations.php']);
		echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_destinations']."')) { document.forms.destinations.submit(); }"]);
		echo "</div>\n";

	}
	else {
		echo "</div>\n";
	}

	echo "</div>\n";
	echo "<br />\n";

	echo "<div class='card'>\n";
	echo "<table class='list'>\n";
	echo "<tr class='list-header'>\n";
	if (!empty($destinations)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('destination_number', $text['label-destination_number'], $order_by, $order, null, null, $param);
	if (!empty($option_selected) && $option_selected != 'destination_enabled') {
		echo th_order_by('destination_enabled', $text['label-enabled'], $order_by, $order, null, null, $param);
	}
	if (!empty($option_selected) && $option_selected != 'destination_description') {
		echo th_order_by($option_selected, $text["label-".$option_selected.""], $order_by, $order, null, null, $param);
	}
	echo th_order_by('destination_description', $text['label-description'], $order_by, $order, null, null, $param);
	echo "</tr>\n";

	$dest_ids = [];
	if (!empty($destinations)) {
		foreach($destinations as $key => $row) {
			$list_row_url = permission_exists('destination_edit') ? "/app/destinations/destination_edit.php?id=".urlencode($row['destination_uuid']) : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['destination_uuid'])."' value='".escape($row['destination_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$dest_ids[] = 'checkbox_'.$row['destination_uuid'];
			echo "	<td><a href='".$list_row_url."'>".escape(format_phone($row['destination_number']))."</a></td>\n";
			if (!empty($option_selected) && $option_selected != 'destination_enabled') {
				echo "	<td>".escape($text['label-'.($row['destination_enabled'] ?? 'false')])."&nbsp;</td>\n";
			}
			if (!empty($option_selected) && $option_selected != 'destination_description') {
				$value = $row[$option_selected] ?? '';
				// Convert boolean-like values to true/false labels for display
				if ($option_selected == 'destination_record' || $option_selected == 'destination_enabled') {
					if ($value === '1' || $value === 'true' || $value === true) {
						$value = $text['label-true'];
					} elseif ($value === '0' || $value === 'false' || $value === false || $value === '' || $value === null) {
						$value = $text['label-false'];
					}
				}
				// Clean up hold music display - show just the name without local_stream:// prefix
				if ($option_selected == 'destination_hold_music') {
					if (empty($value) || $value === 'silence') {
						$value = $text['label-none'] ?? 'None';
					} elseif (strpos($value, 'local_stream://') === 0) {
						$value = str_replace('local_stream://', '', $value);
					}
				}
				// Parse destination_actions JSON and display readable names
				if ($option_selected == 'destination_actions') {
					if (!empty($value)) {
						$actions_json = json_decode($value, true);
						if (!empty($actions_json) && is_array($actions_json)) {
							// Build the app:data format that action_name expects
							$destination_app_data = [];
							foreach ($actions_json as $action) {
								if (!empty($action['destination_app']) && !empty($action['destination_data'])) {
									$destination_app_data[] = $action['destination_app'].':'.$action['destination_data'];
								}
							}
							// Use action_name function to get readable names
							$action_names = action_name($destination_array, $destination_app_data, $settings);
							$value = (!empty($action_names)) ? implode(', ', $action_names) : '';
						}
					}
					if (empty($value)) {
						$value = $text['label-none'] ?? 'None';
					}
				}
				echo "	<td>".escape($value)."&nbsp;</td>\n";
			}
			echo "	<td>".escape($row['destination_description'])."&nbsp;</td>\n";
			echo "</tr>\n";
		}
	}

	echo "</table>\n";
	echo "</div>\n";
	echo "</form>\n";

	if (!empty($paging_controls)) {
		echo "<br />\n";
		echo $paging_controls."\n";
	}
	echo "<br /><br />".((!empty($destinations)) ? "<br /><br />" : null);

	// check or uncheck all checkboxes
	if (!empty($dest_ids)) {
		echo "<script>\n";
		echo "	function check(what) {\n";
		echo "		document.getElementById('chk_all').checked = (what == 'all') ? true : false;\n";
		foreach ($dest_ids as $dest_id) {
			echo "		document.getElementById('".$dest_id."').checked = (what == 'all') ? true : false;\n";
		}
		echo "	}\n";
		echo "</script>\n";
	}

	if (!empty($destinations)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
