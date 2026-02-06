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

//get the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');
	$option_action = $_GET["option_action"] ?? '';

//validate the option_selected
	if (!empty($option_selected) && !in_array($option_selected, $voicemail_options, true)) {
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

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["search"] ?? '');
	if (strlen($search) > 0) {
		$sql_mod = "and ( ";
		$sql_mod .= "CAST(voicemail_id AS TEXT) LIKE :search ";
		$sql_mod .= "or voicemail_description ILIKE :search ";
		$sql_mod .= ") ";
		$parameters['search'] = '%'.$search.'%';
	}
	if (strlen($order_by) < 1) {
		$order_by = "voicemail_id";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get the settings to use for this domain and user
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//get total voicemail count from the database
	$sql = "select count(voicemail_uuid) as num_rows from v_voicemails where domain_uuid = :domain_uuid ".$sql_mod." ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	if (!empty($result)) {
		$total_voicemails = intval($result);
	} else {
		$total_voicemails = 0;
	}

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_voicemails, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_voicemails, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//voicemail options
	if (preg_match('/voicemail_option_(.)/', $option_selected)) {
		preg_match('/voicemail_option_(.)/', $option_selected, $matches);
		$option_number = $matches[1];
	}

//get all the voicemails from the database
	$parameters = [];
	$sql = "SELECT ";
	$sql .= "v.voicemail_description, ";
	$sql .= "v.voicemail_id, ";
	$sql .= "v.voicemail_uuid, ";
	$sql .= "v.voicemail_file, ";
	$sql .= "v.voicemail_enabled, ";
	$sql .= "v.voicemail_local_after_email, ";
	$sql .= "v.voicemail_transcription_enabled ";
	$sql .= "FROM v_voicemails as v ";
	$sql .= "WHERE v.domain_uuid = :domain_uuid ";
	if (!empty($search)) {
		$sql .= $sql_mod; //add search mod from above
		$parameters['search'] = '%'.$search.'%';
	}
	if ($rows_per_page > 0) {
		$sql .= "ORDER BY ".$order_by." ".$order." ";
		$sql .= "limit $rows_per_page offset $offset ";
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'all');
	$voicemails = !empty($result) ? $result : [];
	unset($parameters);

//lookup the options
	foreach ($voicemails as $key => $row) {
		$parameters = [];
		$sql = "SELECT voicemail_option_param, voicemail_option_order ";
		$sql .= "FROM v_voicemail_options ";
		$sql .= "WHERE domain_uuid = :domain_uuid ";
		if (!empty($row['voicemail_uuid']) && is_uuid($row['voicemail_uuid'])) {
			$sql .= "AND voicemail_uuid = :voicemail_uuid ";
			$parameters['voicemail_uuid'] = $row['voicemail_uuid'];
		}
		if (isset($option_number)) {
			$sql .= "AND voicemail_option_digits = :voicemail_option_digits ";
			$parameters['voicemail_option_digits'] = $option_number;
		}
		$parameters['domain_uuid'] = $domain_uuid;
		$result = $database->select($sql, $parameters, 'all');
		if (!empty($result) && is_array($result)) {
			$voicemails[$key]['option_db_value'] = $result;
		}
		unset($parameters);
	}

//additional includes
	require_once "resources/header.php";
	$document['title'] = $text['title-voicemail_settings'];

