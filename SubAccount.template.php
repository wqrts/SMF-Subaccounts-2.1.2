<?php
/**********************************************************************************
* SubAccount.template.php											  			  *
***********************************************************************************
* Software Version:           1.0.0		                          				  *
* Copyright 2008-2009 by:     Matt Zuba (http://www.mattzuba.com)				  *
***********************************************************************************
* This mod is free software; you can not redistribute it 					      *
*                                                                                 *
* This mod is distributed in the hope that it is and will be useful, but  		  *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY	  *
* or FITNESS FOR A PARTICULAR PURPOSE.                                       	  *
*                                                                             	  *
* The latest version can always be found at http://www.mattzuba.com.	     	  *
**********************************************************************************/

function template_manage_subaccounts()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt, $memberContext;

	// The main header!
	echo '
	<h3 class="catbg"><span class="left"></span>
		<img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="" />
		', $txt['managesubaccounts'], !$context['user']['is_owner'] ? ' - &quot;' . $context['member']['name'] . '&quot;' : '', '
	</h3>';

	// Have we some description?
	if (!empty($context['page_desc']))
	{
		echo '
			<div class="description">', $context['page_desc'];
		echo '
					<br /><br />
					<ul class="flow_hidden reset subaccount_icons">
						', $context['can_delete'] ? '<li><img align="middle" src="' . $settings['images_url'] . '/subaccount_delete.gif" alt="' . $txt['delete'] . '" title="' . $txt['delete'] . '" border="0" />' . $txt['delete'] . '&nbsp;</li>' : '', '
						', $context['can_merge'] ? '<li><img align="middle" src="' . $settings['images_url'] . '/subaccount_merge.gif" alt="' . $txt['button_merge'] . '" title="' . $txt['button_merge'] . '" border="0" />' . $txt['button_merge'] . '&nbsp;</li>' : '', '
						', $context['can_split'] ? '<li><img align="middle" src="' . $settings['images_url'] . '/subaccount_split.gif" alt="' . $txt['button_split'] . '" title="' . $txt['button_split'] . '" border="0" />' . $txt['button_split'] . '&nbsp;</li>' : '', '
						', $context['can_reassign'] ? '<li><img align="middle" src="' . $settings['images_url'] . '/subaccount_parent.gif" alt="' . $txt['button_parent'] . '" title="' . $txt['button_parent'] . '" border="0" />' . $txt['button_parent'] . '&nbsp;</li>' : '', '
						', $context['can_create'] ? '<li><img align="middle" src="' . $settings['images_url'] . '/subaccount_share.gif" alt="' . $txt['button_share'] . '" title="' . $txt['button_share'] . '" border="0" />' . $txt['button_share'] . '&nbsp;</li>' : '', '
					</ul>';
		echo '
			</div>';
	}

	if (empty($context['subaccounts']))
		echo '
			<p class="description">', $txt['current_subaccounts_none'], '</p>';
	else
	{
		echo '
			<ul id="subaccount_list" class="flow_hidden reset">';
		foreach($context['subaccounts'] as $account)
		{
			echo '
				<li class="subaccount">
					<span class="upperframe"><span></span></span>
					<ul class="roundframe flow_hidden">
						<li class="name"><a href="', $account['href'], '">', $account['name'], '</a></li>';
			// Now their group
			echo '
						<li class="group">', $account['group'], '</li>';

			if (!empty($account['stars']))
				echo '
						<li class="stars">', $account['stars'], '</li>';

			if (!isset($context['disabled_fields']['posts']))
				echo '
						<li class="posts">', $account['posts'], ' ', $txt['posts'], '</li>';

			echo '
						<li class="karma">', $txt['personal_messages'], ': ', !empty($account['messages']['unread']) ? $account['messages']['unread'] . '/' : '', $account['messages']['total'], '</li>';

			echo '
						<li class="action_buttons">
							<ul class="flow_hidden reset subaccount_icons">
								', $account['permissions']['can_delete'] ? '<li><a href="' . $scripturl . '?action=profile;area=managesubaccounts;sa=delete;u=' . $context['member']['id'] . ';subaccount=' . $account['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['deleteAccount_subaccount'] . '\') && confirm(\'' . $txt['deleteAccount_subaccount_sure'] . '\')"><img src="' . $settings['images_url'] . '/subaccount_delete.gif" alt="' . $txt['delete'] . '" title="' . $txt['delete'] . '" border="0" /></a></li>' : '', '
								', $account['permissions']['can_merge'] ? '<li><a href="' . $scripturl . '?action=profile;area=managesubaccounts;sa=merge;u=' . $context['member']['id'] . ';subaccount=' . $account['id'] . '"><img src="' . $settings['images_url'] . '/subaccount_merge.gif" alt="' . $txt['button_merge'] . '" title="' . $txt['button_merge'] . '" border="0" /></a></li>' : '', '
								', $account['permissions']['can_split'] ? '<li><a href="' . $scripturl . '?action=profile;area=managesubaccounts;sa=split;u=' . $context['member']['id'] . ';subaccount=' . $account['id'] . '"><img src="' . $settings['images_url'] . '/subaccount_split.gif" alt="' . $txt['button_split'] . '" title="' . $txt['button_split'] . '" border="0" /></a></li>' : '', '
								', $account['permissions']['can_reassign'] ? '<li><a href="' . $scripturl . '?action=profile;area=managesubaccounts;sa=reassign;u=' . $context['member']['id'] . ';subaccount=' . $account['id'] . '"><img src="' . $settings['images_url'] . '/subaccount_parent.gif" alt="' . $txt['button_parent'] . '" title="' . $txt['button_parent'] . '" border="0" /></a></li>' : '', '
								', $account['permissions']['can_share'] ? '<li><a href="' . $scripturl . '?action=profile;area=managesubaccounts;sa=create;u=' . $context['member']['id'] . ';subaccount=' . $account['id'] . ';make_shared"><img src="' . $settings['images_url'] . '/subaccount_' . ($account['is_shared'] ? 'un' : '') . 'share.gif" alt="' . $txt['button_share'] . '" title="' . $txt['button_share'] . '" border="0" /></a></li>' : '', '
							</ul>
						</li>';
			echo '
					</ul>
					<span class="lowerframe"><span></span></span>
				</li>';
		}
		echo '
			</ul>';
	}

	if(!empty($context['can_create']))
		echo '
			<form action="', $scripturl, '?action=profile;area=managesubaccounts;sa=create;u=',$context['member']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="creator" id="creator" enctype="multipart/form-data"><input class="button_submit" type="submit" name="create" value="', $txt['button_create'],'" /></form>';
}

