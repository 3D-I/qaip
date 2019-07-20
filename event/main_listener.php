<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019, 3Di, https://phpbbstudio.com
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace threedi\qaip\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Quote attachments in posts Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/* @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\language\language */
	protected $language;

	/** @var string Topics table */
	protected $attachments_table;

	/* @var string phpBB root path */
	protected $root_path;

	/**
	 * Constructor
	 *
	 * @param  \phpbb\auth\auth						$auth					Auth object
	 * @param  \phpbb\config\config					$config					Config object
	 * @param  \phpbb\db\driver\driver_interface	$db						Database object
	 * @param  \phpbb\request\request				$request				Request object
	 * @param  \phpbb\template\template				$template				Template object
	 * @param  \phpbb\language\language				$language				Language object
	 * @param  string								$attachments_table		Attachments table
	 * @param  string								$root_path				phpBB root path
	 * @return void
	 * @access public
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\language\language $language,
		$attachments_table,
		$root_path
	)
	{
		$this->auth					= $auth;
		$this->config				= $config;
		$this->db					= $db;
		$this->request				= $request;
		$this->template				= $template;
		$this->language				= $language;

		$this->attachments_table	= $attachments_table;
		$this->root_path			= $root_path;
	}

	/**
	 * Assign functions defined in this class to event listeners in the core
	 *
	 * @return array
	 * @static
	 * @access public
	 */
	static public function getSubscribedEvents()
	{
		return [
			'core.page_header_after'				=> 'qaip_template_switch',
			'core.acp_board_config_edit_add'		=> 'qaip_acp_config',
			'core.posting_modify_post_data'			=> 'qaip_quote_img_in_posts',
		];
	}

	/**
	 * Template switch over all
	 *
	 * @event core.page_header_after
	 * @return void
	 * @access public
	 */
	public function qaip_template_switch($event)
	{
		$this->template->assign_vars([
			'S_QAIP_CENTER'	=> (bool) $this->config['qaip_css_center'],
			'S_QAIP'		=> true,
		]);
	}

	/**
	 * Add QAIP settings to the ACP
	 *
	 * @event core.acp_board_config_edit_add
	 * @param  \phpbb\event\data	$event		The event object
	 * @return void
	 * @access public
	 */
	public function qaip_acp_config($event)
	{
		if ($event['mode'] === 'post' && array_key_exists('legend1', $event['display_vars']['vars']))
		{
			/* Load our language file only if necessary */
			$this->language->add_lang('common', 'threedi/qaip');

			$display_vars = $event['display_vars'];

			/* Set configs */
			$qaip_config_vars = [
				'legend_qaip'		=> 'QAIP_SETTINGS',
				'qaip_css_center'	=> ['lang' => 'QAIP_CSS_RESIZER', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true],
			];

			/* Validate configs */
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $qaip_config_vars, ['before' => 'legend1']);

			$event['display_vars'] = $display_vars;
		}
	}

	/**
	 * Brings back to life quoted attachment's image(s)
	 * No matters if they are links, placed inline or not, thumbnailed or not
	 *
	 * @event core.posting_modify_post_data
	 * @param  \phpbb\event\data	$event		The event object
	 * @return void
	 * @access public
	 */
	public function qaip_quote_img_in_posts($event)
	{
		$mode = $event['mode'];
		$data = $event['post_data'];

		$post_id	= (int) $event['post_id'];
		$forum_id	= (int) $event['forum_id'];

		$file_add	= $this->request->is_set_post('add_file');
		$file_del	= $this->request->is_set_post('delete_file');
		$preview	= $this->request->is_set_post('preview');
		$save		= $this->request->is_set_post('save');
		$load		= $this->request->is_set_post('load');
		$post		= $this->request->is_set_post('post');

		if ($mode === 'quote' && !$post && !$load && !$save && !$preview && !$file_add && !$file_del)
		{
			/* Are BBcodes allowed? */
			if ($this->config['allow_bbcode'])
			{
				$text = $data['post_text'];

				$rows = array_filter($this->qaip_attach_rows($post_id), function($row) {
					return strpos($row['mimetype'], 'image/') !== false;
				});

				/* Check if the user is allowed to use the IMG bbcode */
				$img = [
					'open'	=> $this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id) ? '[img]' : '',
					'close'	=> $this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id) ? '[/img]' : '',
				];

				/* Replace INLINE images in the same place where they were placed */
				if (!empty($rows))
				{
					$preg = '/<ATTACHMENT filename="[^"]*?" index="(' . implode('|', array_keys($rows)) . ')">.*?<\/ATTACHMENT>/';

					$text = preg_replace_callback(
						$preg,
						function($match) use (&$rows, $img) {
							$id = (int) $match[1];

							/* Use relative path for the sake of future's proof */
							$link = $this->root_path . 'download/file.php?id=' . (int) $rows[$id]['attach_id'];

							unset($rows[$id]);

							return "[url={$link}&mode=view]{$img['open']}{$link}{$img['close']}[/url]";
						},
						$text
					);
				}
				/* Replace NOT-INLINE images each on a new line after the post text */
				if (!empty($rows))
				{
					foreach ($rows as $row)
					{
						/* Use relative path for the sake of future's proof */
						$link = $this->root_path . 'download/file.php?id=' . (int) $row['attach_id'];

						$text .= "\n[url={$link}&mode=view]{$img['open']}{$link}{$img['close']}[/url]\n";
					}
				}

				$data['post_text']	= $text;
				$event['post_data']	= $data;
			}
		}
	}

	/**
	 * Retrieves the post's attachment data.
	 *
	 * @param  int		$post_id		The post identifier
	 * @return array	$attach_rows	Array with attachments' data, empty array otherwise
	 * @access protected
	 */
	protected function qaip_attach_rows($post_id)
	{
		$attach_rows = [];

		$sql_attach = 'SELECT attach_id, real_filename, mimetype
			FROM ' . $this->attachments_table . '
			WHERE post_msg_id = ' . (int) $post_id . '
			ORDER BY attach_id DESC';
		$result_attach = $this->db->sql_query($sql_attach);
		$attach_rows = $this->db->sql_fetchrowset($result_attach);
		$this->db->sql_freeresult($result_attach);

		return $attach_rows;
	}
}
