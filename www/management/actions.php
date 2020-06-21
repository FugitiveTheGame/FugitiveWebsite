<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';

$keys = getKeys();
$db = getDb( $keys );

$action = $_GET['action'];
if(!empty($action) && is_numeric($_GET['id']))
{
	$feedback_id = $_GET['id'];

	if($action == "logs")
	{
		$feedback_item = $db->feedback[$feedback_id];
		$logs = $feedback_item['logs'];

		$response = [
			"logs" => $logs
		];

		header('Content-Type: application/json');
		echo json_encode($response);
	}
	elseif($action == "markread")
	{
		$feedback_item = $db->feedback[$feedback_id];
		$feedback_item["new"] = 0;
		$feedback_item->update();

		return_success();
	}
	elseif($action == "markunread")
	{
		$feedback_item = $db->feedback[$feedback_id];
		$feedback_item["new"] = 1;
		$feedback_item->update();

		return_success();
	}
	elseif($action == "delete")
	{
		$feedback_item = $db->feedback[$feedback_id];
		$feedback_item->delete();

		return_success();
	}
}

function return_success()
{
	$response = [
		"success" => true
	];

	header('Content-Type: application/json');
	echo json_encode($response);
}