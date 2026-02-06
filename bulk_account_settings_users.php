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
	if (!permission_exists('bulk_account_settings_users')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set defaults
	$user_ids = [];
	$user_options = [];
	$user_options[] = 'user_enabled';
	$user_options[] = 'group';
	$user_options[] = 'password';
	$user_options[] = 'user_status';
	$user_options[] = 'time_zone';

//use connected database
	$domain_uuid = $_SESSION['domain_uuid'] ?? '';
	$user_uuid = $_SESSION['user_uuid'] ?? '';
	$database = database::new(['config' => config::load(), 'domain_uuid' => $domain_uuid]);
	$settings = new settings(['database' => $database, 'domain_uuid' => $domain_uuid, 'user_uuid' => $user_uuid]);

//get the http values and set them as variables
	$order_by = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order_by"] ?? '');
	$order = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["order"] ?? '');
	$option_selected = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["option_selected"] ?? '');

//handle search term
	$parameters = [];
	$sql_mod = '';
	$search = preg_replace('#[^a-zA-Z0-9_]#', '', $_GET["search"] ?? '');
	if (strlen($search) > 0) {
		$sql_mod = 'and ( ';
		$sql_mod .= 'lower(username) like :search ';
		$sql_mod .= 'or lower(user_status) like :search ';
		$sql_mod .= ') ';
		$parameters['search'] = '%'.strtolower($search).'%';
	}
	if (strlen($order_by) < 1) {
		$order_by = "username";
	}

//ensure only two possible values for $order
	if ($order != 'DESC') {
		$order = 'ASC';
	}

//get total extension count from the database
	$sql = "select count(user_uuid) as num_rows from v_users where domain_uuid = :domain_uuid ".$sql_mod." ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'column');
	$total_users = !empty($result) ? intval($result) : 0;

//prepare to page the results
	$rows_per_page = intval($settings->get('domain', 'paging', 50));
	$param = (!empty($search) ? "&search=".$search : '').(!empty($option_selected) ? "&option_selected=".$option_selected : '');
	$page = intval(preg_replace('#[^0-9]#', '', $_GET['page'] ?? 0));
	list($paging_controls, $rows_per_page) = paging($total_users, $param, $rows_per_page);
	list($paging_controls_mini, $rows_per_page) = paging($total_users, $param, $rows_per_page, true);
	$offset = $rows_per_page * $page;

//get all the users from the database
	$parameters = [];
	$sql  = "select";
	$sql .= "	username, ";
	$sql .= "	user_uuid, ";
	$sql .= "	user_status, ";
	$sql .= "	user_enabled ";
	$sql .= "from ";
	$sql .= "	v_users ";
	$sql .= "where ";
	$sql .= "	domain_uuid = :domain_uuid ";
	if (!empty($sql_mod)) {
		$sql .= $sql_mod; //add search mod from above
		$parameters['search'] = '%'.$search.'%';
	}
	if ($rows_per_page > 0) {
		$sql .= "order by ".$order_by." ".$order." ";
		$sql .= "limit ".$rows_per_page." offset ".$offset;
	}
	$parameters['domain_uuid'] = $domain_uuid;
	$users = $database->select($sql, $parameters, 'all');
	if (empty($users)) {
		$users = [];
	}

//get all the users' groups from the database
	$parameters = [];
	$sql = "select ";
	$sql .= "	ug.*, g.domain_uuid as group_domain_uuid ";
	$sql .= "from ";
	$sql .= "	v_user_groups as ug, ";
	$sql .= "	v_groups as g ";
	$sql .= "where ";
	$sql .= "	ug.group_uuid = g.group_uuid ";
	$sql .= "	and ug.domain_uuid = :domain_uuid ";
	$sql .= "order by ";
	$sql .= "	g.domain_uuid desc, ";
	$sql .= "	g.group_name asc ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'all');
	if (!empty($result)) {
		foreach($result as $row) {
			$user_groups[$row['user_uuid']][] = $row['group_name'].(($row['group_domain_uuid'] != '') ? "@".$_SESSION['domains'][$row['group_domain_uuid']]['domain_name'] : '');
		}
	}
	else {
		$user_groups = [];
	}
	unset($result);

