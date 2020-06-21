<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/utils.php';

if( validate() )
{
	error_log("Game Feedback received.");

	$userName = trim($_POST['user_name']);
	$description = trim($_POST['description']);
	$logs = null;
	if(isset($_POST['logs']) && !empty($_POST['logs']))
	{
		$raw = base64_decode($_POST['logs']);
		$logs = gzdecode($raw);
	}

	$keys = getKeys();
	$db = getDb($keys);

	$result = $db->feedback()->insert(array(
		"user_name" => $userName,
		"description" => $description,
		"logs" => $logs
	));

	echo $result;
}
else
{
	echo "Nu uh";
}

function validate()
{
	$isValid = true;

	$isValid = $isValid && $_SERVER['REQUEST_METHOD'] === 'POST';
	$isValid = $isValid && !empty($_POST['user_name']);
	$isValid = $isValid && !empty($_POST['description']);

	return $isValid;
}