function template_manage_subaccounts_create()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<script language="JavaScript" type="text/javascript" src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<h3 class="catbg"><span class="left"></span>
			<img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="" />
			', $txt['managesubaccounts'], !$context['user']['is_owner'] ? ' - &quot;' . $context['member']['name'] . '&quot;' : '', '
		</h3>
		<p class="description">', $txt['create_subaccount_desc'], '</p>
		<form action="', $scripturl, '?action=profile;area=managesubaccounts;sa=create;u=',$context['member']['id'], '" method="post" accept-charset="', $context['character_set'], '" name="postForm" id="postForm" enctype="multipart/form-data" class="windowbg2">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="register_form">
					<dt>
						<strong>', $txt['choose_subaccount'], ':</strong>
						<span class="smalltext">', $txt['identification_by_smf'], '</span>
					</dt>
					<dd>
						<input type="text" name="username" id="smf_autov_username" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '" />
						<span id="smf_autov_username_div" style="display: none;">
							<a id="smf_autov_username_link" href="#">
								<img id="smf_autov_username_img" src="', $settings['images_url'], '/icons/field_check.gif" alt="*" />
							</a>
						</span>
					</dd>
					<dt>
						<strong>', $txt['password'], ':</strong>
						<span class="smalltext">', $txt['subaccount_create_pass'], '</span>
					</dt>
					<dd>
						<input type="password" id="smf_autov_pwmain" name="passwrd1" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
					<dt><strong>', $txt['verify_pass'], ':</strong></dt>
					<dd>
						<input type="password" id="smf_autov_pwverify" name="passwrd2" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
				</dl>
				<p id="confirm_buttons">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input class="button_submit" name="submit" type="submit" value="', $txt['button_create'], '" tabindex="', $context['tabindex']++, '" /></td>
				</p>
			</div>
			<span class="botslice"><span></span></span>
		</form>
		<br class="clear" />
		<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[
			var regTextStrings = {
				"username_valid": "', $txt['registration_username_available'], '",
				"username_invalid": "', $txt['registration_username_unavailable'], '",
				"username_check": "', $txt['registration_username_check'], '",
				"password_short": "', $txt['registration_password_short'], '",
				"password_reserved": "', $txt['registration_password_reserved'], '",
				"password_numbercase": "', $txt['registration_password_numbercase'], '",
				"password_no_match": "', $txt['registration_password_no_match'], '",
				"password_valid": "', $txt['registration_password_valid'], '"
			};
			verificationHandle = new smfRegister("postForm", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
		// ]]></script>';

}

function template_manage_subaccounts_merge()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<h3 class="catbg"><span class="left"></span>
			<img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="" />
			', $txt['managesubaccounts'], !$context['user']['is_owner'] ? ' - &quot;' . $context['member']['name'] . '&quot;' : '', '
		</h3>
		<p class="description">', $context['page_desc'], '</p>';
	echo '
		<form class="windowbg2" action="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=managesubaccounts;sa=merge" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="register_form">';

	foreach($context['subaccounts'] as $account)
		echo '
						<dt><input onfocus="this.blur()" type="radio" name="parent" value="', $account['id'], '" />', $account['name'], $account['id'] == $context['member']['id'] ? '&nbsp;<strong class="smalltext">(' . $txt['parent_account'] . ')</strong>' : '' , '</dt>';

	echo '
				</dl>
				<br />
				<input class="button_submit" type="submit" name="submit" value="', $txt['button_merge'], '" onclick="return confirm(\'', $txt['deleteAccount_subaccount_sure'], '\')" />
				<input type="hidden" name="subaccount" value="', $context['subaccount'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</div>
			<span class="botslice"><span></span></span>
		</form>
		<br class="clear" />';
}

