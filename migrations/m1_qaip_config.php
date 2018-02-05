<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, 3Di, http://3di.space/32/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace threedi\qaip\migrations;

class m1_qaip_config extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['threedi_qaip']);
	}

	static public function depends_on()
	{
		return array('\phpbb\db\migration\data\v31x\v316');
	}

	public function update_data()
	{
		return array(
			array('config.add', array('qaip_css_center', '0')),
		);
	}
}
