<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';


$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../../templates' );
$twig = new \Twig\Environment($loader);

$keys = getKeys();
$db = getDb( $keys );

$feedback_results = $db->feedback()->select( "*" )->order('date_reported DESC');

$feedback = array();
while( $row = $feedback_results->fetch() )
{
	$newCrash = (strpos($row['description'], '[CRASH DETECTED]') === 0) && $row['new'] == 1;

	$feedback[] = [
		'id' => $row['id'],
		'crash_detected' => $newCrash,
		'name' => $row['user_name'],
		'description' => $row['description'],
		'date_reported' => $row['date_reported'],
		'has_logs' => ($row['logs'] != null),
		'new' => $row['new'],
	];
}

echo $twig->render('view_feedback.html', ['feedback' => $feedback] );