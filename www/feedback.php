<?php

if( validate() )
{
	$userName = trim($_POST['user_name']);
	$description = trim($_POST['description']);
	$logs = trim($_POST['logs']);


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
	$isValid = $isValid && !empty($_POST['logs']);

	return $isValid;
}