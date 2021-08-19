<?php 
require('config.php');
require('functions.php');
require('texts.php');

if (md5($_GET['webtoken'] . $salt) != md5($webtoken . $salt)) {
	die('Invalid token.');
}

$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($db->connect_error) {
	logm('db conn err');
	die('Database connection error.');
}

if (isset($fakeInput)) {
	$update = $fakeInput;
}
else {
	$update = file_get_contents('php://input');
}

$rawupdate = $update;
$update = json_decode($update, true);
if ($update === null) {
	logm('Incoming message is not recognizable json.');
	return false;
}

if (!isset($update['message']) || !isset($update['message']['text'])) {
	if ($update['message']['chat']['id'] == -1001417395515) {
		msg($update['message']['chat']['id'], $rawupdate);
		exit;
	}
	if (isset($update['message'])) {
		logm('Incoming message is not a normal text message. Sending response.');
		msg($update['message']['chat']['id'], 'I cannot understand non-text messages like the one you just sent.');
	}
	else {
		logm('Incoming message is not a normal text message. No response possible.');
	}
	return false;
}

$message = $update['message'];
$uid = floatval($message['from']['id']);
$chatid = intval($message['chat']['id']);
$fromUser = trim($message['from']['first_name'] . ' ' . $message['from']['last_name']);
$sentWelcomeMessages = false;
$isGroupChat = false;

if ($message['chat']['type'] != 'private') {
	// This is a "group", "supergroup" or "channel" (or a new type perhaps), i.e. not a PM chat.
	// We anchor these messages to the group instead of the sender by faking the uid. A bit hacky but I didn't want to rewrite+test a lot of
	// code and add a bunch of complexity when I can just set one variable and be done.
	$uid = $chatid;
	$isGroupChat = true;
}

$result = $db->query("SELECT name FROM paytrack_users$dbsuffix WHERE uid = $uid") or error('Database error 28741: ' . $db->error);
if ($result->num_rows == 0) {
	// New user!
	$name = $db->escape_string($fromUser);
	$db->query("INSERT INTO paytrack_users$dbsuffix (uid, name, chatid) VALUES($uid, '$name', $chatid)") or error('Database error 30955: ' . $db->error);

	if ($isGroupChat) {
		foreach ($groupWelcomeMessages as $msg) {
			msg($chatid, $msg);
		}
	}
	else {
		foreach ($welcomeMessages as $msg) {
			msg($chatid, $msg);
		}
		$sentWelcomeMessages = true;
	}
}
else {
	$result = $result->fetch_row();
	if ($result[0] != $fromUser) {
		// User's name changed
		$name = $db->escape_string($fromUser);
		$db->query("UPDATE paytrack_users$dbsuffix SET name = '$name' WHERE uid = $uid") or error('Database error 2479: ' . $db->error);
	}
}

if (strpos(strtolower($message['text']), '@' . strtolower($botUsername)) !== false) {
	$message['text'] = preg_replace("#(/[a-zA-Z]+)@$botUsername(.*)$#i", '$1$2', $message['text']);
}

if (strpos($message['text'], "/$groupprefix ") === 0) {
	$message['text'] = substr($message['text'], strlen($groupprefix) + 2);
}
else if (strpos($message['text'], "/$groupprefixshortcut ") === 0) {
	$message['text'] = substr($message['text'], strlen($groupprefixshortcut) + 2);
}

