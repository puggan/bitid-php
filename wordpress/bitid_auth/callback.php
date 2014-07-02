<?php

	$raw_post_data = file_get_contents('php://input');

	$variables = array('address', 'signature', 'uri');

	$post_data = array();

	if(substr($raw_post_data, 0, 1) == "{")
	{
		$json = json_decode($raw_post_data, TRUE);
		foreach($variables as $key)
		{
			if(isset($json[$key]))
			{
				$post_data[$key] = (string) $json[$key];
			}
			else
			{
				$post_data[$key] = NULL;
			}
		}
	}
	else
	{
		$json = FALSE;
		foreach($variables as $key)
		{
			if(isset($_POST[$key]))
			{
				$post_data[$key] = (string) $_POST[$key];
			}
			else
			{
				$post_data[$key] = NULL;
			}
		}
	}

	require_once("bitid.php");

	if(!array_filter($post_data))
	{
		BitID::http_error(20, 'No data recived');
		die();
	}

	$nonce = BitID::extractNonce($post_data['uri']);

	if(!$nonce OR strlen($nonce) != 32)
	{
		BitID::http_error(40, 'Bad nonce');
		die();
	}

	if(file_exists("../../../wp-load.php"))
	{
		require_once("../../../wp-load.php");
	}
	else
	{
		// temporary solving the problem with "ln -s" and "../
		require_once("/mnt/data/www/wp_3_9_0/wp-load.php");
	}

	$uri = "bitid://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}?x={$nonce}";

	if(!isset($_SERVER['HTTPS']) OR !$_SERVER['HTTPS'])
	{
		$uri .= "&u=1";
	}

	if($uri != $post_data['uri'])
	{
		BitID::http_error(10, 'Bad URI');
		die();
	}

	$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";
	$query = $GLOBALS['wpdb']->prepare("SELECT * FROM {$table_name_nonce} WHERE nonce = %s", $nonce);
	$nonce_row = $GLOBALS['wpdb']->get_row($query, ARRAY_A);

	if(!$nonce_row)
	{
		BitID::http_error(41, 'Bad or expired nonce');
		die();
	}

	if($nonce_row AND $nonce_row['address'] AND $nonce_row['address'] != $post_data['address'])
	{
		BitID::http_error(41, 'Bad or expired nonce');
		die();
	}

	$bitid = new BitID();

	$signValid = $bitid->isMessageSignatureValidSafe($post_data['address'], $post_data['signature'], $post_data['uri'], TRUE);

	if(!$signValid)
	{
		BitID::http_error(30, 'Bad signature');
		die();
	}

	if(!$nonce_row['address'])
	{
		$nonce_row['address'] = $post_data['address'];
		$db_result = $GLOBALS['wpdb']->update( $table_name_nonce, array('address' => $post_data['address']), array('nonce' => $nonce));
		if(!$db_result)
		{
			BitID::http_error(50, 'Database failer', 500, 'Internal Server Error');
			die();
		}
	}

	BitID::http_ok($post_data['address'], $nonce);
	die();
