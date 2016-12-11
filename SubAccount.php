<?php
/**********************************************************************************
* SubAccount.php																  *
***********************************************************************************
* Software Version:           1.0.0		                          				  *
* Copyright 2008-2009 by:     Matt Zuba (http://www.mattzuba.com)				  *
***********************************************************************************
* This mod is free software; you can not redistribute it 					  *
*                                                                                 *
* This mod is distributed in the hope that it is and will be useful, but  		  *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY	  *
* or FITNESS FOR A PARTICULAR PURPOSE.                                       	  *
*                                                                             	  *
* The latest version can always be found at http://www.mattzuba.com.	     	  *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file has the primary job of showing and editing people's subaccounts.

	void SubAccountBrowse(int id_member)
		// !!!

	void SubAccountCreate(int id_member)
		// !!!

	void SubAccountDelete(int id_member)
		// !!!

	void SubAccountMerge(int id_member)
		// !!!

	void SubAccountSplit(int id_member)
		// !!!

	void SwitchSubAccount()
		// !!!

*/

function SubAccountBrowse($memID)
{
	global $context, $txt, $cur_profile, $smcFunc, $memberContext;

	$context['sub_template'] = 'manage_subaccounts';
	$context['page_desc'] = $txt['modifysubaccounts_desc'];

	$loaded_ids = !empty($cur_profile['subaccounts']) ? array_unique(loadMemberData(array_keys($cur_profile['subaccounts']))) : array();

	// Need to get the PM info for the subaccounts too
	if (!empty($loaded_ids))
	{
		$request = $smcFunc['db_query']('', '
			SELECT id_member, instant_messages, unread_messages
			FROM {db_prefix}members
			WHERE id_member in ({array_int:subaccounts})',
			array(
				'subaccounts' => $loaded_ids,
			)
		);
		$pms = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$pms[$row['id_member']] = array(
				'total' => $row['instant_messages'],
				'unread' => $row['unread_messages'],
			);
		$smcFunc['db_free_result']($request);

		// Setup the array
		foreach ($loaded_ids as $id)
		{
			if (loadMemberContext($id))
			{
				$context['subaccounts'][] = array(
					'id' => $id,
					'name' => $memberContext[$id]['name'],
					'href' => $memberContext[$id]['href'],
					'group' => !empty($memberContext[$id]['group']) ? $memberContext[$id]['group'] : $memberContext[$id]['post_group'],
					'posts' => $memberContext[$id]['posts'],
					'messages' => $pms[$id],
					'website' => $memberContext[$id]['website'],
					'permissions' => array(
						'can_delete' => $context['can_delete'],
						'can_merge' => $context['can_merge'] && (empty($memberContext[$id]['is_shareable']) || $memberContext[$id]['is_shareable'] == $cur_profile['id_member']),
						'can_split' => $context['can_split'] && (empty($memberContext[$id]['is_shareable']) || $memberContext[$id]['is_shareable'] == $cur_profile['id_member']),
						'can_reassign' => $context['can_reassign'] && (empty($memberContext[$id]['is_shareable']) || $memberContext[$id]['is_shareable'] == $cur_profile['id_member']),
						'can_share' => $context['can_create'] && (empty($memberContext[$id]['is_shareable']) || $memberContext[$id]['is_shareable'] == $cur_profile['id_member']),
					),
					'is_shared' => !empty($memberContext[$id]['is_shareable']),
				);
			}
		}
	}
}

