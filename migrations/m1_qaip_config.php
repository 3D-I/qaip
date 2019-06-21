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
	public function effectively_installed()
	{
		return isset($this->config['qaip_css_center']);
	}

	static public function depends_on()
	{
		return ['\phpbb\db\migration\data\v32x\v325'];
	}

	public function update_data()
	{
		return [
			['config.add', ['qaip_css_center', '0']],
		];
	}
}
