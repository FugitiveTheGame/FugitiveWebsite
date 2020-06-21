<?php

function getKeys()
{
	$home = dirname($_SERVER['DOCUMENT_ROOT'], 1);
	$keys = json_decode( utf8_encode( file_get_contents( $home . '/keys.json' ) ) );

	if( !$keys )
	{
		die('Bad Keys file');
	}

	return $keys;
}

function getDb($keys)
{
	$connection = new PDO( "mysql:dbname={$keys->mysql->database}", $keys->mysql->user, $keys->mysql->password );
	$db = new NotORM( $connection );
	return $db;
}