function SubAccountCreate($memID)
{
	global $context, $smcFunc, $txt, $sourcedir, $user_info, $cur_profile, $modSettings;

	$context['sub_template'] = 'manage_subaccounts_create';

	if (empty($cur_profile['additional_groups']))
		$user_groups = array($cur_profile['id_group'], $cur_profile['id_post_group']);
	else
		$user_groups = array_merge(
				array($cur_profile['id_group'], $cur_profile['id_post_group']),
				explode(',', $cur_profile['additional_groups'])
			);

	$context['member']['is_admin'] = in_array(1, $user_groups);

	if (isset($_REQUEST['make_shared']))
	{
		if (empty($_GET['subaccount']))
		{
			$context['custom_error_title'] = $txt['subaccount_error'];
			$context['post_errors'][] = $txt['subaccount_not_selected'];
			return SubAccountBrowse($memID);
		}

		$subaccount = (int) $_GET['subaccount'];

		// Let's do some checks first.
		// If this is a subaccount, it shouldn't even have subaccounts, but you never know...
		if (!empty($cur_profile['id_parent']) || !array_key_exists($subaccount, $cur_profile['subaccounts']) || (!empty($cur_profile['subaccounts'][$subaccount]['shareable']) && ($cur_profile['subaccounts'][$subaccount]['shareable'] != $cur_profile['id_member'] || !$context['member']['is_admin'])))
			fatal_lang_error('cannot_subaccounts_create_own', false);

		if (!empty($cur_profile['subaccounts'][$subaccount]['shareable']))
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_parent
				FROM {db_prefix}subaccounts
				WHERE id_member = {int:subaccount}
					AND id_parent != {int:parent}',
				array(
					'subaccount' => $subaccount,
					'parent' => $cur_profile['id_member'],
				)
			);
			$deleteUsers = array();
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$deleteUsers[] = $row['id_parent'];

			if (!empty($deleteUsers))
			{
				$smcFunc['db_query']('', '
					DELETE FROM {db_prefix}subaccounts
					WHERE id_parent IN ({array_int:members})
						AND id_member = {int:subaccount}',
					array(
						'members' => $deleteUsers,
						'subaccount' => $subaccount,
					)
				);

				foreach ($deleteUsers as $id)
					cache_put_data('user_subaccounts-' . $id, null, 240);

			}
			updateMemberData($subaccount, array('is_shareable' => 0));
		}
		else
			updateMemberData($subaccount, array('is_shareable' => $cur_profile['id_member']));

		cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

		redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
	}
	elseif (isset($_POST['submit']))
	{
		// Make sure they came from *somewhere*, have a session.
		checkSession();

		foreach ($_POST as $key => $value) {
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));
		}

		$username = !empty($_POST['username']) ? $_POST['username'] : '';

		require_once($sourcedir . '/Subs-Members.php');

		// Is this an existing member??
		$request = $smcFunc['db_query']('', '
			SELECT mem.id_member, mem.passwd, mem.member_name, IFNULL(sub.id_parent, 0) as is_subaccount, mem.is_shareable
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}subaccounts AS sub ON (sub.id_member = mem.id_member)
			WHERE ' . ($smcFunc['db_case_sensitive'] ? 'LOWER(member_name)' : 'member_name') . ' = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $smcFunc['db_case_sensitive'] ? strtolower($username) : $username,
			)
		);

		if ($member = $smcFunc['db_fetch_assoc']($request))
		{
			// If the member found is the one this profile belongs to, die out...
			if ($member['id_member'] == $cur_profile['id_member'] || (!empty($member['is_subaccount']) && (empty($member['is_shareable']) || $member['is_subaccount'] == $cur_profile['id_member'])))
			{
				loadLanguage('Errors');
				$context['custom_error_title'] = $txt['subaccount_error'];
				$context['post_errors'][] = 'name_taken';
				return;
			}

			// Lets see if they got the password right...
			$passwd = sha1(strtolower($member['member_name']) . (!empty($_POST['passwrd1']) ? un_htmlspecialchars($_POST['passwrd1']) : ''));

			// Looks like they're smart enough to have this.  Now we need to get any subaccounts that the new subaccount might
			// already have and convert them...
			if ($member['passwd'] == $passwd)
			{
				// Let's see if it has any existing aliases
				$request = $smcFunc['db_query']('', '
					SELECT sub.id_member, mem.is_shareable
					FROM {db_prefix}subaccounts AS sub
						INNER JOIN {db_prefix}members AS mem ON (mem.id_member = sub.id_member)
					WHERE sub.id_parent = {int:parent}',
					array(
						'parent' => $member['id_member'],
					)
				);

				$changeUsers = array();
				$sharedUsers = array();
				$createdSharedUsers = array();
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					// If the current subaccount is a shared account, and it wasn't created by the parent we're merging here, add it
					// to an array so we don't udpate it's email address
					if (!empty($row['is_shareable']) && $row['is_shareable'] != $member['id_member'])
						$sharedUsers[] = $row['id_member'];
					elseif (!empty($row['is_shareable']) && $row['is_shareable'] == $member['id_member'])
						$createdSharedUsers[] = $row['id_member'];

					$changeUsers[] = $row['id_member'];
				}
				$smcFunc['db_free_result']($request);

				// Let's check the table for any possible subaccounts that would be linked, that already are and delete them
				if (!empty($changeUsers))
					$smcFunc['db_query']('', '
						DELETE FROM {db_prefix}subaccounts
						WHERE id_member IN ({array_int:changeusers})
							AND id_parent = {int:new_parent}',
						array(
							'changeusers' => $changeUsers,
							'new_parent' => $cur_profile['id_member'],
						)
					);

				// Update any accounts linked to the one we're really linking up
				$smcFunc['db_query']('', '
					UPDATE {db_prefix}subaccounts
					SET id_parent = {int:new_parent}
					WHERE id_parent = {int:old_parent}',
					array(
						'new_parent' => $cur_profile['id_member'],
						'old_parent' => $member['id_member'],
					)
				);

				// Add our current user
				$changeUsers[] = $member['id_member'];

				// Change all subaccounts except those that are shared and not owned to have this profiles email address
				updateMemberData(array_diff($changeUsers, $sharedUsers), array('email_address' => $cur_profile['email_address']));

				// If there are any shared aliases created by the mergee, update them to be owned by the new owner
				if (!empty($createdSharedUsers))
					updateMemberData($createdSharedUsers, array('is_shareable' => $cur_profile['id_member']));

				// Finally add the member to the subaccounts table
				$smcFunc['db_insert']('',
					'{db_prefix}subaccounts',
					array('id_member' => 'int', 'id_parent' => 'int'),
					array($member['id_member'], $cur_profile['id_member']),
					array('id_parent')
				);

				// Delete the cache for this member
				cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

				redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
			}
			// Sorry, you lose...
			else
			{
				loadLanguage('Errors');
				$context['custom_error_title'] = $txt['subaccount_error'];
				$context['post_errors'][] = 'bad_password';
				return;
			}
		}
		$smcFunc['db_free_result']($request);

		// Set the options needed for registration.
		$regOptions = array(
			'interface' => '',
			'username' => $username,
			// Create a fake email address just to pass validation, it'll get changed automagically with the extra_register_vars array.
			'email' => substr(preg_replace('/\W/', '', md5(rand())), 0, 4).'@'.substr(preg_replace('/\W/', '', md5(rand())), 0, 5).'.com',
			'password' => !empty($_POST['passwrd1']) ? un_htmlspecialchars($_POST['passwrd1']) : '',
			'password_check' => !empty($_POST['passwrd2']) ? un_htmlspecialchars($_POST['passwrd2']) : '',
			'openid' => '',
			'auth_method' => 'password',
			'check_reserved_name' => true,
			'check_password_strength' => true,
			'check_email_ban' => false,
			'send_welcome_email' => '',
			'require' => 'nothing',
			'theme_vars' => array(),
			'memberGroup' => !empty($modSettings['subaccountsInheritParentGroup']) ? $cur_profile['id_group'] : 0,
			'extra_register_vars' => array(
				'email_address' => $cur_profile['email_address'],
				'warning' => $cur_profile['warning'],
				'time_offset' => $cur_profile['time_offset'],
				'lngfile' => $cur_profile['lngfile'],
				),
		);

		foreach ($cur_profile['options'] as $var => $value)
			$regOptions['theme_vars'][$var] = $value;

		$memberID = registerMember($regOptions, true);

		// Was there actually an error of some kind dear boy?
		if (is_array($memberID)) {
			$context['custom_error_title'] = $txt['subaccount_error'];
			$context['post_errors'] = $memberID;
			return;
		}

		// Finally add the member to the subaccounts table
		$smcFunc['db_insert']('',
			'{db_prefix}subaccounts',
			array('id_member' => 'int', 'id_parent' => 'int'),
			array($memberID, $cur_profile['id_member']),
			array('id_parent')
		);

		// Delete the Cache for this user
		cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

		redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
	}
}

