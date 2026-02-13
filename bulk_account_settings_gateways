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
	if (!permission_exists('bulk_account_settings_gateways')) {
		die("access denied");
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

//get the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');

//validate the option_selected
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

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["search"] ?? '');
	if (!empty($search)) {
		$sql_mod = "and ( ";
		$sql_mod .= "lower(gateway) like :search ";
		$sql_mod .= "or lower(proxy) like :search ";
		$sql_mod .= "or lower(description) like :search ";
		if (!empty($option_selected)) {
			switch ($option_selected) {
				case 'context':
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
		$order_by = "gateway";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get total gateway count from the database
	$sql = "select count(gateway_uuid) as num_rows from v_gateways where domain_uuid = :domain_uuid ".$sql_mod." ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	if (!empty($result)) {
		$total_gateways = intval($result);
	} else {
		$total_gateways = 0;
	}
	unset($sql);

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_gateways, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_gateways, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get all the gateways from the database
	$sql = "SELECT ";
	$sql .= "gateway_uuid, ";
	if (!empty($option_selected) && $option_selected !== 'proxy' && $option_selected !== 'gateway') {
		$sql .= $option_selected . ", ";
	}
	$sql .= "gateway, ";
	$sql .= "proxy, ";
	$sql .= "enabled, ";
	$sql .= "description ";
	$sql .= "FROM v_gateways ";
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
	$gateways = $database->select($sql, $parameters, 'all');
	if ($gateways === false) {
		$gateways = [];
	}

//additional includes
	$document['title'] = $text['title-gateway_settings'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-gateways']."</b><div class='count'>".number_format($total_gateways)."</div><br><br>\n";
	echo "		".$text['description-gateway_settings']."\n";
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
	foreach ($gateway_options as $option) {
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

		echo "			<form name='gateways' method='post' action='bulk_account_settings_gateways_update.php'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//text input
		if (
			$option_selected == 'gateway' ||
			$option_selected == 'proxy' ||
			$option_selected == 'context'
			) {
			echo "		<input class='formfld' type='text' name='new_setting' maxlength='255' value=''>\n";
		}

		//enabled
		if ($option_selected === 'enabled') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','link'=>'bulk_account_settings_gateways.php']);
		echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_gateways']."')) { document.forms.gateways.submit(); }"]);
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
	if (!empty($gateways)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('gateway', $text['label-gateway'], $order_by, $order, null, null, $param);
	if (!empty($option_selected) && $option_selected != 'proxy' && $option_selected != 'gateway') {
		echo th_order_by($option_selected, $text["label-".$option_selected.""], $order_by, $order, null, null, $param);
	}
	echo th_order_by('proxy', $text['label-proxy'], $order_by, $order, null, null, $param);
	echo th_order_by('description', $text['label-description'], $order_by, $order, null, null, $param);
	echo "</tr>\n";

	$ext_ids = [];
	if (!empty($gateways)) {
		foreach($gateways as $key => $row) {
			$list_row_url = permission_exists('gateway_edit') ? "/app/gateways/gateway_edit.php?id=".urlencode($row['gateway_uuid']) : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['gateway_uuid'])."' value='".escape($row['gateway_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$ext_ids[] = 'checkbox_'.$row['gateway_uuid'];
			echo "	<td><a href='".$list_row_url."'>".$row['gateway']."</a></td>\n";
			if (!empty($option_selected) && $option_selected != 'proxy' && $option_selected != 'gateway') {
				if ($option_selected == 'enabled') {
					echo "	<td>".escape($text['label-'.(!empty($row[$option_selected]) ? 'true' : 'false')])."&nbsp;</td>\n";
				}
				else {
					echo "	<td>".escape($row[$option_selected])."&nbsp;</td>\n";
				}
			}
			echo "	<td>".escape($row['proxy'])."&nbsp;</td>\n";
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
	echo "<br /><br />".(!empty($gateways) ? "<br /><br />" : null);

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

	if (!empty($gateways)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
