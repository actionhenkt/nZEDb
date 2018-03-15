<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nzedb\Category;
use nzedb\db\DB;
use nzedb\Games;

$pdo = new DB();

if (isset($argv[1]) && $argv[1] === 'true') {
	getOddGames();
} else {
	exit($pdo->log->error("\nThis script attempts to recategorize 150 games each run in 0day and ISO that have a match on giantbomb.\n"
					. "php $argv[0] true       ...:recategorize 0day/ISO games.\n"));
}

function getOddGames()
{
	global $pdo;
	$res = $pdo->query(
		'
				SELECT searchname, id, categories_id
				FROM releases
				WHERE nzbstatus = 1
				AND gamesinfo_id = 0
				AND categories_id BETWEEN ' . Category::PC_0DAY . ' AND ' . Category::PC_ISO . '
				ORDER BY postdate DESC LIMIT 150'
	);

	if ($res !== false) {
		$pdo->log->doEcho($pdo->log->header('Processing... 150 release(s).'));
		$gen = new Games(['Echo' => true, 'Settings' => $pdo]);

		//Match on 78% title
		$gen->matchPercentage = 78;
		foreach ($res as $arr) {
			$startTime = microtime(true);
			$usedgb = true;
			$gameInfo = $gen->parseTitle($arr['searchname']);
			if ($gameInfo !== false) {
				$pdo->log->doEcho(
							$pdo->log->headerOver('Looking up: ') .
							$pdo->log->primary($gameInfo['title'])
						);

				// Check for existing games entry.
				$gameCheck = $gen->getGamesInfoByName($gameInfo['title']);
				if ($gameCheck === false) {
					$gameId = $gen->updateGamesInfo($gameInfo);
					$usedgb = true;
					if ($gameId === false) {
						$gameId = -2;

						//If result is empty then set gamesinfo_id back to 0 so we can parse it at a later time.
						if ($gen->maxHitRequest === true) {
							$gameId = 0;
						}
					}
				} else {
					$gameId = $gameCheck['id'];
				}
				if ($gameId != -2 && $gameId != 0) {
					$arr['categories_id'] = Category::PC_GAMES;
				}

				$pdo->queryExec(sprintf('UPDATE releases SET gamesinfo_id = %d, categories_id = %d WHERE id = %d', $gameId, $arr['categories_id'], $arr['id']));
			} else {
				// Could not parse release title.
				$pdo->queryExec(sprintf('UPDATE releases SET gamesinfo_id = %d WHERE id = %d', -2, $arr['id']));
				echo '.';
			}
			// Sleep so not to flood giantbomb.
			$diff = floor((microtime(true) - $startTime) * 1000000);
			if ($gen->sleepTime * 1000 - $diff > 0 && $usedgb === true) {
				usleep((int)($gen->sleepTime * 1000 - $diff));
			}
		}
	} else {
		$pdo->log->doEcho($pdo->log->header('No games in 0day/ISO to process.'));
	}
}