function SubAccountDelete($memID)
{
	global $sourcedir, $modSettings, $user_info, $smcFunc, $txt, $context, $cur_profile;

	// Make sure they came from *somewhere*, have a session.
	checkSession('get');

	if (empty($_GET['subaccount']))
	{
		$context['custom_error_title'] = $txt['subaccount_error'];
		$context['post_errors'][] = $txt['subaccount_not_selected'];
		return SubAccountBrowse($memID);
	}

	$subaccount = (int) $_GET['subaccount'];

	// Let's do some checks first.
	// If this is an subaccount, it shouldn't even have subaccounts, but you never know...
	if (!empty($cur_profile['id_parent']) || !array_key_exists($subaccount, $cur_profile['subaccounts']))
		fatal_lang_error('cannot_delete_subaccount', false);

	// Get their name for logging purposes.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
		FROM {db_prefix}members
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
			'admin_group' => 1,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
	// Just a small check to make sure that the board will never loose their only admin this way
	if (!empty($row['is_admin']) && !allowedTo('admin_forum'))
		fatal_lang_error('cannot_delete_subaccount', false);

	// Log the action - regardless of who is deleting it.
	if (!empty($modSettings['modlog_enabled']))
	{
		$log_inserts = array();

		// Add it to both the moderation and admin logs as it effects both.
		$log_inserts[] = array(
			time(), 3, $user_info['id'], $user_info['ip'], 'delete_subaccount',
			0, 0, 0, serialize(array('member' => $row['id_member'], 'name' => $row['member_name'], 'parent' => $cur_profile['member_name'])),
		);
		$log_inserts[] = array(
			time(), 1, $user_info['id'], $user_info['ip'], 'delete_subaccount',
			0, 0, 0, serialize(array('member' => $row['id_member'], 'name' => $row['member_name'], 'parent' => $cur_profile['member_name'])),
		);

		// Do the actual logging.
		$smcFunc['db_insert']('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts,
			array('id_action')
		);
	}

	// If they don't own this sharable id, but don't want it, just remove it as a link in the table
	if (!empty($cur_profile['subaccounts'][$subaccount]['shareable']) && $cur_profile['id_member'] != $cur_profile['subaccounts'][$subaccount]['shareable'])
	{
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}subaccounts
			WHERE id_member = {int:user}
			AND id_parent = {int:parent}',
			array(
				'user' => $subaccount,
				'parent' => $cur_profile['id_member'],
			)
		);

		cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);
		redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
	}

	// Integration rocks!
	if (isset($modSettings['integrate_delete_member']) && function_exists($modSettings['integrate_delete_member']))
		call_user_func($modSettings['integrate_delete_member'], $row['id_member']);

	// Remove them from the subaccounts table
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}subaccounts
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Convert their posts  to the parent id and name
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:parent_id}, poster_name = {string:parent_name}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'parent_name' => $cur_profile['real_name'],
			'user' => $subaccount,
		)
	);
	$messageCount = $smcFunc['db_affected_rows']();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET id_member = {int:parent_id}, poster_name = {string:parent_name}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'parent_name' => $cur_profile['real_name'],
			'user' => $subaccount,
		)
	);

	// Make these peoples' posts guest first posts and last posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:parent_id}
		WHERE id_member_started = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:parent_id}
		WHERE id_member_updated = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_actions
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);

	// Delete the bans
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_banned
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_errors
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);

	// Delete the member.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}members
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Delete the logs...
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:user}',
		array(
			'log_type' => 2,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_boards
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_group_requests
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma
		WHERE id_target = {int:user}
			OR id_executor = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_notify
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_topics
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collapsed_categories
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Make their votes appear as their parent's votes - at least it keeps the totals right.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_polls
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);

	// Delete personal messages.
	require_once($sourcedir . '/PersonalMessage.php');
	deleteMessages(null, null, $subaccount);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:parent_id}
		WHERE id_member_from = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);

	// Delete avatar.
	require_once($sourcedir . '/ManageAttachments.php');
	removeAttachments(array('id_member' => $subaccount));

	// Update Attachment Ownership
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $cur_profile['id_member'],
			'user' => $subaccount,
		)
	);
	// It's over, no more moderation for you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}moderators
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}group_moderators
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// If you don't exist we can't ban you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_items
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Remove individual theme settings.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// These users are nobody's buddy nomore.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, pm_ignore_list, buddy_list
		FROM {db_prefix}members
		WHERE FIND_IN_SET({int:user}, pm_ignore_list) OR FIND_IN_SET({int:user}, buddy_list)',
		array(
			'user' => $subaccount,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				pm_ignore_list = {string:pm_ignore_list},
				buddy_list = {string:buddy_list}
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $row['id_member'],
				'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), array($subaccount))),
				'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), array($subaccount))),
			)
		);
	$smcFunc['db_free_result']($request);

	// Make sure no member's birthday is still sticking in the calendar...
	updateSettings(array(
		'calendar_updated' => time(),
	));

	updateStats('member');

	if (!empty($messageCount))
		updateMemberData($cur_profile['id_member'], array('posts' => 'posts + ' . $messageCount));

	// Delete the Cache for this user
	cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

	redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
}

