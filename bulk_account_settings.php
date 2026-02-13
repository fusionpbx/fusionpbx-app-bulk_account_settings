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

//check permissions
	if (!permission_exists('bulk_account_settings_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//show the header
	$document['title'] = $text['title-bulk_account_settings'];
	require_once "resources/header.php";

//show the content
	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$text['header-bulk_account_settings']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo "<a href='bulk_account_settings_destinations.php'>".button::create(['type'=>'button','label'=>$text['button-destinations'],'icon'=>'location-arrow'])."</a>";
	echo "<a href='bulk_account_settings_devices.php'>".button::create(['type'=>'button','label'=>$text['button-devices'],'icon'=>'mobile-retro'])."</a>";
	echo "<a href='bulk_account_settings_extensions.php'>".button::create(['type'=>'button','label'=>$text['button-extensions'],'icon'=>'suitcase'])."</a>";
	echo "<a href='bulk_account_settings_gateways.php'>".button::create(['type'=>'button','label'=>$text['button-gateways'],'icon'=>'globe'])."</a>";
	echo "<a href='bulk_account_settings_users.php'>".button::create(['type'=>'button','label'=>$text['button-users'],'icon'=>'user-group'])."</a>";
	echo "<a href='bulk_account_settings_voicemails.php'>".button::create(['type'=>'button','label'=>$text['button-voicemails'],'icon'=>'envelope'])."</a>";
	// echo "<a href='bulk_account_settings_call_routing.php'>".button::create(['type'=>'button','label'=>$text['button-call_routing']])."</a>";
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

//show the footer
	require_once "resources/footer.php";
