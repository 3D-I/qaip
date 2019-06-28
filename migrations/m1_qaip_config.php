<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, 3Di, https://phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace threedi\qaip\migrations;

class m1_qaip_config extends \phpbb\db\migration\migration
{
	/**
	 * Check if the migration is effectively installed (entirely optional).
	 *
	 * @return bool 		True if this migration is installed, False if this migration is not installed
	 * @access public
	 */
	public function effectively_installed()
	{
		return isset($this->config['qaip_css_center']);
	}

	/**
	 * Assign migration file dependencies for this migration.
	 *
	 * @return array		Array of migration files
	 * @access public
	 * @static
	 */
	static public function depends_on()
	{
		return ['\phpbb\db\migration\data\v32x\v325'];
	}

	/**
	 * Update data stored in the database during extension installation.
	 *
	 * @return	array	Array of data update instructions
	 * @access public
	 */
	public function update_data()
	{
		return [
			['config.add', ['qaip_css_center', 0]],
		];
	}
}
