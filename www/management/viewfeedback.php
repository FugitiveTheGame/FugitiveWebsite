<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';


$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../../templates' );
$twig = new \Twig\Environment($loader);

$keys = getKeys();
$db = getDb( $keys );

$feedback_results = $db->feedback()->select( "*" )->order('date_reported DESC');

$crash_marker = '[CRASH DETECTED]';

$feedback = array();
$numNew = 0;
$numCrashes = 0;
while( $row = $feedback_results->fetch() )
{
	$isCrash = strpos($row['description'], $crash_marker) === 0;
	$description = $isCrash
		? trim(substr($row['description'], strlen($crash_marker)))
		: $row['description'];

	if( $row['new'] == 1 )
	{
		$numNew++;
		if( $isCrash )
		{
			$numCrashes++;
		}
	}

	$feedback[] = [
		'id' => $row['id'],
		'crash' => $isCrash,
		'name' => $row['user_name'],
		'description' => $description,
		'date_reported' => $row['date_reported'],
		'has_logs' => ($row['logs'] != null),
		'new' => $row['new'],
	];
}

echo $twig->render('view_feedback.html',
	[
		'feedback' => $feedback,
		'new_feedback' => $numNew,
		'new_crashes' => $numCrashes
	] );
