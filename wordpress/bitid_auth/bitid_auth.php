<?php
/**
 * @package Bitid Authentication
 * @author Puggan
 * @version 0.0.0-2014-07-02
 */
/*
Plugin Name: Bitid Authentication
Description: Bitid Authentication, extends wordpress default authentication with the bitid-protocol
Version: 0.0.0-2014-07-02
Author: Puggan
Author URI: http://blog.puggan.se
*/

DEFINE("BITID_AUTHENTICATION_PLUGIN_VERSION",'0.0.2');

	require_once("bitid.php");

	register_activation_hook( __FILE__, 'bitid_install' );
	add_action( 'plugins_loaded', 'bitid_update_db_check' );
	add_action( 'login_enqueue_scripts', 'bitid_login_script' );
	add_action( 'wp_logout', 'bitid_exit');
	// TODO: admin-menu

	add_filter( 'login_message', 'bitid_login_header' );

	/* check version on load */
	function bitid_update_db_check()
	{
		if(get_site_option( "bitid_plugin_version") !=  BITID_AUTHENTICATION_PLUGIN_VERSION )
		{
// 			bitid_install();
		}
	}

	/* install plugin, add all tables */
	function bitid_install()
	{
		$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";
		$table_name_links = "{$GLOBALS['wpdb']->prefix}bitid_userlink";

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
  PRIMARY KEY (user_id, address),
  KEY (birth),
  FOREIGN KEY (user_id) REFERENCES wp_users(ID)
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

	function bitid_get_callback_url()
	{
		$nonce = bitid_get_nonce();

		if(!$nonce)
		{
			return FALSE;
		}

// 		$url = plugins_url("callback.php?nonce=" . $nonce, __FILE__);
		$url = plugins_url("callback.php?x=" . $nonce, __FILE__);

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

		$url_encoded_url = urlencode($url);
		$messages .= <<<HTML_BLOCK
<div id='bitid'>
	<p>
		<span>BITID login:</span>
		<a href='{$url}'>
			<img src='https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl={$url_encoded_url}' />
		</a>
	</p>
</div>
HTML_BLOCK;

		return $messages;
	}

	function bitid_login_script()
	{
		$ajax_url = plugins_url("ajax.php" . $nonce, __FILE__);
		$js = <<<JS_BLOCK
setInterval(
	function()
	{
		var ajax = new XMLHttpRequest();
		ajax.open("GET", "{$ajax_url}", true);
		ajax.onreadystatechange = function ()
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
	1000
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