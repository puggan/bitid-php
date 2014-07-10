<?php
/**
 * @package Bitid Authentication
 * @author Puggan
 * @version 0.0.4-20140710
 */
/*
Plugin Name: Bitid Authentication
Description: Bitid Authentication, extends wordpress default authentication with the bitid-protocol
Version: 0.0.4-20140710
Author: Puggan
Author URI: http://blog.puggan.se
Text Domain: bitid-authentication
*/

DEFINE("BITID_AUTHENTICATION_PLUGIN_VERSION", '0.0.3a');

	require_once("bitid.php");

	register_activation_hook( __FILE__, 'bitid_install' );

	add_action( 'plugins_loaded', 'bitid_update_db_check' );
	add_action( 'login_enqueue_scripts', 'bitid_login_script' );
	add_action( 'wp_logout', 'bitid_exit');
	add_action( 'init', 'bitid_init');
	add_action( 'admin_menu', 'bitid_menu' );
	add_action( 'template_redirect', 'bitid_callback_test' );
	add_action( 'wp_ajax_nopriv_bitid', 'bitid_ajax' );
	add_action( 'plugins_loaded', 'bitid_load_translation' );

	add_filter( 'login_message', 'bitid_login_header' );

	function bitid_init()
	{
		$session_id = session_id();

		if(!$session_id)
		{
			session_start();
			$session_id = session_id();
		}
	}

	function bitid_load_translation()
	{
		$plugin_dir = basename(dirname(__FILE__));
		load_plugin_textdomain( 'bitid-authentication', FALSE, $plugin_dir );
	}

	/* check version on load */
	function bitid_update_db_check()
	{
		if(get_site_option( "bitid_plugin_version") !=  BITID_AUTHENTICATION_PLUGIN_VERSION )
		{
			bitid_install();
		}
	}

	/* install plugin, add all tables */
	function bitid_install()
	{
		$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";
		$table_name_links = "{$GLOBALS['wpdb']->prefix}bitid_userlink";
		$table_name_users = "{$GLOBALS['wpdb']->prefix}users";

		$create_table_nonce = <<<SQL_BLOCK
CREATE TABLE {$table_name_nonce} (
  nonce VARCHAR(32) NOT NULL,
  address VARCHAR(34) DEFAULT NULL,
  session_id VARCHAR(40) NOT NULL,
  birth DATETIME NOT NULL,
  PRIMARY KEY (nonce),
  KEY (birth)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_bin
SQL_BLOCK;

		$create_table_links = <<<SQL_BLOCK
CREATE TABLE {$table_name_links} (
  user_id BIGINT(20) UNSIGNED NOT NULL,
  address VARCHAR(34) COLLATE utf8_bin DEFAULT NULL,
  birth DATETIME NOT NULL,
  pulse DATETIME NOT NULL,
  PRIMARY KEY (address),
  KEY (user_id),
  KEY (birth),
  FOREIGN KEY (user_id) REFERENCES {$table_name_users}(ID)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_bin
SQL_BLOCK;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $create_table_nonce );
		//error dbDelta( $create_table_links );

		add_option( "bitid_plugin_version", BITCOIN_PARTIETS_FORMULAR_VERSION );
	}

	function bitid_menu()
	{
// 		add_options_page( 'Bitid Options', 'Bitid', 'edit_users', 'bitid-authentication', 'bitid_option_page' );
		add_users_page(
			_x('My Bitid', 'page_title', 'bitid-authentication'),
			_x('Bitid', 'menu_name', 'bitid-authentication'),
			'read',
			'my-bitid',
			'bitid_my_option_page'
		);
	}

	function bitid_option_page()
	{
		echo "<h1>bitid_option_page</h1>";
		return "<h2>bitid_option_page()</h2>";
	}

	function bitid_my_option_page()
	{
		$user_id = get_current_user_id();
		if(!$user_id)
		{
			return;
		}

		$addresses = bitid_list_users_addresses($user_id);

		$action = "";
		if(isset($_REQUEST['action2']) AND $_REQUEST['action2'] != '' AND $_REQUEST['action2'] != -1)
		{
			$action = $_REQUEST['action2'];
		}
		if(isset($_REQUEST['action']) AND $_REQUEST['action'] != '' AND $_REQUEST['action'] != -1)
		{
			$action = $_REQUEST['action'];
		}
		if($action)
		{
			switch($action)
			{
				case 'add':
				{
					if(isset($_POST['address']))
					{
						$address = $_POST['address'];
						$default_address = $address;
						$bitid = new BitID();
						if($bitid->isAddressValid($address, FALSE) OR $bitid->isAddressValid($address, TRUE))
						{
							$userlink_row = array();
							$userlink_row['user_id'] = $user_id;
							$userlink_row['address'] = $address;
							$userlink_row['birth'] = current_time('mysql');

							$table_name_links = "{$GLOBALS['wpdb']->prefix}bitid_userlink";

							$db_result = $GLOBALS['wpdb']->insert( $table_name_links, $userlink_row );

							if($db_result)
							{
								echo bitid_admin_notice(sprintf(__("The address '%s' is now linked to your account.", 'bitid-authentication'), $address));

								$addresses = bitid_list_users_addresses($user_id);
							}
							else
							{
								echo bitid_admin_notice(sprintf(__("Failed to link address '%s' to your account.", 'bitid-authentication'), $address), 'error');
							}
						}
						else
						{
							echo bitid_admin_notice(sprintf(__("The address '%s' isn't valid.", 'bitid-authentication'), $address), 'error');
						}
					}
					else
					{
						$default_address = (string) @$_REQUEST['address'];
					}

					$legend_title = _x("Add bitid-address", 'legend_title', 'bitid-authentication');
					$label_title = _x("Bitid-address", 'input_label', 'bitid-authentication');
					$button_title = _x("Link to my account", 'button', 'bitid-authentication');

					echo <<<HTML_BLOCK
<form action='?page={$_REQUEST['page']}&action=add' method='post'>
	<fieldset style='border: solid black 2px; width: 40em; padding: 10px; margin: 10px;'>
		<legend style='font-size: larger;'>
			{$legend_title}
		</legend>
		<div class='fieldset_content'>
			<label>
				<span style='width: 10em; display: inline-block;'>
					{$label_title}:
				</span>
				<input type='text' name='address' value='{$default_address}' style='width: 25em;'/>
			</label>
			<br />
			<input type='submit' value='{$button_title}' style='margin-left: 10em;' />
		</div>
	</fieldset>
	<p>Comming soon: adding by QR-code</p>
</form>
HTML_BLOCK;
					break;
				}

				case 'delete':
				{
					$found_addresses = array();
					$try_addresses = array();
					$deleted_addresses = array();
					$failed_addresses = array();

					if(isset($_REQUEST['bitid_row']))
					{
						foreach($_REQUEST['bitid_row'] as $address)
						{
							$found_addresses[$address] = $address;
						}
					}
					else if(isset($_REQUEST['address']))
					{
						$address = $_REQUEST['address'];
						$found_addresses[$address] = $address;
					}

					if(!$found_addresses)
					{
						if($_POST)
						{
							echo bitid_admin_notice(__("Select some rows before asking to delete them", 'bitid-authentication'), 'error');
							break;
						}
						else
						{
							echo bitid_admin_notice(sprintf(__("Missing paramater '%s'", 'bitid-authentication'), 'address'), 'error');
							break;
						}
					}

					foreach($addresses as $current_adress)
					{
						$address = $current_adress['address'];
						if(isset($found_addresses[$address]))
						{
							$try_addresses[$address] = $address;
							unset($found_addresses[$address]);

							if(!$found_addresses)
							{
								break;
							}
						}
					}

					if($found_addresses)
					{
						echo bitid_admin_notice(
							sprintf(
								_n(
									"The address %s isn't connected to your account.",
									"Those addresses %s arn't connected to your account.",
									count($found_addresses),
									'bitid-authentication'
								),
								"'" . implode("', '", $found_addresses) . "'"
							),
							'error'
						);
					}

					if(!$try_addresses)
					{
						break;
					}

					$table_name_links = "{$GLOBALS['wpdb']->prefix}bitid_userlink";

					foreach($try_addresses as $address)
					{
						$db_result = $GLOBALS['wpdb']->delete($table_name_links, array('address' => $address, 'user_id' => $user_id));

						if($db_result)
						{
							$deleted_addresses[$address] = $address;
						}
						else
						{
							$failed_addresses[$address] = $address;
						}
					}

					if($failed_addresses)
					{
						echo bitid_admin_notice(
							sprintf(
								_n(
									"Failed to remove the adress %s.",
									"Failed to remove those addresses %s.",
									count($failed_addresses),
									'bitid-authentication'
								),
								"'" . implode("', '", $failed_addresses) . "'"
							),
							'error'
						);
					}

					if($deleted_addresses)
					{
						echo bitid_admin_notice(
							sprintf(
								_n(
									"The address %s is no longer linked to your account.",
									"Those addresses %s is no longer linked to your account.",
									count($deleted_addresses),
									'bitid-authentication'
								),
								"'" . implode("', '", $deleted_addresses) . "'"
							),
							'error'
						);

						$addresses = bitid_list_users_addresses($user_id);
					}

					break;
				}
				default:
				{
					echo bitid_admin_notice("Unknowed action: " . $_REQUEST['action'], 'error');
					break;
				}
			}
		}

		$page_title = _x("My bitid-addresses", "page_title", 'bitid-authentication');
		$add_link_title = __("Add New");

		echo <<<HTML_BLOCK
<div class="wrap">
	<h2>
		<span>{$page_title}</span>
		<a class="add-new-h2" href="?page={$_REQUEST['page']}&action=add">{$add_link_title}</a>
	</h2>

HTML_BLOCK;

		if(!$addresses)
		{
			echo bitid_admin_notice(__("You have no bitid-addresses connected to your account.", 'bitid-authentication'));
			return;
		}

		class my_bitid_addresses extends WP_List_Table
		{
			function get_columns()
			{
				return array(
					'cb' => '<input type="checkbox" />',
					'address' => _x('Bitid-address', 'column_name', 'bitid-authentication'),
					'birth' => _x('Added', 'column_name', 'bitid-authentication'),
					'pulse' => _x('Last time used', 'column_name', 'bitid-authentication'),
				);
			}

			function get_sortable_columns()
			{
				return array(
					'address'  => array('address',false),
					'birth' => array('birth',false),
					'pulse'   => array('pulse',false)
				);
			}

			function usort_reorder( $a, $b )
			{
				// If no sort, default to title
				$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'address';
				// If no order, default to asc
				$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
				// Determine sort order
				$result = strcmp( $a[$orderby], $b[$orderby] );
				// Send final sort direction to usort
				return ( $order === 'asc' ) ? $result : -$result;
			}

			function prepare_items($items)
			{
				$columns = $this->get_columns();
				$hidden = array();
				$sortable = $this->get_sortable_columns();
				$this->_column_headers = array($columns, $hidden, $sortable);
				usort( $items, array( &$this, 'usort_reorder' ) );
				$this->items = $items;
			}

			function column_default($item, $column_name)
			{
				return $item[$column_name];
			}

			function column_address($item)
			{
				$action_template = '<a href="?page=%s&action=%s&address=%s">%s</a>';
				$actions = array(
					'edit'      => sprintf($action_template, $_REQUEST['page'], 'edit', $item['address'], __('Edit')),
					'delete'    => sprintf($action_template, $_REQUEST['page'], 'delete', $item['address'], __('Remove')),
				);
				return $item['address'] . $this->row_actions($actions);
			}

			function get_bulk_actions()
			{
				return array(
					'delete' => __('Delete'),
				);
			}

			function column_cb($item)
			{
				return sprintf('<input type="checkbox" name="bitid_row[]" value="%s" />', $item['address'] );
			}
		}

		echo <<<HTML_BLOCK
	<form action='?page={$_REQUEST['page']}' method='post'>

HTML_BLOCK;
		$my_bitid_addresses = new my_bitid_addresses();
		$my_bitid_addresses->prepare_items($addresses);
		$my_bitid_addresses->display();

			echo <<<HTML_BLOCK
	</form>
</div>

HTML_BLOCK;
	}

	function bitid_get_nonce()
	{
		$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";

		$session_id = session_id();

		if(!$session_id)
		{
			session_start();
			$session_id = session_id();
		}

		if(!$session_id)
		{
			return FALSE;
		}

		$query = $GLOBALS['wpdb']->prepare("SELECT * FROM {$table_name_nonce} WHERE session_id = %s", $session_id);
		$nonce_row = $GLOBALS['wpdb']->get_row($query, ARRAY_A);

		if($nonce_row)
		{
			return $nonce_row['nonce'];
		}
		$nonce_row = array();
		$nonce_row['nonce'] = BitID::generateNonce();
		$nonce_row['session_id'] = $session_id;
		$nonce_row['birth'] = current_time('mysql');

		$db_result = $GLOBALS['wpdb']->insert( $table_name_nonce, $nonce_row );
		if($db_result)
		{
			return $nonce_row['nonce'];
		}
		else
		{
			return $db_result;
		}
	}

	function bitid_get_callback_url($nonce = NULL)
	{
		if(!$nonce)
		{
			$nonce = bitid_get_nonce();
		}

		if(!$nonce)
		{
			return FALSE;
		}

		$url = home_url("bitid/callback?x=" . $nonce);

		if(substr($url, 0, 8) == 'https://')
		{
			return 'bitid://' . substr($url, 8);
		}
		else
		{
			return 'bitid://' . substr($url, 7) . "&u=1";
		}
	}

	function bitid_login_header($messages)
	{
		$url = bitid_get_callback_url();

		if(!$url)
		{
			return $messages;
		}

		$title = _x("BitID login", 'qr_image_label', 'bitid-authentication');
		$alt_text = htmlentities(_x("QR-code for BitID", 'qr_alt_text', 'bitid-authentication'), ENT_QUOTES);

		$url_encoded_url = urlencode($url);
		$messages .= <<<HTML_BLOCK
<div id='bitid'>
	<p>
		<span>{$title}:</span>
		<a href='{$url}'>
			<img src='https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl={$url_encoded_url}' alt='{$alt_text}' title='{$alt_text}' />
		</a>
	</p>
</div>
HTML_BLOCK;

		return $messages;
	}

	function bitid_login_script()
	{
		$ajax_url = admin_url('admin-ajax.php?action=bitid');

		$js = <<<JS_BLOCK
var bitid_interval_resource;
bitid_interval_resource = setInterval(
	function()
	{
		var ajax = new XMLHttpRequest();
		ajax.open("GET", "{$ajax_url}", true);
		ajax.onreadystatechange =
			function ()
			{
				if(ajax.readyState != 4 || ajax.status != 200)
				{
					return;
				}
				else if(ajax.responseText > '')
				{
					var json = JSON.parse(ajax.responseText);

					if(json.html > '')
					{
						document.getElementById('bitid').innerHTML = json.html;
					}

					if(json.stop > 0)
					{
						window.clearInterval(bitid_interval_resource);
					}

					if(json.reload > 0)
					{
						var redirect = document.getElementsByName("redirect_to");
						if(redirect && redirect[0].value > '')
						{
							window.location.href = redirect[0].value;
						}
						else
						{
							window.location.href = "wp-admin/";
						}
					}
				}
			};
		ajax.send();
	},
	3000
);
JS_BLOCK;

		echo "<script type=\"text/javascript\">\n{$js}\n</script>";
	}

	function bitid_exit()
	{
		$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";

		$session_id = session_id();

		if(!$session_id)
		{
			session_start();
			$session_id = session_id();
		}

		if(!$session_id)
		{
			return FALSE;
		}

		$query = $GLOBALS['wpdb']->prepare("SELECT * FROM {$table_name_nonce} WHERE session_id = %s", $session_id);
		$nonce_row = $GLOBALS['wpdb']->get_row($query, ARRAY_A);
		if($nonce_row)
		{
			$GLOBALS['wpdb']->delete($table_name_nonce, array('session_id' => $session_id));
		}
	}

	function bitid_callback_test()
	{
		$bitid_callback_url = "/bitid/callback";
		if(strstr($_SERVER['REQUEST_URI'], $bitid_callback_url))
		{
			require_once("callback.php");
		}
	}

	function bitid_ajax()
	{
		require_once("ajax.php");
	}

	function bitid_list_users_addresses($user_id)
	{
		$table_name_links = "{$GLOBALS['wpdb']->prefix}bitid_userlink";

		if($user_id === TRUE)
		{
			$query = "SELECT * FROM {$table_name_links}";
			return $GLOBALS['wpdb']->get_results($query, ARRAY_A);
		}
		else
		{
			$query = "SELECT * FROM {$table_name_links} WHERE user_id = %d";
			$query = $GLOBALS['wpdb']->prepare($query, (int) $user_id);
			return $GLOBALS['wpdb']->get_results($query, ARRAY_A);
		}
	}

	function bitid_admin_notice($text, $class = 'updated')
	{
		return  <<<HTML_BLOCK
	<div class='{$class}'>
		<p>{$text}</p>
	</div>

HTML_BLOCK;
	}