if ($message['text'][0] == '/') {
	if (strpos($message['text'], ' ') !== false) {
		$splitMsg = explode(' ', $message['text'], 2);
		$trimmed = trim($splitMsg[1]);
		if (empty($trimmed)) {
			unset($splitMsg[1]);
		}
	}
	else {
		$splitMsg = [$message['text']];
	}

	if ($message['text'] == "/$groupprefix") {
		msg($chatid, "The `/$groupprefix` command can be used in groups and should be followed by what would be commandless in a private chat. For more info, see /helpgroup");
		return true;
	}
	else if ($message['text'] == '/start' || $splitMsg[0] == '/start') {
		if (count($splitMsg) > 1) {
			if ($splitMsg[1][0] != 's' || $splitMsg[1][strlen($splitMsg[1]) - 1] != 'l') {
				msg($chatid, 'Something went wrong with the link. Please try again.');
				return false;
			}
			else {
				$msg = substr($splitMsg[1], 1, strlen($splitMsg[1]) - 2);
				$hash = substr($msg, 0, 32);
				$id = substr($msg, 32);
				$shouldBeHash = md5($id . $salt);
				if (md5($shouldBeHash . $salt) != md5($hash . $salt)) {
					logm('Invalid code given: ' . $message['text']);
					msg($chatid, 'Invalid link.');
					return false;
				}

				// Get the connected person's name to display (and make sure we know the other user)
				$result = $db->query("
					SELECT pc.uid2, pu.name, pu.chatid, pc.givenName, pc.uid1
					  FROM paytrack_connect$dbsuffix pc
					       INNER JOIN paytrack_users$dbsuffix pu
						   ON pu.uid = pc.uid1
					 WHERE pc.id = $id
				") or error('Database error 24931: ' . $db->error);

				if ($result->num_rows != 1) {
					msg($chatid, 'Connect link not found.');
					return false;
				}

				$result = $result->fetch_row();
				if ($result[0] != null) {
					msg($chatid, 'This connect link is already used.');
					return false;
				}

				if ($result[2] == $chatid) {
					msg($chatid, 'You cannot connect to yourself.');
					return false;
				}

				$existingConnection = $db->query("SELECT id FROM paytrack_connect$dbsuffix WHERE uid2 = $uid AND uid1 = "
					. "(SELECT uid1 FROM paytrack_connect$dbsuffix WHERE id = $id)") or error('Database error 12209: ' . $db->error);
				if ($existingConnection->num_rows > 0) {
					msg($chatid, 'You already have a connection with this person under a different name.');
					return false;
				}

				// Connect them
				$db->query("UPDATE paytrack_connect$dbsuffix SET uid2 = $uid WHERE id = $id") or error('Database error 9507: ' . $db->error);

				$connectedUser = getConnectedUser($result[4], $result[3]);
				if ($connectedUser === false) {
					// Not mutual
					msg($chatid, "You connected to $result[1]. You are notified when they add a debt involving you, but not when you add one for them. "
						. "To complete the link, use `/connect name` where you replace `name` with whatever you want to call $result[1].");
					msg($result[2], "$result[3] just opened your link. They will be notified when you add a debt for them, but not the other way around. To complete the link, ask them for their connect link.");
				}
				else {
					$amount1 = getAmount($uid, $connectedUser['name']);
					$connectedUser2 = getConnectedUser($uid, $connectedUser['name']);
					$amount2 = getAmount($connectedUser2['uid'], $connectedUser2['name']);

					$reset1 = '';
					$reset2 = '';
					$t = time();

					if ($amount1 != -$amount2 && $amount1 != 0 && $amount2 != 0) {
						// If they have unequal amounts and both are non-zero: reset their balance

						if ($amount1 != 0) {
							$reset1 = " To synchronize, the balance has been reset to 0 (was $amount1).";
						}

						if ($amount2 != 0) {
							$reset2 = " To synchronize, the balance has been reset to 0 (was $amount2).";
						}

						$name_db = $db->escape_string($connectedUser['name']);
						$db->query("UPDATE paytrack$dbsuffix SET amount = 0, lastupdate = $t WHERE uid = $uid AND name = '$name_db'") or error('Database error 7384: ' . $db->error);

						$name_db = $db->escape_string($connectedUser2['name']);
						$db->query("UPDATE paytrack$dbsuffix SET amount = 0, lastupdate = $t WHERE uid = $connectedUser2[uid] AND name = '$name_db'") or error('Database error 7384: ' . $db->error);
					}
					else if (($amount1 != 0 || $amount2 != 0) && $amount1 != -$amount2) {
						// If one of them is non-zero but not both: update the zero one.
						if ($amount1 != 0) {
							$displayAmount = -$amount1;
							$reset2 = " To synchronize, your balance has been updated to $displayAmount (was 0).";

							$name_db = $db->escape_string($connectedUser2['name']);
							$db->query("UPDATE paytrack$dbsuffix SET amount = -$amount1, lastupdate = $t WHERE uid = $connectedUser2[uid] AND name = '$name_db'") or error('Database error 7384: ' . $db->error);
						}
						else {
							$displayAmount = -$amount2;
							$reset1 = " To synchronize, your balance has been updated to $displayAmount (was 0).";

							$name_db = $db->escape_string($connectedUser['name']);
							$db->query("UPDATE paytrack$dbsuffix SET amount = -$amount2, lastupdate = $t WHERE uid = $uid AND name = '$name_db'") or error('Database error 7384: ' . $db->error);
						}
					}

					msg($chatid, "You are now mutually connected to $connectedUser[name]. Any debts you or they add will cascade to the other's account.$reset1");
					msg($result[2], "$result[3] just opened your link. You are now mutually connected. Any debts you or they add will cascade to the other's account.$reset2");
				}
			}
		}
		else {
			// Just /start, no param
			if (!$sentWelcomeMessages) {
				foreach ($welcomeMessages as $msg) {
					msg($chatid, $msg);
				}
			}
		}
	}
	else if ($message['text'] == '/rename' || $splitMsg[0] == '/rename') {
		if (count($splitMsg) == 1 || strpos($splitMsg[1], ':') === false) {
			msg($chatid, 'Use `/rename` like this: `/rename oldname: newname`, like `/rename girlfriend: wife`. See /help');
			return false;
		}

		$fromto = explode(':', $splitMsg[1]);
		$fromto[1] = trim($fromto[1]);
		$name_from_db = $db->escape_string(trim($fromto[0]));
		$name_to_db = $db->escape_string(trim($fromto[1]));

		$amount = getAmount($uid, $fromto[0]);
		if ($amount === false) {
			msg($chatid, "User '$fromto[0]' not found.");
			return false;
		}

		$db->query("UPDATE paytrack$dbsuffix SET name = '$name_to_db' WHERE uid = $uid AND name = '$name_from_db'") or error('Database error 23524: ' . $db->error);
		$db->query("UPDATE paytrack_connect$dbsuffix SET givenName = '$name_to_db' WHERE uid1 = $uid AND givenName = '$name_from_db'") or error('Database error 23524: ' . $db->error);

		msg($chatid, "Updated $fromto[0] to $fromto[1].");
		return true;
	}
	else if ($message['text'] == '/remind' || $splitMsg[0] == '/remind') {
		if (count($splitMsg) == 1) {
			msg($chatid, 'You should add a name to `/remind`, like `/remind sally` to remind Sally of their debt. See /help');
			return false;
		}

		if (strpos($splitMsg[1], ':') !== false) {
			$splitMsg[1] = explode(':', $splitMsg[1], 2);
			$name = trim($splitMsg[1][0]);
			$message = trim($splitMsg[1][1]);

			if (empty($name)) {
				msg($chatid, 'No name given.');
				return false;
			}
		}
		else {
			$message = '';
			$name = trim($splitMsg[1]);
		}

		$amount = getAmount($uid, $name);
		if ($amount === false) {
			msg($chatid, "User '$name' not found.");
			return false;
		}

		$msg = 'asked me to remind you that ';
		if ($amount > 0) {
			$variant = 'you owe them ';
			$msg .= $variant . $amount;
		}
		else {
			$variant = 'they owe you ';
			$msg .= $variant . (-$amount);
		}

		if (!empty($message)) {
			$msg .= ". They said: $message";
		}

		$connectedUser = getConnectedUser($uid, $name);
		if ($connectedUser !== false) {
			// Mutual connection.
			msg($connectedUser['chatid'], "$connectedUser[name] $msg");
			if (!empty($message)) {
				$message = " Attached message: $message";
			}
			msg($chatid, "Reminded $name that $variant$amount.$message");
			return true;
		}

		$name_db = $db->escape_string($name);
		$connectionResult = $db->query("
			SELECT pu.chatid, pc.id, pc.uid2
			  FROM paytrack_connect$dbsuffix pc
				   INNER JOIN paytrack_users$dbsuffix pu
				   ON pu.uid = pc.uid2
			 WHERE pc.uid1 = $uid
			   AND givenName = '$name_db'
		") or error('Database error 17025: ' . $db->error);

		if ($connectionResult->num_rows == 0) {
			msg($chatid, 'You have no connection with this user. You must `/connect` with them first. See /help');
			return false;
		}

		$connectionResult = $connectionResult->fetch_row();

		msg($connectedUser['chatid'], "$fromUser $msg");
		msg($chatid, "To $name: $fromUser $msg");

		return true;
	}
	else if ($message['text'] == '/connect' || $splitMsg[0] == '/connect') {
		if (count($splitMsg) == 1) {
			msg($chatid, 'You should add a name to `/connect`, like `/connect sally` to generate a connect link for Sally. See /help');
			return false;
		}

		$givenName = $splitMsg[1];
		$name_db = $db->escape_string($givenName);

		$result = $db->query("SELECT id FROM paytrack$dbsuffix WHERE uid = $uid AND name = '$name_db'") or error('Database error 31861: ' . $db->error);
		if ($result->num_rows == 0) {
			// Name not found, we need to create it
			$t = time();
			$db->query("INSERT INTO paytrack$dbsuffix (uid, name, amount, lastupdate) VALUES($uid, '$name_db', 0, $t)") or error('Database error 19942: ' . $db->error);
		}

		// Find an existing connect link, if any, otherwise create it
		$result = $db->query("SELECT id FROM paytrack_connect$dbsuffix WHERE uid1 = $uid AND givenName = '$name_db'") or error('Database error 18533: ' . $db->error);
		if ($result->num_rows > 0) {
			$result = $result->fetch_row();
			$id = $result[0];
		}
		else {
			$db->query("INSERT INTO paytrack_connect$dbsuffix (uid1, givenName) VALUES($uid, '$name_db')") or error('Database error 23882: ' . $db->error);
			$id = $db->insert_id;
		}

		$code = 's' . md5($id . $salt) . $id . 'l';
		$link = "https://telegram.me/$botUsername?start=$code";

		msg($chatid, "Give $givenName this link to connect and tell them to click Start: $link");

		return $code;
	}
	else if ($message['text'] == '/disconnect' || $splitMsg[0] == '/disconnect') {
		if (count($splitMsg) == 1) {
			msg($chatid, 'You should add a name to `/disconnect`, like `/disconnect sally`. See /help');
			return false;
		}

		$name = $splitMsg[1];
		$name_db = $db->escape_string($name);

		$connectedUser = getConnectedUser($uid, $name);
		if ($connectedUser !== false) {
			// Mutual connection. Inform the other party.
			msg($connectedUser['chatid'], "$connectedUser[name] disconnected. Your accounts are no longer synchronized, although they will still be notified of your updates.");
		}

		$db->query("DELETE FROM paytrack_connect$dbsuffix WHERE uid1 = $uid AND givenName = '$name_db' AND uid2 IS NOT NULL") or error('Database error 1431: ' . $db->error);
		if ($db->affected_rows == 0) {
			// Apparently they were already disconnected? Let's also cancel the reverse connection.
			msg($chatid, 'You are not connected to them. They may still be connected to you, though. That\'s hard to tell at the moment.');
			return false;
		}
		if ($connectedUser !== false) {
			msg($chatid, 'Ok. They will no longer receive updates from you and your accounts are not shared anymore, but you will still receive updates from them.');
		}
		else {
			msg($chatid, 'Ok. They will no longer receive updates from you.');
		}
		return true;
	}
	else if ($message['text'] == '/stop') {
		msg($chatid, 'Not implemented, sorry. You can complain to ' . $author);
	}
	else if ($message['text'] == '/examples' || $message['text'] == '/example') {
		foreach ($exampleMessages as $exampleMessage) {
			msg($chatid, $exampleMessage);
		}
	}
	else if ($message['text'] == '/about') {
		foreach ($aboutMessages as $aboutMessage) {
			msg($chatid, $aboutMessage);
		}
	}
	else if ($message['text'] == '/helpgroup') {
		foreach ($helpgroupMessages as $hgmsg) {
			msg($chatid, $hgmsg);
		}
	}
	else if ($message['text'] == '/help') {
		foreach ($helpMessages as $helpMessage) {
			msg($chatid, $helpMessage);
		}
	}
	else if ($message['text'] == '/total' || $message['text'] == '/totals' || $message['text'] == '/totalall') {
		$result = $db->query("SELECT name, amount FROM paytrack$dbsuffix WHERE uid = $uid ORDER BY lastupdate") or error('Database error 467: ' . $db->error);
		$total = 0;
		$totalToGet = 0;
		$totalToReturn = 0;
		$msg = '';
		while ($row = $result->fetch_row()) {
			$amount = floatval($row[1]);
			if ($amount == 0 && $message['text'] != '/totalall') {
				continue;
			}

			$total += $amount;
			if ($amount < 0) {
				$totalToReturn += -$amount;
			}
			else {
				$totalToGet += $amount;
			}
			$msg .= "$row[0]: $amount\n"; }
		$msg .= "You still owe: $totalToReturn\n"
			. "You are owed: $totalToGet\n"
			. "Total balance: $total";
		msg($chatid, $msg);
	}
	else {
		msg($chatid, 'Unknown command. Send /start for quick info. Use /help to see all commands.');
	}

	return true;
}

// No command given, so it's a "name debt: description" message (or a "name debt" message (or a "name" message))

if (strpos($message['text'], ':') !== false) {
	$msg = explode(':', $message['text'], 2);
	$description = trim($msg[1]);
}
else {
	$msg = [$message['text']];
}

// Split on the last space
$msg = explode(' ', strrev($msg[0]), 2);
$updated = false;

if (count($msg) > 0) {
	// Since we reversed to split on the *last* space, reverse the two parts
	$amount = strrev($msg[0]);
	if (substr_count($amount, ',') === 1 && substr_count($amount, '.') === 0) {
		// If there is one comma in there, they might have tried to use it as decimal separator.
		$amount = str_replace(',', '.', $amount);
	}
	$name = strrev($msg[1]);
}
else {
	$amount = '';
	$name = strrev($msg[0]);
}

$epsilon = 1/1e7;

if (is_numeric($amount) || is_fraction($amount)) {
	if (is_fraction($amount)) {
		$amount = explode('/', $amount, 2);
		if (abs(floatval($amount[1])) < $epsilon) {
			msg($chatid, "Do not divide by zero, please ;)");
			return false;
		}
		$amount = round(floatval($amount[0]) / floatval($amount[1]), 3);
	}
	else {
		$amount = floatval($amount);
	}
	if ($amount == 0) {
		msg($chatid, "Amount 0 does nothing. To get someone's balance, send me the name. In this case, just send me: $name");
		return false;
	}
	$name_db = $db->escape_string($name);
	$t = time();

	$result = $db->query("SELECT amount FROM paytrack$dbsuffix WHERE uid = $uid AND name = '$name_db'") or error('Database error 14054: ' . $db->error);
	if ($result->num_rows == 0) {
		$result = $db->query("INSERT INTO paytrack$dbsuffix (uid, name, amount, lastupdate) VALUES($uid, '$name_db', $amount, $t)") or error('Database error 18477: ' . $db->error);
	}
	else {
		$newamount = $result->fetch_row()[0] + $amount;
		if (abs($newamount) < $epsilon) {
			$newamount = 0;
		}
		$result = $db->query("UPDATE paytrack$dbsuffix SET amount = $newamount, lastupdate = $t WHERE uid = $uid AND name = '$name_db'") or error('Database error 9489: ' . $db->error);
	}

	$updated = true;
	$updateAmount = $amount;

	$msg = 'Ok. Updated amount: ';
}
else {
	// Apparently that was not a number. They gave us a name only.
	$name = trim($name . ' ' . $amount);
	$name_db = $db->escape_string($name);
	$msg = '';
}

$result = $db->query("SELECT amount FROM paytrack$dbsuffix WHERE uid = $uid AND name = '$name_db'") or error('Database error 25321: ' . $db->error);
if ($result->num_rows == 0) {
	msg($chatid, 'Unknown name. Do you want /help?');
	return false;
}
$result = $result->fetch_row();
$amount = floatval($result[0]);
if ($amount < 0) {
	$amountMin = -$amount;
	msg($chatid, $msg . "You owe $name $amountMin");
}
else if ($amount > 0) {
	msg($chatid, $msg . "$name owes you $amount");
}
else {
	msg($chatid, $msg . '0. You are neither owed nor owe them!');
}

if ($updated) {
	$connectedUser = getConnectedUser($uid, $name);
	if ($connectedUser === false) {
		// Not a mutual connection

		$connectionResult = $db->query("
			SELECT pu.chatid, pc.id, pc.uid2
			  FROM paytrack_connect$dbsuffix pc
				   INNER JOIN paytrack_users$dbsuffix pu
				   ON pu.uid = pc.uid2
			 WHERE pc.uid1 = $uid
			   AND givenName = '$name_db'
		") or error('Database error 17025: ' . $db->error);

		if ($connectionResult->num_rows == 0) {
			// Not a mutual connection... and in fact not a connection at all. Kthxbye!
			return true;
		}

		// We have a one-way connection!
		$connectionResult = $connectionResult->fetch_row();

		if ($amount > 0) {
			$msg = 'now owe them';
		}
		else {
			$msg = 'are now owed';
			$amount = -$amount;
		}
		msg($connectionResult[0], "$fromUser just added a debt of $updateAmount" . (isset($description) ? " for $description" : ' (no description given)') . ". You $msg $amount.");

		return true;
	}

	$connectedName_db = $db->escape_string($connectedUser['name']);
	$db->query("UPDATE paytrack$dbsuffix SET amount = -$amount, lastupdate = $t WHERE uid = $connectedUser[uid] AND name = '$connectedName_db'") or error('Database error 13621: ' . $db->error);

	if ($amount > 0) {
		$msg = 'now owe them';
	}
	else {
		$msg = 'are now owed';
		$amount = -$amount;
	}
	msg($connectedUser['chatid'], "$connectedUser[name] just added a debt of $updateAmount" . (isset($description) ? " for $description" : ' (no description given)') . ". You $msg $amount.");
}

return true;

