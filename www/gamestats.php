<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/utils.php';

$loader = new \Twig\Loader\FilesystemLoader( __DIR__ . '/../templates' );
$twig = new \Twig\Environment($loader);

class Game
{
	public $id;
	public $length;
	public $numPlayers;
	public $winningTeam;
	public $timestamp;
	public $serverId;
	public $mapName;
}

$mondayTs = mktime(0, 0, 0, date("n"), date("j") - date("N"));
$todayTs = mktime(0, 0, 0);

$serverRepoUrl = "http://repository.fugitivethegame.online/servers";
$serverRepoContext = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
$json = @file_get_contents($serverRepoUrl, false, $serverRepoContext);
$servers = $json === false ? null : json_decode($json);

// The repository is a separate host and may be down; the rest of the page
// comes from our own database and stays useful without it.
$serversKnown = is_array($servers);
$numServers = $serversKnown ? count($servers) : null;
$numInGameServers = null;
if ($serversKnown)
{
	$numInGameServers = 0;
	foreach($servers as $server)
	{
		if($server->is_joinable == false)
		{
			$numInGameServers++;
		}
	}
}
else
{
	error_log("Server repository unreachable: $serverRepoUrl");
}

$keys = getKeys();
$db = getDb( $keys );

$feedback_results = $db->feedback()->select( "*" )->where( "new", "1" );
$numNewFeedback = $feedback_results->count("*");//count($feedback_results);

//echo("New Feedback: $numNewFeedback<br />");

$crash_results = $db->feedback()->select( "*" )->where( "description LIKE ? AND new = ?", array("%[CRASH DETECTED]%", "1") );
$numNewCrash = $crash_results->count("*");//count($crash_results);
//echo("New Crash Reports: $numNewCrash<br />");

$averageNumPlayers = 0;

$start_event_results = $db->server_events()->select( "*" )->where( "event_name", "game_start" );
$numStartEvents = count($start_event_results);
while( $row = $start_event_results->fetch() )
{
	$averageNumPlayers += $row['num_players'];
}
$averageNumPlayers = $numStartEvents > 0 ? round($averageNumPlayers / $numStartEvents, 2) : 0;

$event_results = $db->server_events()->select( "*" )->where( "event_name", "game_end" );

$games = array();
$weeklyGames = array();
$todayGames = array();

while( $row = $event_results->fetch() )
{
	$game = new Game();

	$eventData = json_decode( $row['event_data'] );

	$game->id = $row['id'];
	$game->numPlayers = $row['num_players'];
	$game->winningTeam = $eventData->winning_team;
	$game->length = $eventData->game_length_s;
	$game->timestamp = strtotime($row['date_uploaded']);
	$game->serverId = $row['server_id'];
	$game->mapName = $row['map_name'];

	$games[] = $game;

	if($game->timestamp >= $mondayTs)
	{
		$weeklyGames[] = $game;
	}
	if($game->timestamp >= $todayTs)
	{
		$todayGames[] = $game;
	}
}

$numGames = count( $games );

$averageGameLength = 0;
$copWins = 0;
$fugitiveWins = 0;

$numValidGames = 0;
$numValidWeeklyGames = 0;
$numValidTodayGames = 0;

$xAxisLabels = getXAxisLabels($games);
$chartData = getGamesPerDay($games);

