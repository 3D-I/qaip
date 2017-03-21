<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, 3Di, http://3di.space/32/
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
		if ( $this->phpbb_requirements() )
		{
			return true;
		}
		else
		{
			$this->verbose_it();
		}
	}

	/**
	 * Check phpBB compatibility
	 * Requires phpBB 3.1.6 or greater
	 *
	 * @return bool
	 */
	protected function phpbb_requirements()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.1.6', '>=');
	}

	/**
	 * Let's tell the user what exactly is going on on failure, provides a backlink.
	 *
	 * Using the User Object for the BC's sake.
	 */
	protected function verbose_it()
	{
		$this->container->get('user')->add_lang_ext('threedi/qaip', 'ext_require');

		trigger_error($this->container->get('user')->lang['EXTENSION_REQUIREMENTS_NOTICE'] . adm_back_link(append_sid('index.' . $this->container->getParameter('core.php_ext'), 'i=acp_extensions&amp;mode=main')), E_USER_WARNING);
	}
}
