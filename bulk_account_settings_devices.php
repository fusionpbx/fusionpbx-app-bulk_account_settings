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
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('bulk_account_settings_devices')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

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

//sanitize the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_mod = "and ( ";
		$sql_mod .= "device_address ILIKE :search ";
		$sql_mod .= "or device_label ILIKE :search ";
		$sql_mod .= "or device_vendor ILIKE :search ";
		$sql_mod .= "or device_model ILIKE :search ";
		$sql_mod .= "or device_description ILIKE :search ";
		$sql_mod .= "or device_template ILIKE :search ";
		$sql_mod .= ") ";
		$parameters['search'] = '%' .$search . '%';
	}
	if (empty($order_by)) {
		$order_by = "device_label";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get total device count from the database
	$sql = "select count(device_uuid) as num_rows from v_devices where domain_uuid = :domain_uuid $sql_mod ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	if (!empty($result)) {
		$total_devices = intval($result);
	} else {
		$total_devices = 0;
	}

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_devices, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_devices, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//ensure the selected option is valid
	if (!empty($option_selected) && !in_array($option_selected, $device_options, true)) {
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

//get all the devices from the database
	$parameters = [];
	$sql = "SELECT \n";
	$sql .= "d.device_uuid, \n";
	$sql .= "d.device_label, \n";
	$sql .= "d.device_address, \n";
	$sql .= "d.device_vendor, \n";
	$sql .= "d.device_template, \n";
	$sql .= "d.device_enabled, \n";
	$sql .= "d.device_description, \n";
	$sql .= "(\n";
	$sql .= "select dp.device_profile_name from v_device_profiles as dp \n";
	$sql .= "where d.device_profile_uuid = dp.device_profile_uuid \n";
	$sql .= ") as device_profile_name \n";
	$sql .= "FROM v_devices as d \n";
	$sql .= "WHERE domain_uuid = :domain_uuid \n";
	//add search mod from above
	if (!empty($sql_mod)) {
		$sql .= $sql_mod;
		$parameters['search'] = '%'.$search.'%';
	}
	$sql .= "ORDER BY ".$order_by." ".$order." \n";
	$sql .= "limit $rows_per_page offset $offset ";
	$parameters['domain_uuid'] = $domain_uuid;
	$directory = $database->select($sql, $parameters, 'all');

//lookup the lines
	if (!empty($directory)) {
		foreach ($directory as $key => $row) {
			$parameters = [];
			$sql = "SELECT * \n";
			$sql .= "FROM v_device_lines \n";
			$sql .= "WHERE domain_uuid = :domain_uuid \n";
			$sql .= "and device_uuid = :device_uuid ";
			$sql .= "and line_number = '1' ";
			$sqlview1 = $sql;
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['device_uuid'] = $row['device_uuid'];
			$result = $database->select($sql, $parameters, 'all');
			$directory[$key]['line_1_server_address'] = $result[0]['server_address'];
			$directory[$key]['line_1_server_address_primary'] = $result[0]['server_address_primary'];
			$directory[$key]['line_1_server_address_secondary'] = $result[0]['server_address_secondary'];
			$directory[$key]['line_1_outbound_proxy_primary'] = $result[0]['outbound_proxy_primary'];
			$directory[$key]['line_1_outbound_proxy_secondary'] = $result[0]['outbound_proxy_secondary'];
			$directory[$key]['line_1_sip_port'] = $result[0]['sip_port'];
			$directory[$key]['line_1_sip_transport'] = $result[0]['sip_transport'];
			$directory[$key]['line_1_register_expires'] = $result[0]['register_expires'];
		}
		unset($result);
	}

//additional includes
	$document['title'] = $text['title-device_settings'];

//create a token for the update script (it validates against its own path)
	$object = new token;
	$token = $object->create(dirname($_SERVER['PHP_SELF']) . '/bulk_account_settings_devices_update.php');

	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-devices']."</b><div class='count'>".number_format($total_devices)."</div><br><br>\n";
	echo "		".$text['description-devices_settings']."\n";
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
	foreach ($device_options as $option) {
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

		echo "			<form name='devices' method='post' action='bulk_account_settings_devices_update.php'>\n";
		echo "			<input type='hidden' name='" . $token['name'] . "' value='" . $token['hash'] . "'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//enabled
		if ($option_selected == 'device_enabled') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//device profile
		if ($option_selected == 'device_profile_uuid' && permission_exists('device_profile_edit')) {
			$parameters = [];
			$sql = "select * from v_device_profiles ";
			$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
			$sql .= "order by device_profile_name asc ";
			$parameters['domain_uuid'] = $domain_uuid;
			$result = $database->select($sql, $parameters, 'all');
			if (!empty($result)) {
				$result_count = intval($result);
			} else {
				$result_count = 0;
			}
			unset ($sql);

			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			if ($result_count > 0) {
				foreach ($result as $row) {
					echo "	<option value='".escape($row['device_profile_uuid'])."'>".escape($row['device_profile_name'])." ".(($row['domain_uuid'] == '') ? "&nbsp;&nbsp;(".$text['select-global'].")" : null)."</option>\n";
				}
			}
			echo "		</select>\n";
		}

		//device template
		if ($option_selected == 'device_template' && permission_exists('device_template')) {
			$device = new device;
			$template_dir = $device->get_template_dir();

			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			if (is_dir($template_dir)) {
					$templates = scandir($template_dir);
					foreach ($templates as $dir) {
						if (!empty($dir) && $dir != "." && !empty($dir) && $dir != ".." && $dir[0] != '.') {
							if (is_dir($template_dir . "/" . $dir)) {
								echo "<optgroup label='$dir'>";
								$dh_sub=$template_dir . "/" . $dir;
								if (is_dir($dh_sub)) {
									$templates_sub = scandir($dh_sub);
									foreach ($templates_sub as $dir_sub) {
										if ($dir_sub != '..' && $dir_sub[0] != '.') {
											if (is_dir($template_dir . '/' . $dir .'/'. $dir_sub)) {
												echo "<option value='".$dir."/".$dir_sub."'>".$dir."/".$dir_sub."</option>\n";
											}
										}
									}
								}
								echo "</optgroup>";
							}
						}
					}
				}
			echo "		</select>\n";
		}

		//line 1 text input
		if (
			$option_selected == 'line_1_server_address' ||
			$option_selected == 'line_1_server_address_primary' ||
			$option_selected == 'line_1_server_address_secondary' ||
			$option_selected == 'line_1_outbound_proxy_primary' ||
			$option_selected == 'line_1_outbound_proxy_secondary' ||
			$option_selected == 'line_1_sip_port' ||
			$option_selected == 'line_1_register_expires'
			) {
			echo "		<input class='formfld' type='text' name='new_setting' maxlength='255' value=\"".escape($new_setting ?? '')."\">\n";
		}

		//transport
		if ($option_selected == 'line_1_sip_transport') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='tcp'>TCP</option>\n";
			echo "			<option value='udp'>UDP</option>\n";
			echo "			<option value='tls'>TLS</option>\n";
			echo "			<option value='dns srv'>DNS SRV</option>\n";
			echo "		</select>\n";
		}

		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','link'=>'bulk_account_settings_devices.php']);
		echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_devices']."')) { document.forms.devices.submit(); }"]);
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
	if (is_array($directory)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('device_address', $text['label-device_address'], $order_by, $order, null, null, $param);
	echo th_order_by('device_label', $text['label-device_label'], $order_by, $order, null, null, $param);
	if (preg_match('/line_(.)/',($option_selected ?? ''))) {
		echo th_order_by($option_selected, $text["label-".$option_selected.""], $order_by, $order, null, null, $param);
	}
	echo th_order_by('device_vendor', $text['label-device_vendor'], $order_by, $order, null, null, $param);
	echo th_order_by('device_template', $text['label-device_template'], $order_by, $order, null, null, $param);
	echo th_order_by('device_label', $text['label-device_profile'], $order_by, $order, null, null, $param);
	echo th_order_by('device_enabled', $text['label-device_enabled'], $order_by, $order, null, "class='center'", $param);
	echo th_order_by('device_description', $text['label-device_description'], $order_by, $order, null, null, $param);
	echo "</tr>\n";

	$device_ids = [];
	if (is_array($directory)) {
		foreach ($directory as $key => $row) {
			$list_row_url = permission_exists('device_edit') ? "/app/devices/device_edit.php?id=".urlencode($row['device_uuid']) : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['device_uuid'])."' value='".escape($row['device_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$device_ids[] = 'checkbox_'.$row['device_uuid'];
			echo "	<td class='no-wrap'><a href='".$list_row_url."'>".escape(format_device_address($row['device_address']))."</a></td>";
			echo "	<td>".escape($row['device_label'])."&nbsp;</td>\n";
			if (preg_match ('/line_/',($option_selected ?? ''))) {
				echo "	<td> ".escape($row[$option_selected])."&nbsp;</td>\n";
			}
			echo "	<td>".escape($row['device_vendor'])."&nbsp;</td>\n";
			echo "	<td>".escape($row['device_template'])."&nbsp;</td>\n";
			echo "	<td>".escape($row['device_profile_name'])."&nbsp;</td>\n";
			echo "	<td class='center'>".$text['label-'.(!empty($row['device_enabled']) ? 'true' : 'false')]."&nbsp;</td>\n";
			echo "	<td>".escape($row['device_description'])."&nbsp;</td>\n";
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

	echo "<br /><br />".(!empty($directory) && is_array($directory) ? "<br /><br />" : null);

	// check or uncheck all checkboxes
	if (!empty($device_ids)) {
		echo "<script>\n";
		echo "	function check(what) {\n";
		echo "		document.getElementById('chk_all').checked = (what == 'all') ? true : false;\n";
		foreach ($device_ids as $device_id) {
			echo "		document.getElementById('".$device_id."').checked = (what == 'all') ? true : false;\n";
		}
		echo "	}\n";
		echo "</script>\n";
	}

	if (!empty($directory)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
