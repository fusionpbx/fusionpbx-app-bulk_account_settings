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
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	KonradSC <konrd@yahoo.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";
	require_once "resources/paging.php";

//check permissions
	if (permission_exists('bulk_account_settings_extensions')) {
		//access granted
	}
	else {
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
	$extension_options[] = 'sip_force_expires';
	$extension_options[] = 'sip_bypass_media';
	$extension_options[] = 'mwi_account';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//get the http values and set them as variables
	$order_by = check_str($_GET["order_by"]);
	$order = check_str($_GET["order"]);
	$option_selected = check_str($_GET["option_selected"]);

//validate the option_selected
	if (!empty($option_selected) && !in_array($option_selected, $extension_options)) {
		die('invalid option');
	}

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = check_str($_GET["search"]);
	if (!empty($search)) {
		$sql_mod = "and ( ";
		$sql_mod .= "lower(extension) like :search ";
		$sql_mod .= "or lower(accountcode) like :search ";
		$sql_mod .= "or lower(call_group) like :search ";
		$sql_mod .= "or lower(description) like :search ";
		switch ($option_selected) {
			case 'call_timeout':
			case 'sip_force_expires':
				$sql_mod .= "or lower(cast (".$option_selected." as text)) like :search ";
				break;
			default:
				$sql_mod .= "or lower(".$option_selected.") like :search ";
		}
		$sql_mod .= ") ";
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if (strlen($order_by) < 1) {
		$order_by = "extension";
		$order = "ASC";
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
	$url_params = "&search=".$search."&option_selected=".$option_selected;
	if ($rows_per_page > 0) {
		if (!isset($_GET['page'])) { $_GET['page'] = 0; }
		$_GET['page'] = check_str($_GET['page']);
		list($paging_controls_mini, $rows_per_page, $var_3) = paging($total_extensions, $url_params, $rows_per_page, true); //top
		list($paging_controls, $rows_per_page, $var_3) = paging($total_extensions, $url_params, $rows_per_page); //bottom
		$offset = $rows_per_page * intval($_GET['page']);
	}

//get all the extensions from the database
	$parameters = [];
	$sql = "SELECT ";
	$sql .= "description, ";
	$sql .= "extension, ";
	$sql .= "extension_uuid, ";
	if (!empty($option_selected) && $option_selected !== 'call_group' && $option_selected !== 'accountcode') {
		$sql .= $option_selected . ", ";
	}
	$sql .= "accountcode, ";
	$sql .= "call_group ";
	$sql .= "FROM v_extensions ";
	$sql .= "WHERE domain_uuid = :domain_uuid ";
	//add search mod from above
	if (!empty($sql_mod)) {
		$sql .= $sql_mod;
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if ($rows_per_page > 0) {
		$sql .= "ORDER BY $order_by $order ";
		$sql .= "limit $rows_per_page offset $offset ";
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$directory = $database->select($sql, $parameters, 'all');
	if ($directory === false) {
		$directory = [];
	}

//additional includes
	require_once "resources/header.php";
	$document['title'] = $text['title-extensions_settings'];

//set the alternating styles
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

//show the content
	echo "<table width=\"100%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\">\n";
	echo "  <tr>\n";
	echo "	<td align='left' width='100%'>\n";
	echo "		<div class='action_bar' id='action_bar'>\n";
	echo "			<div class='heading'><b>".$text['header-extensions']."</b><div class='count'>".$total_extensions."</div></div><br>\n";
	echo "		</div>\n";

//options list
		echo "<form name='frm' method='get' id=option_selected>\n";
		echo "    <select class='formfld' name='option_selected'  onchange=\"this.form.submit();\">\n";
		echo "    <option value=''>".$text['label-extension_null']."</option>\n";
		foreach ($extension_options as $option) {
			if ($option_selected === $option) {
				$selected = " selected='selected'";
			} else {
				$selected = "";
			}
			echo "    <option value='$option'$selected>".$text['label-'.$option]."</option>\n";
		}
		echo "    </select>\n";
		echo "    </form>\n";
		echo "<br />\n";
		echo $text['description-extension_settings_description']."\n";
		echo "</td>\n";



	echo "		<td align='right' width='100%' style='vertical-align: top;'>";
	echo "		<form method='get' action=''>\n";
	echo "			<td style='vertical-align: top; text-align: right; white-space: nowrap;'>\n";
	echo "				<input type='button' class='btn' style='width: 40px' alt='".$text['button-back']."' onclick=\"window.location='bulk_account_settings.php'\" value='".$text['button-back']."'>\n";
	echo "				<input type='text' class='txt' style='width: 150px' name='search' id='search' value='".escape($search)."'>";
	echo "				<input type='hidden' class='txt' style='width: 150px' name='option_selected' id='option_selected' value='".escape($option_selected)."'>";
	echo "				<input type='submit' class='btn' name='submit' value='".$text['button-search']."'>";
	if ($paging_controls_mini != '') {
		echo 			"<span style='margin-left: 15px;'>".$paging_controls_mini."</span>\n";
	}
	echo "			</td>\n";
	echo "		</form>\n";
	echo "  </tr>\n";


	echo "	<tr>\n";
	echo "		<td colspan='2'>\n";
	echo "			".$text['description-extensions_settings']."\n";
	echo "		</td>\n";
	echo "	</tr>\n";
	echo "</table>\n";
	echo "<br />";

	if (strlen($option_selected) > 0) {
		echo "<form name='extensions' method='post' action='bulk_account_settings_extensions_update.php'>\n";
		echo "<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";
		echo "<table width='auto' border='0' cellpadding='0' cellspacing='0'>\n";
		echo "<tr>\n";
		//options with a free form input
		if ($option_selected == 'accountcode'
				|| $option_selected == 'call_group'
				|| $option_selected == 'call_timeout'
				|| $option_selected == 'emergency_caller_id_name'
				|| $option_selected == 'emergency_caller_id_number'
				|| $option_selected == 'limit_max'
				|| $option_selected == 'outbound_caller_id_name'
				|| $option_selected == 'outbound_caller_id_number'
				|| $option_selected == 'toll_allow'
				|| $option_selected == 'sip_force_expires'
				|| $option_selected == 'mwi_account') {
			echo "<td class='vtable' align='left'>\n";
			echo "    <input class='formfld' type='text' name='new_setting' maxlength='255' value=\"$new_setting\">\n";
			echo "<br />\n";
			echo $text["description-".escape($option_selected).""]."\n";
			echo "</td>\n";
		}
		//options with True/False
		if ($option_selected === 'enabled' || $option_selected === 'directory_visible') {
			echo "<td class='vtable' align='left'>\n";
			echo "    <select class='formfld' name='new_setting'>\n";
			echo "    <option value='true'>".$text['label-true']."</option>\n";
			echo "    <option value='false'>".$text['label-false']."</option>\n";
			echo "    </select>\n";
			echo "    <br />\n";
			echo $text["description-".$option_selected.""]."\n";
			echo "</td>\n";
		}
		//option is User Record
        if ($option_selected == 'user_record') {
			echo "<td class='vtable' align='left'>\n";
			echo "    <select class='formfld' name='new_setting'>\n";
			echo "		<option value=''>".$text['label-user_record_none']."</option>\n";
			echo "		<option value='all'>".$text['label-all']."</option>\n";
			echo "		<option value=inbound'>".$text['label-inbound']."</option>\n";
			echo "		<option value=outbound'>".$text['label-outbound']."</option>\n";
			echo "		<option value=local'>".$text['label-local']."</option>\n";
			echo "		<option value=disabled'>".$text['label-disabled']."</option>\n";
			echo "    </select>\n";
			echo "    <br />\n";
			echo $text["description-".$option_selected.""]."\n";
			echo "</td>\n";
        }
		//option is SIP Bypass Media
		if ($option_selected == 'sip_bypass_media') {
			echo "<td class='vtable' align='left'>\n";
			echo "    <select class='formfld' name='new_setting'>\n";
			echo "    <option value=''></option>\n";
			echo "    <option value='bypass-media'>".$text['option-bypass_media']."</option>\n";
			echo "    <option value='bypass-media-after-bridge'>".$text['option-bypass_media_after_bridge']."</option>\n";
			echo "    <option value='proxy-media'>".$text['option-proxy_media']."</option>\n";
			echo "    </select>\n";
			echo "    <br />\n";
			echo $text["description-".$option_selected.""]."\n";
			echo "</td>\n";
		}
		//option is hold_music
		if ($option_selected == 'hold_music') {
			echo "<td class='vtable' align='left'>\n";
			if (is_dir($_SERVER["DOCUMENT_ROOT"].PROJECT_PATH.'/app/music_on_hold')) {
				require_once "app/music_on_hold/resources/classes/switch_music_on_hold.php";
				$options = '';
				$moh = new switch_music_on_hold;
				echo $moh->select('new_setting', $hold_music, $options);
			}
			$new_setting = $hold_music;
			echo "    <br />\n";
			echo $text["description-".escape($option_selected).""]."\n";
			echo "</td>\n";
		}
		echo "<td align='left'>\n";
		echo "<input type='button' class='btn' alt='".$text['button-submit']."' onclick=\"if (confirm('".$text['confirm-update']."')) { document.forms.extensions.submit(); }\" value='".$text['button-submit']."'>\n";
		echo "</td>\n";
		echo "</tr>\n";
		echo "</table>";
		echo "<br />";
	}

	echo "<div class='card'>\n";
	echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	if (!empty($directory)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('extension', $text['label-extension'], $order_by,$order,'','',"option_selected=".$option_selected."&search=".$search."");
	if (($option_selected == "") or ($option_selected == 'call_group') or ($option_selected == 'accountcode')) {
		} else {
			echo th_order_by($option_selected, $text["label-".$option_selected.""], $order_by,$order,'','',"option_selected=".$option_selected."&search=".$search."");
		}
	echo th_order_by('accountcode', $text['label-accountcode'], $order_by, $order,'','',"option_selected=".$option_selected."&search=".$search."");
	echo th_order_by('call_group', $text['label-call_group'], $order_by, $order,'','',"option_selected=".$option_selected."&search=".$search."");
	echo th_order_by('description', $text['label-description'], $order_by, $order,'','',"option_selected=".$option_selected."&search=".$search."");
	echo "</tr>\n";


	$ext_ids = [];
	if (!empty($directory)) {

		foreach($directory as $key => $row) {
			$tr_link = (permission_exists('extension_edit')) ? " href='/app/extensions/extension_edit.php?id=".$row['extension_uuid']."'" : null;
			echo "<tr ".$tr_link.">\n";

			echo "	<td valign='top' class='".$row_style[$c]." tr_link_void' style='text-align: center; vertical-align: middle; padding: 0px;'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['extension_uuid'])."' value='".escape($row['extension_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$ext_ids[] = 'checkbox_'.$row['extension_uuid'];

			echo "	<td valign='top' class='".$row_style[$c]."'> ".$row['extension']."&nbsp;</td>\n";
			if (($option_selected == "") or ($option_selected == 'call_group') or ($option_selected == 'accountcode')) {
			} else {
				echo "	<td valign='top' class='".$row_style[$c]."'> ".escape($row[$option_selected])."&nbsp;</td>\n";
			}
			echo "	<td valign='top' class='".$row_style[$c]."'> ".escape($row['accountcode'])."&nbsp;</td>\n";
			echo "	<td valign='top' class='".$row_style[$c]."'> ".escape($row['call_group'])."&nbsp;</td>\n";
			echo "	<td valign='top' class='".$row_style[$c]."'> ".escape($row['description'])."</td>\n";
			echo "</tr>\n";
			$c = ($c) ? 0 : 1;
		}

	}

	echo "</table>\n";
	echo "</div>\n";
	echo "</form>\n";

	if (!empty($paging_controls)) {
		echo "<br />\n";
		echo $paging_controls."\n";
	}
	echo "<br /><br />".((!empty($directory)) ? "<br /><br />" : null);

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

	if (!empty($directory)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
