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
	require_once "resources/paging.php";

//check permissions
	if (!permission_exists('bulk_account_settings_extensions')) {
		die("access denied");
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

//get the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');

//validate the option_selected
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

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_mod = "and ( ";
		$sql_mod .= "lower(extension) like :search ";
		$sql_mod .= "or lower(accountcode) like :search ";
		$sql_mod .= "or lower(call_group) like :search ";
		$sql_mod .= "or lower(description) like :search ";
		if (!empty($option_selected)) {
			switch ($option_selected) {
				case 'call_timeout':
				case 'sip_force_expires':
					$sql_mod .= "or lower(cast (".$option_selected." as text)) like :search ";
					break;
				default:
					$sql_mod .= "or lower(".$option_selected.") like :search ";
			}
		}
		$sql_mod .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if (empty($order_by)) {
		$order_by = "extension";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get total extension count from the database
	$sql = "select count(extension_uuid) as num_rows from v_extensions where domain_uuid = :domain_uuid ".$sql_mod." ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	if (!empty($result)) {
		$total_extensions = intval($result);
	} else {
		$total_extensions = 0;
	}
	unset($sql);

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_extensions, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_extensions, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get all the extensions from the database
	$sql = "SELECT ";
	$sql .= "description, ";
	$sql .= "extension, ";
	$sql .= "extension_uuid, ";
	if (!empty($option_selected) && $option_selected !== 'context' && $option_selected !== 'call_group' && $option_selected !== 'accountcode') {
		$sql .= $option_selected . ", ";
	}
	$sql .= "accountcode, ";
	$sql .= "call_group, ";
	$sql .= "user_context AS context ";
	$sql .= "FROM v_extensions ";
	$sql .= "WHERE domain_uuid = :domain_uuid ";
	//add search mod from above
	if (!empty($sql_mod)) {
		$sql .= $sql_mod;
	}
	if ($rows_per_page > 0) {
		$sql .= "ORDER BY $order_by $order ";
		$sql .= "limit $rows_per_page offset $offset ";
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$extensions = $database->select($sql, $parameters, 'all');
	if ($extensions === false) {
		$extensions = [];
	}

//additional includes
	$document['title'] = $text['title-extension_settings'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-extensions']."</b><div class='count'>".number_format($total_extensions)."</div><br><br>\n";
	echo "		".$text['description-extension_settings']."\n";
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
	foreach ($extension_options as $option) {
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

		echo "			<form name='extensions' method='post' action='bulk_account_settings_extensions_update.php'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//text input
		if (
			$option_selected == 'accountcode' ||
			$option_selected == 'call_group' ||
			$option_selected == 'call_timeout' ||
			$option_selected == 'context' ||
			$option_selected == 'emergency_caller_id_name' ||
			$option_selected == 'emergency_caller_id_number' ||
			$option_selected == 'limit_max' ||
			$option_selected == 'outbound_caller_id_name' ||
			$option_selected == 'outbound_caller_id_number' ||
			$option_selected == 'toll_allow' ||
			$option_selected == 'sip_force_expires' ||
			$option_selected == 'mwi_account'
			) {
			echo "		<input class='formfld' type='text' name='new_setting' maxlength='255' value=''>\n";
		}

		//enabled, directory visible
		if ($option_selected === 'enabled' || $option_selected === 'directory_visible') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//user record
        if ($option_selected == 'user_record') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''>".$text['label-user_record_none']."</option>\n";
			echo "			<option value='all'>".$text['label-all']."</option>\n";
			echo "			<option value='local'>".$text['label-local']."</option>\n";
			echo "			<option value='inbound'>".$text['label-inbound']."</option>\n";
			echo "			<option value='outbound'>".$text['label-outbound']."</option>\n";
			echo "			<option value='disabled'>".$text['label-disabled']."</option>\n";
			echo "		</select>\n";
        }

		//sip force contact
		if ($option_selected == 'sip_force_contact') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			echo "			<option value='NDLB-connectile-dysfunction'>".$text['label-rewrite_contact_ip_and_port']."</option>\n";
			echo "			<option value='NDLB-connectile-dysfunction-2.0'>".$text['label-rewrite_contact_ip_and_port_2']."</option>\n";
			echo "			<option value='NDLB-tls-connectile-dysfunction'>".$text['label-rewrite_tls_contact_port']."</option>\n";
			echo "		</select>\n";
		}

		//sip bypass media
		if ($option_selected == 'sip_bypass_media') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			echo "			<option value='bypass-media'>".$text['option-bypass_media']."</option>\n";
			echo "			<option value='bypass-media-after-bridge'>".$text['option-bypass_media_after_bridge']."</option>\n";
			echo "			<option value='proxy-media'>".$text['option-proxy_media']."</option>\n";
			echo "		</select>\n";
		}

		//hold music
		if ($option_selected == 'hold_music') {
			if (is_dir($_SERVER["DOCUMENT_ROOT"].PROJECT_PATH.'/app/music_on_hold')) {
				require_once "app/music_on_hold/resources/classes/switch_music_on_hold.php";
				$options = '';
				$moh = new switch_music_on_hold;
				echo $moh->select('new_setting', ($hold_music ?? ''), $options);
			}
		}

		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','link'=>'bulk_account_settings_extensions.php']);
		echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_extensions']."')) { document.forms.extensions.submit(); }"]);
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
	if (!empty($extensions)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('extension', $text['label-extension'], $order_by, $order, null, null, $param);
	if (!empty($option_selected) && $option_selected != 'call_group' && $option_selected != 'accountcode') {
		echo th_order_by($option_selected, $text["label-".$option_selected.""], $order_by, $order, null, null, $param);
	}
	echo th_order_by('accountcode', $text['label-accountcode'], $order_by, $order, null, null, $param);
	echo th_order_by('call_group', $text['label-call_group'], $order_by, $order, null, null, $param);
	echo th_order_by('description', $text['label-description'], $order_by, $order, null, null, $param);
	echo "</tr>\n";

	$ext_ids = [];
	if (!empty($extensions)) {
		foreach($extensions as $key => $row) {
			$list_row_url = permission_exists('extension_edit') ? "/app/extensions/extension_edit.php?id=".urlencode($row['extension_uuid']) : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['extension_uuid'])."' value='".escape($row['extension_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$ext_ids[] = 'checkbox_'.$row['extension_uuid'];
			echo "	<td><a href='".$list_row_url."'>".$row['extension']."</a></td>\n";
			if (!empty($option_selected) && $option_selected != 'call_group' && $option_selected != 'accountcode') {
				if ($option_selected == 'enabled' || $option_selected == 'directory_visible') {
					echo "	<td>".escape($text['label-'.(!empty($row[$option_selected]) ? 'true' : 'false')])."&nbsp;</td>\n";
				}
				else if ($option_selected == 'user_record') {
					echo "	<td>".escape(ucwords($row[$option_selected] ?? ''))."&nbsp;</td>\n";
				}
				else {
					echo "	<td>".escape($row[$option_selected])."&nbsp;</td>\n";
				}
			}
			echo "	<td>".escape($row['accountcode'])."&nbsp;</td>\n";
			echo "	<td>".escape($row['call_group'])."&nbsp;</td>\n";
			echo "	<td>".escape($row['description'])."&nbsp;</td>\n";
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
	echo "<br /><br />".((!empty($extensions)) ? "<br /><br />" : null);

	// check or uncheck all checkboxes
	if (!empty($ext_ids)) {
		echo "<script>\n";
		echo "	function check(what) {\n";
		echo "		document.getElementById('chk_all').checked = (what == 'all') ? true : false;\n";
		foreach ($ext_ids as $ext_id) {
			echo "		document.getElementById('".$ext_id."').checked = (what == 'all') ? true : false;\n";
		}
		echo "	}\n";
		echo "</script>\n";
	}

	if (!empty($extensions)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
