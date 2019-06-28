<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, 3Di, https://phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

/*
 * Some characters you may want to copy&paste:
 * ’ » “ ” …
 */
$lang = array_merge($lang,[
	'QAIP_SETTINGS'				=> '3Di - Quote Attachments Img in Posts',
	'QAIP_CSS_RESIZER'			=> 'Use CSS to center quoted images',
	'QAIP_CSS_RESIZER_EXPLAIN'	=> 'Use it if you don’t have a Center BBcode or a CCS rule installed. Works only for images posted within the IMG bbcode tags.',
]);
