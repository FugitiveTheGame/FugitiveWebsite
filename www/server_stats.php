<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/utils.php';


if( validate() )
{
	$serverId = trim($_GET['server_id']);
	$version = trim($_GET['version']);
	$serverName = urldecode(trim($_GET['server_name']));
	$eventName = trim($_GET['event']);
	$numPlayers = trim($_GET['num_players']);
	$mapName = urldecode(trim($_GET['map_name']));
	if(!empty($_GET['event_data']))
	{
		$eventData = urldecode(trim($_GET['event_data']));
	}
	else
	{
		$eventData = null;
	}

	error_log("Game Server Event: " . $serverName);

	$keys = getKeys();
	$db = getDb($keys);

	$db->server_events()->insert(array(
		"server_id" => $serverId,
		"version" => $version,
		"server_name" => $serverName,
		"num_players" => $numPlayers,
		"map_name" => $mapName,
		"event_name" => $eventName,
		"event_data" => $eventData
	));
}
else
{
	error_log("--- INVALID request: Server Stats");
	echo 'Bad Request';
}

function validate()
{
	$isValid = true;

	$isValid = $isValid && $_SERVER['REQUEST_METHOD'] === 'GET';
	$isValid = $isValid && !empty($_GET['server_id']);
	$isValid = $isValid && !empty($_GET['version']);
	$isValid = $isValid && !empty($_GET['server_name']);
	$isValid = $isValid && !empty($_GET['num_players']);
	$isValid = $isValid && !empty($_GET['map_name']);
	$isValid = $isValid && !empty($_GET['event']);

	return $isValid;
}