function template_manage_subaccounts_reassign()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<script language="JavaScript" type="text/javascript" src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<h3 class="catbg"><span class="left"></span>
			<img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="" />
			', $txt['managesubaccounts'], !$context['user']['is_owner'] ? ' - &quot;' . $context['member']['name'] . '&quot;' : '', '
		</h3>
		<p class="description">', $context['page_desc'], '</p>
		<form class="windowbg2" action="', $scripturl, '?action=profile;u=', $context['member']['id'], ';area=managesubaccounts;sa=reassign" name="creator" id="creator" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="register_form">
					<dt><strong>', $txt['button_parent'], ':</strong></dt>
					<dd>
						<input type="hidden" name="subaccount" value="', $context['subaccount']['id'], '" /><strong>', $context['subaccount']['name'], '</strong>
					</dd>
					<dt><strong>', $txt['choose_pass'], ':</strong></dt>
					<dd>
						<input type="password" id="smf_autov_pwmain" name="pwmain" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
					<dt><strong>', $txt['verify_pass'], ':</strong></dt>
					<dd>
						<input type="password" id="smf_autov_pwverify" name="pwverify" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
				</dl>
				<p id="confirm_buttons">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input class="button_submit" type="submit" name="submit" value="', $txt['button_parent'], '" />
				</p>
			</div>
			<span class="botslice"><span></span></span>
		</form>
		<br class="clear" />
<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[';

	// Clever registration stuff...
	echo '
	var regTextStrings = {
		"username_valid": "', $txt['registration_username_available'], '",
		"username_invalid": "', $txt['registration_username_unavailable'], '",
		"username_check": "', $txt['registration_username_check'], '",
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
';

	echo '
// ]]></script>';

}

function template_manage_subaccounts_split()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// The main header!
	echo '
		<script language="JavaScript" type="text/javascript" src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<h3 class="catbg"><span class="left"></span>
			<img src="', $settings['images_url'], '/icons/profile_sm.gif" alt="" />
			', $txt['managesubaccounts'], !$context['user']['is_owner'] ? ' - &quot;' . $context['member']['name'] . '&quot;' : '', '
		</h3>
		<p class="description">', $context['page_desc'], '</p>
		<form class="windowbg2" action="', $scripturl, '?action=profile;area=managesubaccounts;sa=split;u=', $context['member']['id'], '" name="creator" id="creator" method="post" accept-charset="', $context['character_set'], '" enctype="multipart/form-data">
			<span class="topslice"><span></span></span>
			<div class="content">
				<dl class="register_form">
					<dt><strong>', $txt['subaccount'], ':</strong></dt>
					<dd><input type="hidden" name="subaccount" value="', $context['subaccount']['id'], '" /><strong>', $context['subaccount']['name'], '</strong></dd>
					<dt><strong>', $txt['email'], ':</strong></dt>
					<dd><input type="text" id="smf_autov_reserve2" name="email" size="40" tabindex="', $context['tabindex']++, '" value="', !empty($context['form_email']) ? $context['form_email'] : '', '" /></dd>
					<dt><strong>', $txt['choose_pass'], ':</strong></dt>
					<dd>
						<input type="password" id="smf_autov_pwmain" name="pwmain" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
					<dt><strong>', $txt['verify_pass'], ':</strong></dt>
					<dd>
						<input type="password" id="smf_autov_pwverify" name="pwverify" size="15" tabindex="', $context['tabindex']++, '" />
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.gif" alt="*" />
						</span>
					</dd>
				</dl>
				<p id="confirm_buttons">
					<input class="button_submit" type="submit" name="submit" value="', $txt['button_split'], '" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</p>
			</div>
			<span class="botslice"><span></span></span>
		</form>
		<br class="clear" />
<script language="JavaScript" type="text/javascript"><!-- // --><![CDATA[';

	// Clever registration stuff...
	echo '
	var regTextStrings = {
		"username_valid": "', $txt['registration_username_available'], '",
		"username_invalid": "', $txt['registration_username_unavailable'], '",
		"username_check": "', $txt['registration_username_check'], '",
		"password_short": "', $txt['registration_password_short'], '",
		"password_reserved": "', $txt['registration_password_reserved'], '",
		"password_numbercase": "', $txt['registration_password_numbercase'], '",
		"password_no_match": "', $txt['registration_password_no_match'], '",
		"password_valid": "', $txt['registration_password_valid'], '"
	};
	verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
';

	echo '
// ]]></script>';

}

?>