//initialize the destinations object
	$destination = new destinations;

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-voicemails']."</b><div class='count'>".number_format($total_voicemails)."</div><br><br>\n";
	echo "		".$text['description-voicemail_settings']."\n";
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
	foreach ($voicemail_options as $option) {
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

		echo "			<form name='voicemails' method='post' action='bulk_account_settings_voicemails_update.php'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//password
		if ($option_selected == 'voicemail_password') {
			echo "		<input class='formfld' type='password' name='new_setting' id='password' autocomplete='off' onmouseover=\"this.type='text';\" onfocus=\"this.type='text';\" onmouseout=\"if (!$(this).is(':focus')) { this.type='password'; }\" onblur=\"this.type='password';\" autocomplete='off' maxlength='50'>\n";
		}

		//voicemail_enabled, voicemail_local_after_email, voicemail_transcription_enabled
		if ($option_selected == 'voicemail_enabled' || $option_selected == 'voicemail_local_after_email' || $option_selected == 'voicemail_transcription_enabled') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//voicemail_file
		if ($option_selected == 'voicemail_file') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='listen'>".$text['option-voicemail_file_listen']."</option>\n";
			echo "			<option value='link'>".$text['option-voicemail_file_link']."</option>\n";
			echo "			<option value='attach'>".$text['option-voicemail_file_attach']."</option>\n";
			echo "		</select>\n";
		}

		//voicemail_option
		if (preg_match('/option_/', $option_selected)) {
			echo "		<select class='formfld' name='option_action' onchange=\"$('.add_option').slideToggle();\">\n";
			echo "			<option value='add'>".$text['label-add']."</option>\n";
			echo "			<option value='remove'>".$text['label-remove']."</option>\n";
			echo "		</select><br>\n";

			echo "<div class='add_option'>\n";
			echo "	<br>\n";
			echo $destination->select('ivr', 'voicemail_option_param', '', $text['label-destination'])."<br>\n";

			echo "	<select class='formfld' name='voicemail_option_order' style='width: 70px'>\n";
			echo "		<option value='' selected='selected' disabled='disabled'>".$text['label-order']."</option>\n";
			if (strlen(htmlspecialchars($voicemail_option_order))> 0) {
				echo "	<option selected='yes' value='".htmlspecialchars($voicemail_option_order)."'>".htmlspecialchars($voicemail_option_order)."</option>\n";
			}
			$i = 0;
			for ($i = 0; $i <= 999; $i++) {
				//set to 3 digit display with character '0' prefixed
				$padded_i = str_pad($i, 3, "0", STR_PAD_LEFT);
				echo "	<option value='".$padded_i."'>".$padded_i."</option>\n";
			}
			echo "	</select><br>\n";

			echo "	<input class='formfld' style='width: 100px' type='text' name='voicemail_option_description' maxlength='255' placeholder=\"".$text['label-description']."\">\n";

			echo "</div>\n";
		}


		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		// echo "<input type='button' class='btn' alt='".$text['button-submit']."' onclick=\"if (confirm('".$text['confirm-update']."')) { document.forms.voicemails.submit(); }\" value='".$text['button-submit']."'>\n";
		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','style'=>($option_selected == 'group' ? "margin-right: 15px;" : null),'link'=>'bulk_account_settings_voicemails.php']);
		echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_voicemails']."')) { document.forms.voicemails.submit(); }"]);
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
	if (!empty($voicemails)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('voicemail_id', $text['label-voicemail_id'], $order_by, $order, null, null, $param);
	if (preg_match('/option_/', $option_selected)) {
		echo th_order_by('voicemail_id', $text["label-".$option_selected], $order_by, $order, null, null, $param);
	}
	else {
		echo th_order_by('voicemail_file', $text['label-voicemail_file'], $order_by, $order, null, null, $param);
		echo th_order_by('voicemail_local_after_email', $text['label-voicemail_local_after_email'], $order_by, $order, null, "class='center'", $param);
		if ($settings->get('voicemail', 'transcribe_enabled') == true) {
			echo th_order_by('voicemail_transcription_enabled', $text['label-voicemail_transcription_enabled'], $order_by, $order, null, null, $param);
		}
	}
	echo th_order_by('voicemail_enabled', $text['label-voicemail_enabled'], $order_by, $order, null, "class='center'", $param);
	echo th_order_by('voicemail_description', $text['label-voicemail_description'], $order_by, $order, null, null, $param);
	echo "</tr>\n";

	$ext_ids = [];
	if (!empty($voicemails)) {
		foreach ($voicemails as $row) {
			$list_row_url = permission_exists('voicemail_edit') ? "/app/voicemails/voicemail_edit.php?id=".escape($row['voicemail_uuid']) : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['voicemail_uuid'])."' value='".escape($row['voicemail_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$ext_ids[] = 'checkbox_'.$row['voicemail_uuid'];
			echo "	<td><a href='".$list_row_url."'>".escape($row['voicemail_id'])."</a></td>\n";
			if (preg_match('/option_/', $option_selected)) {
				echo "	<td>\n";
				if (!empty($row['option_db_value']) && is_array($row['option_db_value'])) {
					foreach ($row['option_db_value'] as $v => $value) {
						if ($v != 0) {
							echo ", ";
						}
						echo $value['voicemail_option_param']." (".$value['voicemail_option_order'].")";
					}
				}
				echo "&nbsp;</td>\n";
			}

			else {
				echo "	<td>\n";
				switch ($row['voicemail_file']) {
					case 'listen': echo $text['option-voicemail_file_listen']; break;
					case 'link': echo $text['option-voicemail_file_link']; break;
					case 'attach': echo $text['option-voicemail_file_attach']; break;
				}
				echo "&nbsp;</td>\n";
				echo "	<td class='center'>".$text['label-'.(!empty($row['voicemail_local_after_email']) ? 'true' : 'false')]."&nbsp;</td>\n";
				if ($settings->get('voicemail','transcribe_enabled') == true) {
					echo "	<td> ".escape($row['voicemail_transcription_enabled'])."&nbsp;</td>\n";
				}
			}
			echo "	<td class='center'>".$text['label-'.(!empty($row['voicemail_enabled']) ? 'true' : 'false')]."&nbsp;</td>\n";
			echo "	<td>".escape($row['voicemail_description'])."</td>\n";
			echo "</tr>\n";
		}
	}
	echo "</table>\n";
	echo "</div>\n";
	echo "</form>\n";

	if (!empty($paging_controls)) {
		echo "<br />";
		echo $paging_controls."\n";
	}
	echo "<br /><br />".(!empty($voicemails) ? "<br /><br />" : null);

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

	if (!empty($voicemails)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
