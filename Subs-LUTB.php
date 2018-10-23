<?php
/********************************************************************************
* Subs-LUTB.php - Functions for the List Users in Topics and Boards mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/********************************************************************************
* Functions that take over handling of viewing boards, messages and posts:
**********************************************************************************/
function LUTB_BoardIndex()
{
	global $context, $modSettings;
	BoardIndex();
	$context['users_online'] = !empty($context['users_online']) && allowedTo('who_view') && !empty($modSettings['who_enabled']);
}

function LUTB_MessageIndex()
{
	global $settings, $modSettings, $context, $txt;

	loadLanguage('LUTB');
	$settings['display_who_viewing'] = !empty($settings['display_who_viewing']) && !empty($modSettings['who_enabled']) && allowedTo('view_users_in_board') ? $settings['display_who_viewing'] : 0;
	$context['LUTB'] = $txt['users_browsing_board'];
	if ($settings['display_who_viewing'])
		add_integration_function('integrate_buffer', 'LUTB_Buffer', false);
	MessageIndex();
}

function LUTB_Display()
{
	global $settings, $modSettings, $context, $txt;

	loadLanguage('LUTB');
	$settings['display_who_viewing'] = !empty($settings['display_who_viewing']) && !empty($modSettings['who_enabled']) && allowedTo('view_users_in_topic') ? $settings['display_who_viewing'] : 0;
	$context['LUTB'] = $txt['users_browsing_topic'];
	if ($settings['display_who_viewing'])
		add_integration_function('integrate_buffer', 'LUTB_Buffer', false);
	Display();
}

/********************************************************************************
* Admin functions
**********************************************************************************/
function LUTB_Permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	loadLanguage('LUTB');
	$temp = array();
	foreach ($permissionList['membergroup'] as $id => $arr)
	{
		$temp[$id] = $arr;
		if ($id == 'who_view')
		{
			$temp['view_users_in_board'] = array(false, 'general', 'view_basic_info');
			$temp['view_users_in_topic'] = array(false, 'general', 'view_basic_info');
		}
	}
	$permissionList['membergroup'] = $temp;
}

function LUTB_Admin(&$admin_areas)
{
	global $txt;
	$admin_areas['config']['areas']['featuresettings']['subsections']['who'] = array($txt['who_title']);
}

function LUTB_Basic(&$config_vars)
{
	foreach ($config_vars as $id => $var)
	{
		if (is_array($var) && ($var[1] == 'who_enabled' || $var[1] == 'lastActive'))
		{
			unset($config_vars[$id]);
			if (isset($config_vars[$id + 1]) && !is_array($config_vars[$id + 1]) && $config_vars[$id + 1] == '')
				unset($config_vars[$id + 1]);
		}
	}
}