foreach( $games as $index=>$game )
{
	if( $game->length > 10 )
	{
		$averageGameLength += $game->length;
		++$numValidGames;
		if($game->timestamp >= $mondayTs)
		{
			++$numValidWeeklyGames;
		}
		if($game->timestamp >= $todayTs)
		{
			++$numValidTodayGames;
		}

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

if( $numValidGames > 0 )
{
	$copWinPercent = round($copWins / $numValidGames, 3) * 100.0;
	$fugitiveWinPercent = round($fugitiveWins / $numValidGames, 3) * 100.0;
	$seconds = floor($averageGameLength / $numValidGames);
}
else
{
	$copWinPercent = 0;
	$fugitiveWinPercent = 0;
	$seconds = 0;
}

$mins = floor($seconds / 60 % 60);
$secs = floor($seconds % 60);
$averageGameLengthStr = sprintf('%02d:%02d', $mins, $secs);

$numWeeklyGames = count($weeklyGames);
$numTodayGames = count($todayGames);

$stats = [
	'total_games_played' => $numGames,
	'valid_games' => $numValidGames,
	'average_game_length' => $averageGameLengthStr,
	'average_num_players' => $averageNumPlayers,
	'cop_win_percentage' => $copWinPercent,
	'fugitive_win_percentage' => $fugitiveWinPercent,
	'servers_known' => $serversKnown,
	'servers_online' => $numServers,
	'servers_in_game' => $numInGameServers,
	'week_games_played' => $numWeeklyGames,
	'week_valid_games' => $numValidWeeklyGames,
	'today_games_played' => $numTodayGames,
	'today_valid_games' => $numValidTodayGames
];

$now = new DateTime();
$current_time_gmt = $now->format('H:i:s d M Y');

$chart = [
	'chart_x_axis_labels' => $chartData['labels'],
	'chart_games_per_day' => $chartData['gamesPerDay'],
	'chart_big_games_per_day' => $chartData['bigGamesPerDay'],
];

echo $twig->render('gamestats.html', ['stats' => $stats, 'chart' => $chart, 'current_time_gmt' => $current_time_gmt] );

function xAxisLabel($timestamp)
{
	return date_format(date_timestamp_set(new DateTime(),$timestamp), 'd/M/y');;
}

function getXAxisLabels($games)
{
	$xAxisLabels = [];
	foreach($games as $game)
	{
		$xAxisLabels[] = xAxisLabel($game->timestamp);
	}

	return $xAxisLabels;
}

function getDaysSinceZero($timestamp)
{
	$zero = new DateTime('0001-01-01');
	$dateTime = new DateTime();
	$dateTime->setTimestamp($timestamp);
	$dateTime->diff($zero);
	$diff = $dateTime->diff($zero);
	return intval($diff->format('%a'));
}

function getGamesPerDay($games)
{
	$numGames = count( $games );
	if( $numGames === 0 )
	{
		return ['labels' => [], 'gamesPerDay' => [], 'bigGamesPerDay' => []];
	}

	$firstGameDate = $games[0]->timestamp;
	$lastGameDate = $games[ $numGames - 1 ]->timestamp;
	$todayDate = time();

	$firstDay = getDaysSinceZero( $firstGameDate );
	$lastDay = getDaysSinceZero( $lastGameDate );
	$todayDay = getDaysSinceZero( $todayDate );

	$numDays = ($todayDay - $firstDay) + 1;

	$labels = [];

	$gamesPerDay = [];
	$bigGamesPerDay = [];
	for( $ii = 0; $ii < $numDays; ++$ii )
	{
		$gamesPerDay[ $firstDay + $ii ] = 0;
		$bigGamesPerDay[ $firstDay + $ii ] = 0;
		$labels[] = xAxisLabel( $firstGameDate + ($ii * 24 * 60 * 60) );
	}

	$BIG_GAME_SIZE = 3;

	$curDay = -1;
	$gamesThisDay = 0;
	$bigGamesThisDay = 0;
	foreach( $games as $game )
	{
		$day = getDaysSinceZero($game->timestamp);
		if($day != $curDay)
		{
			$gamesPerDay[$day] = $gamesThisDay;
			$bigGamesPerDay[$day] = $bigGamesThisDay;

			$curDay = $day;
			$gamesThisDay = 0;
			$bigGamesThisDay = 0;
		}

		$gamesThisDay += 1;
		if($game->numPlayers >= $BIG_GAME_SIZE) {
			$bigGamesThisDay += 1;
		}
	}

	// Save the last day
	$gamesPerDay[$lastDay] = $gamesThisDay;

	$data['labels'] = $labels;
	$data['gamesPerDay'] = array_values($gamesPerDay);
	$data['bigGamesPerDay'] = array_values($bigGamesPerDay);

	return $data;
}