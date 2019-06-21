<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, 3Di, https://phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace threedi\qaip;

/**
 * Quote attachments in posts Extension base
 */
class ext extends \phpbb\extension\base
{
	/**
	 * Check whether or not the extension can be enabled.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		$is_enableable = true;

		$user = $this->container->get('user');

		$user->add_lang_ext('threedi/qaip', 'ext_require');

		$lang = $user->lang;

		if (!(phpbb_version_compare(PHPBB_VERSION, '3.2.5', '>=') && phpbb_version_compare(PHPBB_VERSION, '3.3.0@dev', '<')))
		{
			/**
			 * Despite it seems wrong that's the right approach and not an error in coding.
			 * Done this way in order to avoid PHP errors like
			 * "Indirect modification of overloaded property phpbb/user::$lang has no effect"
			 * or " Can't use method return value in write context" depending on the use case.
			 * Discussed here: https://www.phpbb.com/community/viewtopic.php?p=14724151#p14724151
			*/
			$lang['EXTENSION_NOT_ENABLEABLE'] .= '<br>' . $user->lang('ERROR_PHPBB_VERSION', '3.2.5', '3.3.0@dev');

			$is_enableable = false;
		}

		$user->lang = $lang;

		return $is_enableable;
	}
}
