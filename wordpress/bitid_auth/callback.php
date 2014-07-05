<?php

	$raw_post_data = file_get_contents('php://input');

	$variables = array('address', 'signature', 'uri');

	$post_data = array();

	$json = NULL;
	$uri = NULL;
	$nonce = NULL;

	$GLOBALS['bitid_vars']['json'] = &$json;
	$GLOBALS['bitid_vars']['uri'] = &$uri;
	$GLOBALS['bitid_vars']['nonce'] = &$nonce;

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

	$uri = bitid_get_callback_url($nonce);

	if($uri != $post_data['uri'])
	{
		BitID::http_error(10, 'Bad URI', NULL, NULL, array('expected' => $uri, 'sent_uri' => $post_data['uri']));
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
