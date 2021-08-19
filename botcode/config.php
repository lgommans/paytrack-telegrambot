<?php 
if (!isset($dbsuffix)) {
	// For testing purposes, a suffix can be used. See <tests.php>.
	$dbsuffix = '';

	// set to false to disable logging
	$log = 'bot.log';
}

// Your mysql database info
$db_host = 'localhost';
$db_user = 'paytrack';
$db_pass = TODO change me
$db_name = 'paytrack';

// This probably doesn't change
$apiUrl = 'https://api.telegram.org/bot';

// Your bot token
$botToken = '12341234:ABCdefGHIjkletc';

// The bot's username and whom to contact for help/feature requests/issues/etc.
$botUsername = 'PayTrackBot';
$botOwner = '@lucgm';

// Randomly generate this and include in your webhook URL
$webtoken = TODO change me

// Randomly generate this
$salt = TODO change me

// The /command prefix that one has to use in a group chat
$groupprefix = 'paytrack';
$groupprefixshortcut = 'pt';