function SubAccountMerge($memID)
{
	global $sourcedir, $modSettings, $user_info, $smcFunc, $txt, $context, $cur_profile;

	// If we don't even have this request variable, there's nothing we can do here, spit out an error.
	if (empty($_REQUEST['subaccount']))
	{
		$context['custom_error_title'] = $txt['subaccount_error'];
		$context['post_errors'][] = $txt['subaccount_not_selected'];
		return SubAccountBrowse($memID);
	}

	$subaccount = (int) $_REQUEST['subaccount'];

	// Let's do some checks first.
	// If this is an subaccount, it shouldn't even have subaccounts, but you never know...
	// Does the subaccount belong to the user we're trying to remove it from?
	if (!empty($cur_profile['id_parent']) || !array_key_exists($subaccount, $cur_profile['subaccounts']))
		fatal_lang_error('cannot_delete_subaccount', false);

	if (!empty($cur_profile['subaccounts'][$subaccount]['shareable']) && $cur_profile['id_member'] != $cur_profile['subaccounts'][$subaccount]['shareable'])
		fatal_lang_error('cannot_delete_subaccount_shared', false);

	// Setup the array of subaccounts for the template
	$context['subaccounts'] = array();
	$context['subaccounts'][$cur_profile['id_member']] = array('id' => $cur_profile['id_member'], 'name' => $cur_profile['real_name']);
	$context['subaccounts'] += $cur_profile['subaccounts'];

	// Do the actual merge (otherwise display the template to choose who to merge to)
	if (!isset($_POST['submit']))
	{
		$context['sub_template'] = 'manage_subaccounts_merge';

		$context['subaccount'] = $subaccount;
		$context['page_desc'] = sprintf($txt['subaccounts_merge_desc'], $context['subaccounts'][$subaccount]['name']);

		// We don't want to merge a user with itself...
		unset($context['subaccounts'][$subaccount]);

		return;
	}

	// Make sure they came from *somewhere*, have a session.
	checkSession();

	if (empty($_POST['parent']) || (isset($_POST['parent']) && !array_key_exists($_POST['parent'], $context['subaccounts'])))
	{
		$context['custom_error_title'] = $txt['subaccount_error'];
		$context['post_errors'][] = $txt['subaccount_not_selected'];
		return SubAccountBrowse($memID);
	}

	$parentAccount = (int) $_POST['parent'];

	// Get their name for logging purposes.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, CASE WHEN id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0 THEN 1 ELSE 0 END AS is_admin
		FROM {db_prefix}members
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
			'admin_group' => 1,
		)
	);

	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Make sure they aren't trying to delete administrators if they aren't one.  But don't bother checking if it's just themself.
	// Just a small check to make sure that the board will never loose their only admin this way
	if (!empty($row['is_admin']) && !allowedTo('admin_forum'))
		fatal_lang_error('cannot_delete_subaccount', false);

	// Log the action - regardless of who is deleting it.
	if (!empty($modSettings['modlog_enabled']))
	{
		$log_inserts = array();

		// Add it to both the moderation and admin logs as it effects both.
		$log_inserts[] = array(
			time(), 3, $user_info['id'], $user_info['ip'], 'merge_subaccount',
			0, 0, 0, serialize(array('member' => $row['id_member'], 'name' => $row['member_name'], 'parent' => $context['subaccounts'][$parentAccount])),
		);
		$log_inserts[] = array(
			time(), 1, $user_info['id'], $user_info['ip'], 'merge_subaccount',
			0, 0, 0, serialize(array('member' => $row['id_member'], 'name' => $row['member_name'], 'parent' => $context['subaccounts'][$parentAccount])),
		);

		// Do the actual logging.
		$smcFunc['db_insert']('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'string-16', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts,
			array('id_action')
		);
	}

	// Integration rocks!
	if (isset($modSettings['integrate_delete_member']) && function_exists($modSettings['integrate_delete_member']))
		call_user_func($modSettings['integrate_delete_member'], $row['id_member']);

	// Remove them from the subaccounts table
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}subaccounts
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Make these peoples' posts guest posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}messages
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$messageCount = $smcFunc['db_affected_rows']();

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}polls
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	// Make these peoples' posts guest first posts and last posts.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_started = {int:parent_id}
		WHERE id_member_started = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET id_member_updated = {int:parent_id}
		WHERE id_member_updated = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_actions
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_banned
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_errors
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	// Delete the member.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}members
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Change the logs...
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_actions
		SET id_member = {int:parent_id}
		WHERE id_log = {int:log_type}
			AND id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'log_type' => 2,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_boards
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_group_requests
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_karma
		SET id_target = {int:parent_id}
		WHERE id_target = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_karma
		SET id_executor = {int:parent_id}
		WHERE id_executor = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_mark_read
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_notify
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_topics
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}collapsed_categories
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Make their votes appear as the parents votes - at least it keeps the totals right.
	//!!! Consider adding back in cookie protection.
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}log_polls
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	// Still might need some work on this.  Not sure that it'll work 100%, but at least
	// messages won't be lost (I hope)
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}pm_recipients
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	$smcFunc['db_query']('', '
		UPDATE {db_prefix}personal_messages
		SET id_member_from = {int:parent_id}
		WHERE id_member_from = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	// Delete avatar.
	require_once($sourcedir . '/ManageAttachments.php');
	removeAttachments(array('id_member' => $subaccount));

	// Update Attachment Ownership
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}attachments
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	// It's over, no more moderation for you.
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}moderators
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);
	$smcFunc['db_query']('', '
		UPDATE IGNORE {db_prefix}group_moderators
		SET id_member = {int:parent_id}
		WHERE id_member = {int:user}',
		array(
			'parent_id' => $parentAccount,
			'user' => $subaccount,
		)
	);

	// If you don't exist we can't ban you.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}ban_items
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Remove individual theme settings.
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}themes
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// These users are nobody's buddy nomore.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, pm_ignore_list, buddy_list
		FROM {db_prefix}members
		WHERE FIND_IN_SET({int:user}, pm_ignore_list) OR FIND_IN_SET({int:user}, buddy_list)',
		array(
			'user' => $subaccount,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				pm_ignore_list = {string:pm_ignore_list},
				buddy_list = {string:buddy_list}
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => $row['id_member'],
				'pm_ignore_list' => implode(',', array_diff(explode(',', $row['pm_ignore_list']), array($subaccount))),
				'buddy_list' => implode(',', array_diff(explode(',', $row['buddy_list']), array($subaccount))),
			)
		);
	$smcFunc['db_free_result']($request);

	// Make sure no member's birthday is still sticking in the calendar...
	updateSettings(array(
		'calendar_updated' => time(),
	));

	updateStats('member');

	if (!empty($messageCount))
		updateMemberData($parentAccount, array('posts' => 'posts + ' . $messageCount));

	// Delete the Cache for this user
	cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

	redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
}

