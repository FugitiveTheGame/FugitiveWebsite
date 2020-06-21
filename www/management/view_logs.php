<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';


$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../../templates' );
$twig = new \Twig\Environment($loader);

$keys = getKeys();
$db = getDb( $keys );

if(is_numeric($_GET['id']))
{
	$feedback_id = $_GET['id'];

	$feedback = $db->feedback[$feedback_id];

	$logs = $feedback['logs'];

	echo $twig->render('view_logs.html', ['logs' => $logs] );
}