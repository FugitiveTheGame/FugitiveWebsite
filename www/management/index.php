<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/utils.php';

$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../../templates' );
$twig = new \Twig\Environment($loader);

$keys = getKeys();
$db = getDb( $keys );

$feedback_results = $db->feedback()->select( "*" )->where( "new", "1" );
$numNewFeedback = count($feedback_results);

$crash_results = $db->feedback()->select( "*" )->where( "description LIKE ? AND new = ?", array("%[CRASH DETECTED]%", "1") );
$numNewCrash = count($crash_results);


echo $twig->render('index.html',
	[
		'new_feedback' => $numNewFeedback,
		'new_crashes' => $numNewCrash
	]);
