<?php

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/utils.php';

$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../../templates' );
$twig = new \Twig\Environment($loader);

class Game
{
	public $id;
	public $length;
	public $numPlayers;
	public $winningTeam;
}

$json = file_get_contents("http://repository.fugitivethegame.online/servers");
$servers = json_decode($json);
$numServers = count($servers);
//echo("Servers Online: $numServers<br />");
$numInGameServers = 0;
foreach($servers as $server)
{
	if($server->is_joinable == false)
	{
		$numInGameServers++;
	}
}
//echo("Servers In-Game: $numInGameServers<br />");

$keys = getKeys();
$db = getDb( $keys );


$feedback_results = $db->feedback()->select( "*" )->where( "new", "1" );
$numNewFeedback = count($feedback_results);
//echo("New Feedback: $numNewFeedback<br />");

$crash_results = $db->feedback()->select( "*" )->where( "description LIKE ? AND new = ?", array("%[CRASH DETECTED]%", "1") );
$numNewCrash = count($crash_results);
//echo("New Crash Reports: $numNewCrash<br />");

$averageNumPlayers = 0;

$start_event_results = $db->server_events()->select( "*" )->where( "event_name", "game_start" );
$numStartEvents = count($start_event_results);
while( $row = $start_event_results->fetch() )
{
	$averageNumPlayers += $row['num_players'];
}
$averageNumPlayers = round($averageNumPlayers / $numStartEvents, 2);

$event_results = $db->server_events()->select( "*" )->where( "event_name", "game_end" );

$games = array();

while( $row = $event_results->fetch() )
{
	$game = new Game();

	$eventData = json_decode( $row['event_data'] );

	$game->id = $row['id'];
	$game->numPlayers = $row['num_players'];
	$game->winningTeam = $eventData->winning_team;
	$game->length = $eventData->game_length_s;

	$games[] = $game;
}

$numGames = count( $games );

$averageGameLength = 0;
$copWins = 0;
$fugitiveWins = 0;

$numValidGames = 0;

foreach( $games as $game )
{
	if( $game->length > 10 )
	{
		$averageGameLength += $game->length;
		++$numValidGames;

		if($game->winningTeam == 0)
		{
			$fugitiveWins++;
		}
		else
		{
			$copWins++;
		}
	}
	/*
	 * Hacky DB cleanup
	else if( $game->length < 5)
	{
			echo("Delete game: {$game->id}<br />");
			$db->server_events[$game->id]->delete();
	}
	*/
}

$copWinPercent = round($copWins / $numValidGames, 3) * 100.0;
$fugitiveWinPercent = round($fugitiveWins / $numValidGames, 3) * 100.0;

$seconds = floor($averageGameLength / $numValidGames);
$mins = floor($seconds / 60 % 60);
$secs = floor($seconds % 60);
$averageGameLengthStr = sprintf('%02d:%02d', $mins, $secs);

$stats = [
	'total_games_played' => $numGames,
	'valid_games' => $numValidGames,
	'average_game_length' => $averageGameLengthStr,
	'average_num_players' => $averageNumPlayers,
	'cop_win_percentage' => $copWinPercent,
	'fugitive_win_percentage' => $fugitiveWinPercent,
	'servers_online' => $numServers,
	'servers_in_game' => $numInGameServers
];


echo $twig->render('gamestats.html', ['stats' => $stats] );