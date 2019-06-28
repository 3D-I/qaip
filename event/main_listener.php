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
		\phpbb\template\template $template,
		\phpbb\language\language $language,
		$attachments_table,
		$root_path
	)
	{
		$this->auth					= $auth;
		$this->config				= $config;
		$this->db					= $db;
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
			'core.posting_modify_template_vars'		=> 'qaip_quote_img_in_posts',
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
	 *
	 * @event core.posting_modify_template_vars
	 * @param  \phpbb\event\data	$event		The event object
	 * @return void
	 * @access public
	 */
	public function qaip_quote_img_in_posts($event)
	{
		$page_data		= $event['page_data'];
		$message_parser	= $event['message_parser'];
		$post_data		= $event['post_data'];
		$mode			= $event['mode'];
		$post_id		= (int) $event['post_id'];
		$forum_id		= (int) $event['forum_id'];
		$submit			= $event['submit'];
		$preview		= $event['preview'];
		$refresh		= $event['refresh'];

		if ($mode == 'quote' && !$submit && !$preview && !$refresh)
		{
			/* Is it a topic poll? */
			if (count($post_data['poll_options']) || !empty($post_data['poll_title']))
			{
				$post_data_poll = $post_data['post_text'];

				/* Stripping the ending '[/quote]\n' */
				$post_data_poll = substr_replace($post_data_poll, "[/quote]", - 10);
				$message_parser->message = "{$post_data_poll}";
			}

			/* Are BBcodes allowed? */
			if ($this->config['allow_bbcode'])
			{
				$img_open_tag	= ($this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id)) ? '[img]' : '';
				$img_close_tag	= ($this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id)) ? '[/img]' : '';

				/* Stripping the ending '[/quote]\n' */
				$message_parser->message = substr($message_parser->message, 0, strlen($message_parser->message) - 10);

				/* Retrieve the necessary data to work with */
				$attach_rows = $this->qaip_attach_rows($post_id);

				/**
				 * Transform quoted attached images as images again
				 * No matters if they are links, placed inline or not, thumbnailed or not
				 */
				if (count($attach_rows))
				{
					foreach ($attach_rows as $attach_row)
					{
						/* Use relative path for the sake of future's proof */
						$img_link = $this->root_path . 'download/file.php?id=' . (int) $attach_row['attach_id'];

						/* Only images */
						if (strpos($attach_row['mimetype'], 'image/') !== false)
						{
							/* If the attachments aren't INLINE there aren't filenames placed in the "post_text" */
							if (strpos($message_parser->message, $attach_row['real_filename']) === false)
							{
								/* Put the quoted image(s) each on a new line after the post text */
								$message_parser->message .= "\n[url={$img_link}&mode=view]{$img_open_tag}{$img_link}{$img_close_tag}[/url]";
							}
							else
							{
								/* Replace missing quoted images in the same place they were put INLINE */
								$message_parser->message = str_replace(
									$attach_row['real_filename'],
									"[url={$img_link}&mode=view]{$img_open_tag}{$img_link}{$img_close_tag}[/url]",
									$message_parser->message
								);
							}
						}
					}
				}

				/* Destroy array and its associated data */
				unset($attach_rows);

				/**
				 * Add back the closing quote tag previously stripped away.
				 * Basically close back the opened quote prior to send it back to the parser.
				 */
				$message_parser->message .= '[/quote]';

				$post_data['post_text'] = $message_parser->message;

				$page_data = array_merge($page_data, ['MESSAGE' => $post_data['post_text']]);

				$event['page_data'] = $page_data;
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

		$sql_attach = 'SELECT attach_id, post_msg_id, real_filename, mimetype
			FROM ' . $this->attachments_table . '
			WHERE post_msg_id = ' . (int) $post_id . '
			ORDER BY attach_id DESC';
		$result_attach = $this->db->sql_query($sql_attach);
		$attach_rows = $this->db->sql_fetchrowset($result_attach);
		$this->db->sql_freeresult($result_attach);

		return $attach_rows;
	}
}