function SubAccountSplit($memID)
{
	global $context, $smcFunc, $user_info, $txt, $cur_profile, $sourcedir, $user_profile;

	// Make sure they came from *somewhere*, have a session.
	// If we don't even have this request variable, there's nothing we can do here, spit out an error.
	if (empty($_REQUEST['subaccount']))
	{
		$context['custom_error_title'] = $txt['subaccount_error'];
		$context['post_errors'][] = $txt['subaccount_not_selected'];
		return SubAccountBrowse($memID);
	}

	$subaccount = (int) $_REQUEST['subaccount'];

	// Let's do some checks first.
	// If this is an subaccount, it shouldn't even have subaccounts, but you never know...
	// Does the subaccount belong to the user we're trying to remove it from?
	if (!empty($cur_profile['id_parent']) || !array_key_exists($subaccount, $cur_profile['subaccounts']))
		fatal_lang_error('cannot_delete_subaccount', false);

	if (!empty($cur_profile['subaccounts'][$subaccount]['shareable']) && $cur_profile['id_member'] != $cur_profile['subaccounts'][$subaccount]['shareable'])
		fatal_lang_error('cannot_delete_subaccount_shared', false);

	if (empty($_POST['submit']))
	{
		$context['sub_template'] = 'manage_subaccounts_split';
		$context['page_desc'] = $txt['subaccounts_split_desc'];
		$context['subaccount'] = array(
			'id' => $subaccount,
			'name' => $cur_profile['subaccounts'][$subaccount]['name'],
		);
		return;
	}
	// Do the splits... err... split
	// This should be fairly easy... erase the parent ID, reset the password and the email and call
	// it a day... Gotta do the proper checks first though.  If anyone fails, we fall through on all
	// Clean up the POST variables.
	$_POST = htmltrim__recursive($_POST);
	$_POST = htmlspecialchars__recursive($_POST);

	require_once($sourcedir . '/Subs-Auth.php');

	$context['post_errors'] = array();

	// Check the password
	$passwordErrors = validatePassword($_POST['pwmain'], $cur_profile['subaccounts'][$subaccount]);
	// Were there errors?
	if ($passwordErrors != null)
		$context['post_errors'][] = 'password_' . $passwordErrors;

	$emailErrors = profileValidateEmail($_POST['email']);
	if ($emailErrors !== true)
		$context['post_errors'][] = $emailErrors;

	if ($_POST['pwmain'] != $_POST['pwverify'])
		$context['post_errors'][] = $txt['registration_password_no_match'];

	if (!empty($context['post_errors']))
	{
		loadLanguage('Errors');
		$context['custom_error_title'] = $txt['subaccount_error'];
		// Reload the info into $context so we can put it back on the form... (just emails, really)
		$context['form_email'] = $_POST['email'];
		$context['sub_template'] = 'manage_subaccounts_split';
		$context['page_desc'] = $txt['subaccounts_split_desc'];
		$context['subaccount'] = array(
			'id' => $subaccount,
			'name' => $cur_profile['subaccounts'][$subaccount]['name'],
		);

		return;
	}


	// No errors, woo hooo... update the user...
	loadMemberData($subaccount, false, 'minimal');
	updateMemberData($subaccount, array('email_address' => $_POST['email'], 'is_shareable' => 0, 'passwd' => sha1(strtolower($user_profile[$subaccount]['member_name']) . un_htmlspecialchars($_POST['pwmain']))));

	// Remove them from the subaccounts table
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}subaccounts
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Delete the Cache for this user
	cache_put_data('user_subaccounts-' . $cur_profile['id_member'], null, 240);

	redirectexit('action=profile;area=managesubaccounts;u=' . $cur_profile['id_member']);
}

