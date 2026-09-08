<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';

header('Content-Type: application/json');

// Reads come in on the query string, writes in a POST body.
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$params = $isPost ? $_POST : $_GET;
$action = $params['action'] ?? '';
$id = (string) ($params['id'] ?? '');

$writes = ['markread', 'markunread', 'delete'];

if (in_array($action, $writes, true) && !$isPost)
{
	fail(405, "'$action' has to be sent as a POST.");
}

if (!ctype_digit($id))
{
	fail(400, "'$id' is not a feedback id.");
}

try
{
	$keys = getKeys();
	$db = getDb($keys);

	$feedback_item = $db->feedback[(int) $id];

	if (!$feedback_item)
	{
		fail(404, "Feedback $id is not in the database any more. Reload the page.");
	}

	if ($action == "logs")
	{
		echo json_encode(["logs" => $feedback_item['logs']]);
	}
	elseif ($action == "markread" || $action == "markunread")
	{
		$feedback_item["new"] = ($action == "markread" ? 0 : 1);

		if ($feedback_item->update() === false)
		{
			fail(500, "The database refused to update feedback $id.");
		}

		return_success();
	}
	elseif ($action == "delete")
	{
		// delete() returns the number of rows it removed, or false on error.
		if (!$feedback_item->delete())
		{
			fail(500, "The database refused to delete feedback $id.");
		}

		return_success();
	}
	else
	{
		fail(400, "'$action' is not something this page can do.");
	}
}
catch (Throwable $e)
{
	error_log("management action '$action' on feedback $id failed: " . $e->getMessage());
	fail(500, $e->getMessage());
}

function return_success()
{
	echo json_encode(["success" => true]);
}

function fail($status, $message)
{
	http_response_code($status);
	echo json_encode(["error" => $message]);
	exit;
}