function LUTB_Settings($return_config = false)
{
	global $txt, $scripturl, $context, $smcFunc, $modSettings;

	// Load language and make adjustments:
	loadLanguage('ManagePermissions');
	loadLanguage('Themes');
	loadLanguage('LUTB');
	$txt['who_themes'] = $txt['who_title'] . ': ' . $txt['who_themes'];
	$txt['lastActive'] .= $txt['who_view_sub'];
	$txt['who_permissions'] = $txt['who_title'] . ': ' . $txt['permissionname_manage_permissions'];

	// Setup the config options:
	$config_vars = array(
		// Users online?
		array('check', 'who_enabled'),
		array('int', 'lastActive'),
		array('check', 'who_view_no_colored'),
		array('check', 'who_placement_top'),
		//'',
		// Membergroups with permission to see other users online:
		array('title', 'who_permissions'),
		array('permissions', 'who_view'),
		array('permissions', 'view_users_in_board'),
		array('permissions', 'view_users_in_topic'),
		//'',
		// Theme settings:
		array('title', 'who_themes'),
	);

	// Get theme names and current "display_who_viewing" setting per theme:
	$request = $smcFunc['db_query']('', '
		SELECT a.id_theme, a.value AS theme_name, b.value AS display_who_viewing
		FROM {db_prefix}themes AS a
			LEFT JOIN {db_prefix}themes AS b ON (a.id_theme = b.id_theme AND a.id_member = b.id_member AND b.variable = {string:display_who_viewing})
		WHERE a.variable = {string:theme_name}
			AND a.id_member = {int:id_member}',
		array(
			'theme_name' => 'name',
			'display_who_viewing' => 'display_who_viewing',
			'id_member' => 0,
		)
	);
	$themes = array();
	$options = array(
		0 => $txt['who_display_viewing_off'],
		1 => $txt['who_display_viewing_numbers'],
		2 => $txt['who_display_viewing_names']
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		$themes[$row['id_theme']] = $theme_var = 'display_who_viewing_' . $row['id_theme'];
		$modSettings[$theme_var] = $row['display_who_viewing'];
		$config_vars[$theme_var] = array('select', $theme_var, $options);
		$txt[$theme_var] = $row['theme_name'];
	}
	$smcFunc['db_free_result']($request);

	// Did someone request the config variables?  If so, return them!
	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Save "display_who_viewing" setting per theme:
		foreach ($themes as $id_theme => $theme_var)
		{
			// Get rid of the current setting:
			$smcFunc['db_query']('', '
				DELETE FROM {db_prefix}themes
				WHERE id_member = {int:id_member}
					AND variable = {string:display_who_viewing}
					AND id_theme = {int:id_theme}',
				array(
					'display_who_viewing' => 'display_who_viewing',
					'id_member' => 0,
					'id_theme' => $id_theme
				)
			);
			unset($config_vars[$theme_var]);

			// If setting is non-zero, then record setting in database:
			if (!empty($_POST[$theme_var]))
			{
				$smcFunc['db_insert']('',
					'{db_prefix}themes',
					array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string', 'value' => 'string',),
					array($id_theme, 0, 'display_who_viewing', $_POST[$theme_var]),
					array('id_theme')
				);
			}
		}

		// Save remaining configuration variables:
		saveDBSettings($config_vars);
		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=who');
	}

	// Prepare to show the config options:
	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;sa=who;save';
	$context['settings_title'] = $txt['who_title'];
	prepareDBSettingContext($config_vars);
}

/********************************************************************************
* Buffer manipulation functions
**********************************************************************************/
function LUTB_Buffer($buffer)
{
	global $context, $txt, $modSettings, $forum_version, $topic;

	$pattern = '#<(p|div|tr|td)([^\>]*?)(class=\"([^\>\"]*?)|id=\")(whos_viewing|whoisviewing)([^\>^\"]*?)\"([^\>]*?)>(<td([^\>]*?)>)?(.*?)</(p|div|td|tr)>#is';
	if (preg_match($pattern, $buffer, $matches))
	{
		// Construct the new "Who's Online" HTML fragment:
		$smf20 = substr($forum_version, 0, 7) == "SMF 2.0";
		$replace = $matches[0];
		$fragment = (($is_top = !empty($modSettings['who_placement_top'])) ? '<br />' : '') .
			'<div class="cat_bar"><h3 class="catbg">' . $context['LUTB'] . ':</h3></div>' .
			'<div class="information">' . $matches[10] . '</div>';

		// Where, oh, where to insert the new fragment?
		if (!$is_top)
			$find = $smf20 ? '(moderationbuttons|topic_icons)' : '(description_board|msg' . $context['first_message'] . ')';
		else
			$find = $smf20 ? '(messageindex|forumposts)' : (empty($topic) ? 'navigate_section' : 'whoisviewing_bottom');
		
		// If found, remove old fragment and place new fragment after found text:
		$pattern = '#(<(a|p|div|tr)([^\>]*)(class=\"([^\>\"]*?)' . $find . '([^\>\"]*?)\"|id=\"' . $find . '")([^\>]*)\>)#is';
		if (preg_match($pattern, $buffer, $matches))
			$buffer = str_replace($matches[0], $fragment . $matches[0], str_replace($replace, '', $buffer));
	}
	return $buffer;
}

?>