function SubAccountParent($memID)
{
	global $context, $txt, $cur_profile, $smcFunc, $sourcedir, $user_profile;

	// Here's what we'll do: verify a valid subaccount, update everyone's information, then switch to that subaccount (might change this later, but it makes sense)
	if (empty($_REQUEST['subaccount']))
	{
		$context['custom_error_title'] = $txt['subaccount_error'];
		$context['post_errors'][] = $txt['subaccount_not_selected'];
		return SubAccountBrowse($memID);
	}

	$subaccount = (int) $_REQUEST['subaccount'];

	// Let's do some checks first.
	// If this is an subaccount, it shouldn't even have subaccounts, but you never know...
	if (!empty($cur_profile['id_parent']) || !array_key_exists($subaccount, $cur_profile['subaccounts']))
		fatal_lang_error('cannot_delete_subaccount', false);

	if (!empty($cur_profile['subaccounts'][$subaccount]['shareable']) && $cur_profile['id_member'] != $cur_profile['subaccounts'][$subaccount]['shareable'])
		fatal_lang_error('cannot_delete_subaccount_shared', false);

	// Looks like we're good to go, let's do the function...
	$passwordError = null;
	if (!empty($_POST['submit']))
	{
		require_once($sourcedir . '/Subs-Auth.php');
		$_POST['pwmain'] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST['pwmain']));
		$_POST['pwverify'] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST['pwverify']));
		$passwordError = validatePassword($_POST['pwmain'], $cur_profile['subaccounts'][$subaccount]);
		$passwordError = $passwordError != null ? 'password_' . $passwordError : null;
		$passwordError = $_POST['pwmain'] != $_POST['pwverify'] ? 'bad_new_password' : $passwordError;
	}

	if (empty($_POST['submit']) || $passwordError != null)
	{
		if ($passwordError != null)
		{
			loadLanguage('Errors');
			$context['custom_error_title'] = $txt['subaccount_error'];
			$context['post_errors'][] = $txt['profile_error_' . $passwordError];
		}
		$context['sub_template'] = 'manage_subaccounts_reassign';
		$context['page_desc'] = $txt['subaccounts_reassign_desc'];
		$context['subaccount'] = array('id' => $subaccount, 'name' => $cur_profile['subaccounts'][$subaccount]['name']);
		return;
	}

	// Make sure they came from *somewhere*, have a session.
	checkSession('post');

	// Need to do three different things, first, the new parent can't be a subaccount of anyone
	// Remove them from the subaccounts table
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}subaccounts
		WHERE id_member = {int:user}',
		array(
			'user' => $subaccount,
		)
	);

	// Second, we need to convert everyone that has an id_parent of the current user to the new parent
	// Remove them from the subaccounts table
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}subaccounts
		SET id_parent = {int:new_parent}
		WHERE id_parent = {int:old_parent}',
		array(
			'new_parent' => $subaccount,
			'old_parent' => $cur_profile['id_member'],
		)
	);

	// Lastly, add an entry for the new member into the table
	$smcFunc['db_insert']('',
		'{db_prefix}subaccounts',
		array('id_member' => 'int', 'id_parent' => 'int'),
		array($cur_profile['id_member'], $subaccount),
		array('id_parent')
	);

	// Need to get the member data for the new parent for the password creation and set the proper info
	loadMemberData($subaccount, false, 'minimal');
	updateMemberData($subaccount, array('is_shareable' => 0, 'passwd' => sha1(strtolower($user_profile[$subaccount]['member_name']) . $_POST['pwmain'])));

	// And now redirect to the profile of the new parent (the subaccount screen no less)
	SwitchSubAccount('action=profile;area=managesubaccounts');
}