//get all the users' timezones from the database
	$parameters = [];
	$sql = "select ";
	$sql .= "	us.* ";
	$sql .= "from ";
	$sql .= "	v_user_settings as us ";
	$sql .= "where ";
	$sql .= "	us.user_setting_subcategory = 'time_zone' ";
	$sql .= "	and us.domain_uuid = :domain_uuid ";
	$parameters['domain_uuid'] = $domain_uuid;
	$result = $database->select($sql, $parameters, 'all');
	if (!empty($result)) {
		foreach($result as $row) {
			$user_time_zone[$row['user_uuid']][] = $row['user_setting_value'];
		}
	}
	unset($result);


//additional includes
	$document['title'] = $text['title-user_settings'];
	require_once "resources/header.php";

//javascript for password
	echo "<script>\n";
	echo "	function compare_passwords() {\n";
	echo "		if (document.getElementById('password') === document.activeElement || document.getElementById('password_confirm') === document.activeElement) {\n";
	echo "			if ($('#password').val() != '' || $('#password_confirm').val() != '') {\n";
	echo "				if ($('#password').val() != $('#password_confirm').val()) {\n";
	echo "					$('#password').removeClass('formfld_highlight_good');\n";
	echo "					$('#password_confirm').removeClass('formfld_highlight_good');\n";
	echo "					$('#password').addClass('formfld_highlight_bad');\n";
	echo "					$('#password_confirm').addClass('formfld_highlight_bad');\n";
	echo "				}\n";
	echo "				else {\n";
	echo "					$('#password').removeClass('formfld_highlight_bad');\n";
	echo "					$('#password_confirm').removeClass('formfld_highlight_bad');\n";
	echo "					$('#password').addClass('formfld_highlight_good');\n";
	echo "					$('#password_confirm').addClass('formfld_highlight_good');\n";
	echo "				}\n";
	echo "			}\n";
	echo "		}\n";
	echo "		else {\n";
	echo "			$('#password').removeClass('formfld_highlight_bad');\n";
	echo "			$('#password_confirm').removeClass('formfld_highlight_bad');\n";
	echo "			$('#password').removeClass('formfld_highlight_good');\n";
	echo "			$('#password_confirm').removeClass('formfld_highlight_good');\n";
	echo "		}\n";
	echo "	}\n";

	$req['length'] = $settings->get('user', 'password_length', 20);
	$req['number'] = $settings->get('user', 'password_number', true);
	$req['lowercase'] = $settings->get('user', 'password_lowercase', true);
	$req['uppercase'] = $settings->get('user', 'password_uppercase', true);
	$req['special'] = $settings->get('user', 'password_special', true);

	echo "	function check_password_strength(pwd) {\n";
	echo "		if ($('#password').val() != '' || $('#password_confirm').val() != '') {\n";
	echo "			var msg_errors = [];\n";
	if (is_numeric($req['length']) && $req['length'] != 0) {
		echo "		var re = /.{".$req['length'].",}/;\n"; //length
		echo "		if (!re.test(pwd)) { msg_errors.push('".$req['length']."+ ".$text['label-characters']."'); }\n";
	}
	if ($req['number']) {
		echo "		var re = /(?=.*[\d])/;\n";  //number
		echo "		if (!re.test(pwd)) { msg_errors.push('1+ ".$text['label-numbers']."'); }\n";
	}
	if ($req['lowercase']) {
		echo "		var re = /(?=.*[a-z])/;\n";  //lowercase
		echo "		if (!re.test(pwd)) { msg_errors.push('1+ ".$text['label-lowercase_letters']."'); }\n";
	}
	if ($req['uppercase']) {
		echo "		var re = /(?=.*[A-Z])/;\n";  //uppercase
		echo "		if (!re.test(pwd)) { msg_errors.push('1+ ".$text['label-uppercase_letters']."'); }\n";
	}
	if ($req['special']) {
		echo "		var re = /(?=.*[\W])/;\n";  //special
		echo "		if (!re.test(pwd)) { msg_errors.push('1+ ".$text['label-special_characters']."'); }\n";
	}
	echo "			if (msg_errors.length > 0) {\n";
	echo "				var msg = '".$text['message-password_requirements'].": ' + msg_errors.join(', ');\n";
	echo "				display_message(msg, 'negative', '6000');\n";
	echo "				return false;\n";
	echo "			}\n";
	echo "			else {\n";
	echo "				return true;\n";
	echo "			}\n";
	echo "		}\n";
	echo "		else {\n";
	echo "			return true;\n";
	echo "		}\n";
	echo "	}\n";

	echo "	function show_strenth_meter() {\n";
	echo "		$('#pwstrength_progress').slideDown();\n";
	echo "	}\n";
	echo "</script>\n";


