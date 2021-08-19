<?php 
if (!function_exists('msg')) {
	function escapeNewlines($text) {
		return str_replace("\n", '\n', $text);
	}

	function msg($chatid, $text) {
		global $botToken, $apiUrl, $fakeSendMsg;

		if (isset($fakeSendMsg)) {
			echo "msg(chatid=$chatid, text=</pre>" . escapeNewlines($text) . '<pre>)<br>';
			return;
		}

		$text = urlencode($text);

		return file_get_contents("$apiUrl$botToken/sendMessage?chat_id=$chatid&text=$text&parse_mode=Markdown");
	}

	function logm($msg) {
		global $logm, $log;

		if ($log === false) {
			return;
		}

		if ($log == '>stdout') {
			echo "Log: $msg<br>";
			return;
		}

		if (!isset($logm)) {
			$logm = fopen($log, 'a');
		}

		fwrite($logm, date('Y-m-d H:i:s ') . $msg . "\n");
	}

	function error($msg) {
		logm($msg);

		header('HTTP/1.1 500 Internal Server Error');
		die('Something unexpected happened. This incident has been logged.');
	}

	function getAmount($uid, $name) {
		global $db, $dbsuffix;

		$name_db = $db->escape_string($name);
		$result = $db->query("SELECT amount FROM paytrack$dbsuffix WHERE uid = $uid AND name = '$name_db'") or error('Database error 18767: ' . $db->error);
		if ($result->num_rows != 1) {
			return false;
		}
		return $result->fetch_row()[0];
	}

	function getConnectedUser($uid1, $givenName) {
		// If "joe" (uid 1) and "jane" (uid 2) are connected, joe calls jane "jany" and jane calls joe "sweetling", then getConnectedUser(1, "jany") = ['name' => 'sweetling', 'uid' => 2, etc.].
		// Returns false if there is no connection.

		global $db, $dbsuffix;

		$givenName = $db->escape_string($givenName);

		$uid2 = $db->query("SELECT uid2 FROM paytrack_connect$dbsuffix WHERE uid1 = $uid1 AND givenName = '$givenName' AND uid2 IS NOT NULL") or error('Database error 17767: ' . $db->error);
		if ($uid2->num_rows != 1) {
			return false;
		}
		$uid2 = $uid2->fetch_row()[0];

		$givenNameByOther = $db->query("SELECT givenName FROM paytrack_connect$dbsuffix WHERE uid2 = $uid1 AND uid1 = $uid2") or error('Database error 15144: ' . $db->error);
		if ($givenNameByOther->num_rows != 1) {
			return false;
		}
		$givenNameByOther= $givenNameByOther->fetch_row()[0];

		$givenNameByOther = $db->escape_string($givenNameByOther);
		$result = $db->query("
			SELECT p.id AS id, p.uid AS uid, p.name AS name, p.amount AS amount, p.lastupdate AS lastupdate, pu.chatid AS chatid
			  FROM paytrack$dbsuffix AS p
			       INNER JOIN paytrack_users$dbsuffix AS pu
				   ON pu.uid = p.uid
			 WHERE p.uid = $uid2
			   AND p.name = '$givenNameByOther'
		") or error('Database error 15540: ' . $db->error);

		if ($result->num_rows != 1) {
			return false;
		}

		return $result->fetch_assoc();
	}

	function is_fraction($str) {
		if (strpos($str, '/') === false) {
			return false;
		}

		$str = explode('/', $str, 2);
		return is_numeric($str[0]) && is_numeric($str[1]);
	}
}