function SwitchSubAccount($location = '')
{
	global $smcFunc, $user_info, $sourcedir, $modSettings, $cookiename;

	// This attempts to set a new cookie with the subaccount id and the password.
	// Perhaps incorporating password checking would be good in this... future feature?
	// For now we'll rely on current logged in info and a valid session...
	checkSession('request');

	// Clean the variable, just in case...
	$_REQUEST['subaccount'] = !empty($_REQUEST['subaccount']) ? (int) $_REQUEST['subaccount'] : -1;

	// If the subaccount doesn't exist in the current users subaccount list, leave, NOW!
	if (!array_key_exists($_REQUEST['subaccount'], $user_info['subaccounts']))
		redirectexit(empty($location) ? (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SESSION['old_url']) : $location);

	// Get the information for this subaccount
	$request = $smcFunc['db_query']('', '
		SELECT id_member, passwd, password_salt, is_shareable
		FROM {db_prefix}members
		WHERE id_member = {int:to_switch}
		LIMIT 1',
		array(
			'to_switch' => $_REQUEST['subaccount'],
		)
	);
	$new_user = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Let's setup a new cookie then redirect to wherever we came from
	if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;i:\d{1,7};i:1;s:(0|128):"([a-fA-F0-9]{128})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
		list (, , $timeout) = @unserialize($_COOKIE[$cookiename]);
	elseif (isset($_SESSION['login_' . $cookiename]))
		list (, , $timeout) = @unserialize($_SESSION['login_' . $cookiename]);
	else
		trigger_error('SwitchSubAccount(): Cannot change subaccount without a session or cookie', E_USER_ERROR);

	// Store the timeout, so the same one is used for possibly too cookies
	$timeout -= time();

	require_once($sourcedir . '/Subs-Auth.php');
	setLoginCookie($timeout, $new_user['id_member'], hash_salt($new_user['passwd'], $new_user['password_salt']));

	// Create a new cookie so we know where we came from if we are switching to a shareable account
	if (!empty($new_user['is_shareable']))
	{
		// Since this probably won't be happening a lot, a small extra bit of overhead isn't going to kill us here
		$id_parent = !empty($user_info['id_parent']) ? $user_info['id_parent'] : $user_info['id'];

		$request = $smcFunc['db_query']('', '
			SELECT passwd, password_salt
			FROM {db_prefix}members
			WHERE id_member = {int:id_parent}',
			array(
				'id_parent' => $id_parent,
			)
		);
		$old_user = $smcFunc['db_fetch_assoc']($request);

		setParentCookie($timeout, $id_parent, sha1($old_user['passwd'] . $old_user['password_salt']));
	}
	// otherwise kill the parent cookie, don't really need it
	else
		setParentCookie(-3600, 0);

	// Update log_online
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}log_online
		SET id_member = {int:new_user}
		WHERE id_member = {int:user}',
		array(
			'new_user' => $new_user['id_member'],
			'user' => $user_info['id'],
		)
	);

	// You've logged in, haven't you?
	updateMemberData($new_user['id_member'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));

	redirectexit(empty($location) ? (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $_SESSION['old_url']) : $location);
}

