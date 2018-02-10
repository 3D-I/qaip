<?php
/**
 *
 * Quote attachments in posts. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2017, 3Di, http://3di.space/32/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * Some code, adapted and improved, inspired by Татьяна5's editor_of_attachments.
 * CSS resizer, adapted and improved, inspired by quotethumbnails of HiFiKabin.
 * ACP posting setting, adapted, inspired by Lightbox of VSE.
 */

namespace threedi\qaip\event;

/**
 * @ignore
 */
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

	/** @var \phpbb\user */
	protected $user;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth					$auth
	* @param \phpbb\config\config				$config
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\template\template			$template			Template object
	* @param \phpbb\user						$user
	*/

	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\template\template $template, \phpbb\user $user)
	{
		$this->auth		=	$auth;
		$this->config	=	$config;
		$this->db		=	$db;
		$this->template	=	$template;
		$this->user		=	$user;
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
		return array(
			'core.page_header_after'				=>	'qaip_template_switch',
			'core.acp_board_config_edit_add'		=>	'add_qaip_acp_config',
			'core.posting_modify_template_vars'		=>	'quote_img_in_posts',
		);
	}

	/**
	 * Template switch over all
	 *
	 * @event core.page_header_after
	 */
	public function qaip_template_switch($event)
	{
		$this->template->assign_vars(array(
			'S_QAIP_CENTER'	=>	($this->config['qaip_css_center']) ? true : false,
		));
	}

	/**
	 * Add QAIP settings to the ACP
	 *
	 * @event core.acp_board_config_edit_add
	 */
	public function add_qaip_acp_config($event)
	{
		if ($event['mode'] === 'post' && array_key_exists('legend3', $event['display_vars']['vars']))
		{
			/*
			 * Load language file only when necessary
			 */
			$this->user->add_lang_ext('threedi/qaip', 'common');

			$display_vars = $event['display_vars'];
			/*
			 * Set configs
			 */
			$my_config_vars = array(
				'legend_qaip'		=> 'QAIP_SETTINGS',
				'qaip_css_center'	=> array('lang' => 'QAIP_CSS_RESIZER', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true),
			);
			/*
			 * Validate configs
			 */
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $my_config_vars, array('before' => 'legend3'));

			$event['display_vars'] = $display_vars;
		}
	}

	/**
	 * This event allows you to modify template variables for the posting screen
	 *
	 * @event core.posting_modify_template_vars
	 */
	public function quote_img_in_posts($event)
	{
		$page_data		=	$event['page_data'];
		$message_parser	=	$event['message_parser'];
		$post_data		=	$event['post_data'];
		$mode			=	$event['mode'];
		$post_id		=	$event['post_id'];
		$forum_id		=	$event['forum_id'];
		$submit			=	$event['submit'];
		$preview		=	$event['preview'];
		$refresh		=	$event['refresh'];

		if ($mode == 'quote' && !$submit && !$preview && !$refresh)
		{
			/**
			 * Is a topic poll?
			 */
			if (sizeof($post_data['poll_options']) || !empty($post_data['poll_title']))
			{
				$post_data_poll = $post_data['post_text'];
				/**
				* Taking care of phpBB versions
				*/
				if ( phpbb_version_compare(PHPBB_VERSION, '3.2.0-dev', '>=') )
				{
				/**
				 * phpBB 3.2 - stressing the ending '[/quote]\n'
				 */
				$post_data_poll = substr_replace( $post_data_poll, "[/quote]", - 10 );
				}
				else
				{
				/**
				 * phpBB 3.1 - stressing the ending '[/quote]\n'
				 */
				$post_data_poll = substr_replace( $post_data_poll, "[/quote]", - 9 );
				}
				/**
				 * We need to add add a trailing space at the end of the stripped parsed message
				 * to avoid a potential bug, urls/smilies not correctly parsed after it
				 * (see phpBB message parser code)
				 */
				$poll_space = " ";
				$message_parser->message = "{$post_data_poll}{$poll_space}";
			}
			/**
			* Are BBcodes allowed?
			*/
			if ($this->config['allow_bbcode'])
			{
				$img_open_tag	= ($this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id)) ? '[img]' : ' ';

				$img_close_tag	= ($this->auth->acl_get('f_bbcode', $forum_id) && $this->auth->acl_get('f_img', $forum_id)) ? '[/img]' : ' ';

				/*
				 * Let's extend the functionalities now
				 */
				$mode_view		= '&mode=view';
				$url_open_tag	= '[url=';
				$url_middle_tag	= ']';
				$url_close_tag	= '[/url]';

				/**
				 * Array's creation
				 */
				$attach_in_quote = array();

				/**
				 * We have to take care of different versions here while stripping the ending '[/quote]\n'
				 */
				if ( phpbb_version_compare(PHPBB_VERSION, '3.2.0-dev', '>=') )
				{
					$message_parser->message = substr($message_parser->message, 0, strlen($message_parser->message) - 10);
				}
				else
				{
					$message_parser->message = substr($message_parser->message, 0, strlen($message_parser->message) - 9);
				}
				/**
				 * Extracts the real filename and extension and put it to the array
				 */
				preg_match_all('/\[attachment=\d+\](.*)\[\/attachment\]/U', $message_parser->message, $attach_in_quote);

				$sql_attach = 'SELECT attach_id, post_msg_id, real_filename, mimetype
					FROM ' . ATTACHMENTS_TABLE . '
					WHERE post_msg_id = ' . $post_id . '
					ORDER BY attach_id ASC';

				$result_attach = $this->db->sql_query($sql_attach);

				/**
				 * Transform quoted attached images as images again
				 * No matters if they are links, placed inline or not, thumbnailed or not
				 */
				while ( $attach_row = $this->db->sql_fetchrow($result_attach) )
				{
					if ( in_array($attach_row['real_filename'], $attach_in_quote[1]) )
					{
						if (strpos($attach_row['mimetype'], 'image/') !== false)
						{
							$message_parser->message = preg_replace('/\[attachment=\d+\]' . preg_quote($attach_row['real_filename']) . '\[\/attachment\]/', generate_board_url() . '/download/file.php?id=' . (int) $attach_row['attach_id'], $message_parser->message);
						}
						/**
						 * Fixes a potential issue, are there files with the same real filename?
						 */
						$key_attach = array_search($attach_row['real_filename'], $attach_in_quote[1]);

						if ($key_attach !== false)
						{
							/**
							 * Destroy a single element of an array.
							 */
							unset($attach_in_quote[1][$key_attach]);
						}
					}
					if (strpos($attach_row['mimetype'], 'image/') !== false)
					{
						$img_link = generate_board_url() . '/download/file.php?id=' . (int) $attach_row['attach_id'];

						/**
						 * Put the quoted image on a new line after its real filename
						 * using a faster strings concatenation.
						 */
						$message_parser->message .= "\n" . "{$url_open_tag}{$img_link}{$mode_view}{$url_middle_tag}{$img_open_tag}{$img_link}{$img_close_tag}{$url_close_tag}";
					}
				}
				/**
				 * Free results
				 */
				$this->db->sql_freeresult($result_attach);

				/**
				 * Destroy variable and its associated data.
				 */
				unset($attach_row);

				/**
				 * We need to add the closing quote tag previously stripped away and a CR.
				 * We add a trailing space also, at the end of the ending quote tag, to avoid a potential
				 * bug, urls/smilies not correctly parsed after it (see phpBB message parser code)
				 */
				$end_quote_sp = "[/quote] ";
				$end_quote_cr = "\n";
				$message_parser->message .= "{$end_quote_sp}{$end_quote_cr}";

				$post_data['post_text'] = $message_parser->message;

				$page_data = array_merge($page_data, array('MESSAGE' => $post_data['post_text']));

				$event['page_data'] = $page_data;
			}
		}
	}
}
