<?php

	$session_id = session_id();

	if(!$session_id)
	{
		session_start();
		$session_id = session_id();
	}

	$table_name_nonce = "{$GLOBALS['wpdb']->prefix}bitid_nonce";
	$table_name_userlink = "{$GLOBALS['wpdb']->prefix}bitid_userlink";
	$query = $GLOBALS['wpdb']->prepare("SELECT * FROM {$table_name_nonce} WHERE session_id = %s", $session_id);
	$nonce_row = $GLOBALS['wpdb']->get_row($query, ARRAY_A);

	$data = array();

	if(!$nonce_row)
	{
		$data['status'] = -1;
		$data['html'] = "<p>" . __("Error: The current session dosn't have a bitid-nonce.", 'bitid-authentication') . "</p>";
	}
	else if($nonce_row['address'])
	{
		$data['status'] = 1;
		$data['adress'] = $nonce_row['address'];

		$query = $GLOBALS['wpdb']->prepare("SELECT * FROM {$table_name_userlink} WHERE address = %s", $data['adress']);
		$user_row = $GLOBALS['wpdb']->get_row($query, ARRAY_A);
		if($user_row)
		{
			if(is_user_logged_in())
			{
				$data['html'] = "<p>" . __("Allredy logged in", 'bitid-authentication') . "</p>";
				$data['reload'] = 1;
			}
			else
			{
				$user = get_user_by( 'id', $user_row['user_id'] );
				if($user)
				{
					wp_set_current_user($user->ID, $user->user_login);
					wp_set_auth_cookie($user->ID);
					do_action('wp_login', $user->user_login, $user);

					$data['html'] = "<p>" . sprintf(__("Sucess, loged in as '%s'", 'bitid-authentication'), $user->user_login) . "</p>";
					$data['reload'] = 1;

					$update_query = $GLOBALS['wpdb']->prepare("UPDATE {$table_name_userlink} SET pulse = NOW() WHERE address = %s", $data['adress']);
					$GLOBALS['wpdb']->update($update_query);
				}
				else
				{
					$data['html'] = "<p>" . sprintf(__("Bitid verification Sucess, but no useraccount connected to '%s'", 'bitid-authentication'), $data['adress']) . "</p>";
				}
			}
		}
		else
		{
			$data['html'] = "<p>" . sprintf(__("Bitid verification Sucess, but no useraccount connected to '%s'", 'bitid-authentication'), $data['adress']) . "</p>";
		}
	}
	else
	{
		$data['status'] = 0;
	}

	echo json_encode($data) . PHP_EOL;
	die();
?>