function setParentCookie($cookie_length, $id, $password = '')
{
	global $cookiename, $boardurl, $modSettings;

	$subaccount_cookie = $cookiename . '_parent';

	// The cookie may already exist, and have been set with different options.
	$cookie_state = (empty($modSettings['localCookies']) ? 0 : 1) | (empty($modSettings['globalCookies']) ? 0 : 2);
	if (isset($_COOKIE[$subaccount_cookie]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$subaccount_cookie]) === 1)
	{
		$array = @unserialize($_COOKIE[$subaccount_cookie]);

		// Out with the old, in with the new!
		if (isset($array[3]) && $array[3] != $cookie_state)
		{
			$cookie_url = url_parts($array[3] & 1 > 0, $array[3] & 2 > 0);
			setcookie($subaccount_cookie, serialize(array(0, '', 0)), time() - 3600, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
		}
	}

	// Get the data and path to set it on.
	$data = serialize(empty($id) ? array(0, '', 0) : array($id, $password, time() + $cookie_length, $cookie_state));
	$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

	// Set the cookie, $_COOKIE, and session variable.
	setcookie($subaccount_cookie, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));

	// If subdomain-independent cookies are on, unset the subdomain-dependent cookie too.
	if (empty($id) && !empty($modSettings['globalCookies']))
		setcookie($subaccount_cookie, $data, time() + $cookie_length, $cookie_url[1], '', !empty($modSettings['secureCookies']));

	// Any alias URLs?  This is mainly for use with frames, etc.
	if (!empty($modSettings['forum_alias_urls']))
	{
		$aliases = explode(',', $modSettings['forum_alias_urls']);

		$temp = $boardurl;
		foreach ($aliases as $alias)
		{
			// Fake the $boardurl so we can set a different cookie.
			$alias = strtr(trim($alias), array('http://' => '', 'https://' => ''));
			$boardurl = 'http://' . $alias;

			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));

			if ($cookie_url[0] == '')
				$cookie_url[0] = strtok($alias, '/');

			setcookie($subaccount_cookie, $data, time() + $cookie_length, $cookie_url[1], $cookie_url[0], !empty($modSettings['secureCookies']));
		}

		$boardurl = $temp;
	}

	$_COOKIE[$subaccount_cookie] = $data;

	// Make sure the user logs in with a new session ID.
	if (!isset($_SESSION['login_' . $subaccount_cookie]) || $_SESSION['login_' . $subaccount_cookie] !== $data)
		$_SESSION['login_' . $subaccount_cookie] = $data;
}