//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>\n";
	echo "		<b>".$text['header-users']."</b><div class='count'>".number_format($total_users)."</div><br><br>\n";
	echo "		".$text['description-user_settings']."\n";
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
	foreach ($user_options as $option) {
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

		echo "			<form name='users' method='post' action='bulk_account_settings_users_update.php'>\n";
		echo "			<input class='formfld' type='hidden' name='option_selected' maxlength='255' value=\"".escape($option_selected)."\">\n";

		//password
		if ($option_selected == 'password') {
			echo "		<input class='formfld' type='password' name='new_setting' maxlength='255' value=''>\n";
		}

		//enabled
		if ($option_selected == 'user_enabled') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value='true'>".$text['label-true']."</option>\n";
			echo "			<option value='false'>".$text['label-false']."</option>\n";
			echo "		</select>\n";
		}

		//user status
		if ($option_selected == 'user_status') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			echo "			<option value='Available'>".$text['option-available']."</option>\n";
			echo "			<option value='Available (On Demand)'>".$text['option-available_on_demand']."</option>\n";
			echo "			<option value='Logged Out'>".$text['option-logged_out']."</option>\n";
			echo "			<option value='On Break'>".$text['option-on_break']."</option>\n";
			echo "			<option value='Do Not Disturb'>".$text['option-do_not_disturb']."</option>\n";
			echo "		</select>\n";
		}

		//user time zone
		if ($option_selected == 'time_zone') {
			echo "		<select class='formfld' name='new_setting'>\n";
			echo "			<option value=''></option>\n";
			//$list = DateTimeZone::listAbbreviations();
			$time_zone_identifiers = DateTimeZone::listIdentifiers();
			$previous_category = '';
			$x = 0;
			foreach ($time_zone_identifiers as $key => $row) {
				$time_zone = explode("/", $row);
				$category = $time_zone[0];
				if ($category != $previous_category) {
					if ($x > 0) {
						echo "		</optgroup>\n";
					}
					echo "		<optgroup label='".escape($category)."'>\n";
				}
				echo "			<option value='".escape($row)."'>".escape($row)."</option>\n";

				$previous_category = $category;
				$x++;
			}
			echo "		</select>\n";
		}

		//group
		if ($option_selected == 'group') {
			$sql = "select * from v_groups ";
			$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
			$sql .= "order by domain_uuid desc, group_name asc ";
			$parameters = [];
			$parameters['domain_uuid'] = $domain_uuid;
			$result = $database->select($sql, $parameters, 'all');
			$result_count = count($result);
			if ($result_count > 0) {
				if (isset($assigned_groups)) { echo "<br />\n"; }
				echo "	<select class='formfld' name='group_uuid' style='width: auto; margin-right: 3px;'>\n";
				echo "		<option value=''></option>\n";
				foreach ($result as $field) {
					if ($field['group_name'] == "superadmin" && !if_group("superadmin")) { continue; }	//only show the superadmin group to other superadmins
					if ($field['group_name'] == "admin" && (!if_group("superadmin") && !if_group("admin") )) { continue; }	//only show the admin group to other admins
					if ( !isset($assigned_groups) || (isset($assigned_groups) && !in_array($field["group_uuid"], $assigned_groups)) ) {
						echo "	<option value='{$field['group_uuid']}'>".escape($field['group_name']).(($field['domain_uuid'] != '') ? "@".$_SESSION['domains'][$field['domain_uuid']]['domain_name'] : null)."</option>\n";
					}
				}
				echo "	</select>";
			}
			unset($sql, $result);
		}

		echo "		</div>\n";
		echo "	</div>\n";

		echo "</div>\n";

		echo "<div style='display: flex; justify-content: flex-end; padding-top: 15px; margin-left: 20px; white-space: nowrap;'>\n";
		echo button::create(['label'=>$text['button-reset'],'icon'=>$settings->get('theme', 'button_icon_reset'),'type'=>'button','style'=>($option_selected == 'group' ? "margin-right: 15px;" : null),'link'=>'bulk_account_settings_users.php']);
		if ($option_selected == 'group') {
			// echo button::create([
			// 	'type' => 'submit',
			// 	'value'=> 'add',
			// 	'label'=> $text['button-add'],
			// 	'icon' => $settings->get('theme','button_icon_add'),
			// 	'name' => 'action',
			// 	'style'=> 'margin-left: 15px;',
			// 	'on-click'=> "if (confirm('".$text['confirm-update_users']."')) { document.forms.users.submit(); }",
			// ]);
			// echo button::create([
			// 	'type' => 'submit',
			// 	'value'=> 'remove',
			// 	'label'=> $text['button-remove'],
			// 	'icon' => 'fas fa-minus',
			// 	'name' => 'action',
			// 	'style'=> 'margin-left: 15px;',
			// 	'on-click'=> "if (confirm('".$text['confirm-update_users']."')) { document.forms.users.submit(); }",
			// ]);
			echo button::create(['label'=>$text['button-add'],'icon'=>$settings->get('theme', 'button_icon_add'),'type'=>'submit','id'=>'btn_add','name'=>'action','value'=>'add','click'=>"if (confirm('".$text['confirm-update_users']."')) { document.forms.users.submit(); }"]);
			echo button::create(['label'=>$text['button-remove'],'icon'=>$settings->get('theme', 'button_icon_delete'),'type'=>'submit','id'=>'btn_delete','name'=>'action','value'=>'remove','click'=>"if (confirm('".$text['confirm-update_users']."')) { document.forms.users.submit(); }"]);
		}
		else {
			echo button::create(['label'=>$text['button-update'],'icon'=>$settings->get('theme', 'button_icon_save'),'type'=>'submit','id'=>'btn_update','click'=>"if (confirm('".$text['confirm-update_users']."')) { document.forms.users.submit(); }"]);
		}
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
	if (!empty($users)) {
		echo "<th style='width: 30px; text-align: center; padding: 0px;'><input type='checkbox' id='chk_all' onchange=\"(this.checked) ? check('all') : check('none');\"></th>";
	}
	echo th_order_by('username', $text['label-username'], $order_by, $order, null, null, $param);
	echo th_order_by('user_status', $text['label-user_status'], $order_by, $order, null, null, $param);
	echo th_order_by('username', $text['label-group'], $order_by, $order, null, null, $param);
	echo th_order_by('username', $text['label-time_zone'], $order_by, $order, null, null, $param);
	echo th_order_by('user_enabled', $text['label-user_enabled'], $order_by, $order, null, "class='center'", $param);
	echo "</tr>\n";

	$user_ids = [];
	if (!empty($users)) {
		foreach($users as $key => $row) {
			$list_row_url = permission_exists('extension_edit') ? "/core/users/user_edit.php?id=".$row['user_uuid'] : null;
			echo "<tr class='list-row' href='".$list_row_url."'>\n";
			echo "	<td class='checkbox'>";
			echo "		<input type='checkbox' name='id[]' id='checkbox_".escape($row['user_uuid'])."' value='".escape($row['user_uuid'])."' onclick=\"if (!this.checked) { document.getElementById('chk_all').checked = false; }\">";
			echo "	</td>";
			$user_ids[] = 'checkbox_'.$row['user_uuid'];
			echo "	<td><a href='".$list_row_url."'>".escape($row['username'])."</a></td>\n";
			echo "	<td>".escape($row['user_status'])."&nbsp;</td>\n";
			echo "	<td>";
				if (!empty($user_groups[$row['user_uuid']])) {
					echo implode(', ', $user_groups[$row['user_uuid']]);
				}
				echo "&nbsp;</td>\n";
			echo "	<td>";
				if (isset($user_time_zone[$row['user_uuid']]) && sizeof($user_time_zone[$row['user_uuid']]) > 0) {
					echo implode(', ', $user_time_zone[$row['user_uuid']]);
				}
				echo "&nbsp;</td>\n";
			echo "	<td class='center'>".$text['label-'.(!empty($row['user_enabled']) ? 'true' : 'false')]."&nbsp;</td>\n";
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
	echo "<br /><br />".((!empty($users)) ? "<br /><br />" : null);

	// check or uncheck all checkboxes
	if (!empty($user_ids)) {
		echo "<script>\n";
		echo "	function check(what) {\n";
		echo "		document.getElementById('chk_all').checked = (what == 'all') ? true : false;\n";
		foreach ($user_ids as $user_id) {
			echo "		document.getElementById('".$user_id."').checked = (what == 'all') ? true : false;\n";
		}
		echo "	}\n";
		echo "</script>\n";
	}

	if (!empty($users)) {
		// check all checkboxes
		key_press('ctrl+a', 'down', 'document', null, null, "check('all');", true);

		// delete checked
		key_press('delete', 'up', 'document', array('#search'), $text['confirm-delete'], 'document.forms.frm.submit();', true);
	}

//show the footer
	require_once "resources/footer.php";
