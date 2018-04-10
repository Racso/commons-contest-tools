<?php

$databasePath = "db";
date_default_timezone_set('America/Bogota');

if (!isset($categoryName))
{
	exit('La variable $categoryName debe establecerse antes de ejecutar la actualización de caché.' );
}

$isAdmin = isset($_GET["admin"]) && $_GET["admin"]==="1";
$forceRefresh = isset($_GET["refresh"]) && $_GET["refresh"]==="1";

if (!isset($db)) $db = new SQLite3($databasePath);

if (!$db) {
	exit("No se encontró el archivo de base de datos.");
}

if ($forceRefresh)
{
	clearDatabaseCache();
}

$cacheDate = getCacheDate();
showAdminInfo("Iniciando actualizacion. Ultimo timestamp: ".$cacheDate."<br>");

$newImages = getImagesFromCommons($cacheDate);

showAdminInfo("Fotos nuevas: ".sizeof($newImages->images)."<br>");
showAdminInfo("Nuevo timestamp de la cache: ".$newImages->lastTimestamp."<br>");

if (sizeof($newImages->images)>0)
{
	updateDatabaseCache($newImages);
}
else
{
	showAdminInfo("Sin cambios.<br>");
}

showAdminInfo("Actualización terminada.<br>");

function showAdminInfo($message)
{
	global $isAdmin;
	if ($isAdmin)
	{
		echo $message;
	}
}

function clearDatabaseCache()
{
	global $db;
	showAdminInfo("Refrescando toda la base de datos.<br>");
    $db->query("DELETE FROM photos");
    $db->query("DELETE FROM thumbs");
    $db->query("UPDATE metadata SET value='' WHERE name IN ('photosDate','thumbsDate')");
}

function getCacheDate()
{
	global $db;
	$cacheDate = $db->querySingle("SELECT value FROM metadata WHERE name='photosDate'");
	return $cacheDate!==NULL? $cacheDate : "";
}

function getImagesFromCommons($cacheDate) : ImagesData
{
	global $categoryName;

	$loadedData = new ImagesData();

	require_once( 'RacsoBot2.php' );
	$bot = new RacsoBot2();

	$loginResult = $bot->login('BOTzilla','contrasena','commons','wikimedia');

	showAdminInfo("Login hecho? " . ($loginResult ? "S":"N")."<br>");

	if (!$loginResult)
	{
		return $loadedData;
	}

	$continueToken = "";

	ini_set('max_execution_time', 120);

	do
	{
		$params = array(
			'action'=>'query',
			'prop'=>'imageinfo|revisions',
			'format'=>'json',
			'rvprop'=>'timestamp|content',
			'iiprop'=>'url',
			'iiurlwidth'=>'180',
			'iiurlheight'=>'180',
			'rawcontinue'=>'',
			'generator'=>'categorymembers',
			'gcmtitle'=>"Category:".$categoryName,
			'gcmsort'=>'timestamp',
			'gcmdir'=>'newer',
			'gcmlimit'=>50);
		if ($cacheDate!=="")
		{
			$params['gcmstart']=$cacheDate;
		}
		if ($continueToken!=="")
		{
			$params['gcmcontinue']=$continueToken;
		}

		$data = $bot->callAPI($params);
		showAdminInfo("Datos recibidos: ".sizeof($data)."<br>");
		if (isset($data['query']['pages']))
		{
			showAdminInfo("Datos recibidos de ".sizeof($data['query']['pages'])." archivos.<br>");

			$continueToken = isset($data['query-continue']['categorymembers']['gcmcontinue'])? $continueToken = $data['query-continue']['categorymembers']['gcmcontinue'] : "";

			foreach ($data['query']['pages'] as $page)
			{
				$tmpTimestamp = $page['revisions'][0]['timestamp'];
				if ($tmpTimestamp>$loadedData->lastTimestamp) $loadedData->lastTimestamp = $tmpTimestamp;
				if ($loadedData->lastTimestamp<=$cacheDate) continue;

				preg_match('!\{\{Tomada[ _]en[ _]Colombia\|([0-9]{4,6})\}\}!',$page['revisions'][0]['*'],$id);
				$id = isset($id[1])? $id[1] : "";
				preg_match('!author=\[\[User:(.*?)\|.*?\]\]!',$page['revisions'][0]['*'],$author);
				$author = isset($author[1])? $author[1] : "?";
				$thumbURL = isset($page['imageinfo'][0]['thumburl'])? $page['imageinfo'][0]['thumburl'] : "";
				$loadedData->images[] = array('titulo' => $page['title'], 'id' => $id, 'autor' => $author, 'thumb' => $thumbURL);
			}
		}
	} while ($continueToken!=="");
	return $loadedData;
}

function updateDatabaseCache(ImagesData $imagesData)
{
	global $db;
	$db->query("BEGIN TRANSACTION;");
	$statement = $db->prepare('INSERT OR IGNORE INTO photos (title,author,place) VALUES (:title,:author,:place);');
	$statementThumb = $db->prepare('INSERT OR IGNORE INTO thumbs (title,url) VALUES (:title,:url);');
	$statementID = $db->prepare('INSERT OR IGNORE INTO ids (title) VALUES (:title);');
	foreach ($imagesData->images as $k => $v) {
		$statement->reset();
		$statement->clear();
		$statement->bindValue(':title', $v["titulo"]);
		$statement->bindValue(':author', $v["autor"]);
		$statement->bindValue(':place', $v["id"]);
		$statement->execute();

		$statementThumb->reset();
		$statementThumb->clear();
		$statementThumb->bindValue(':title',$v["titulo"]);
		$statementThumb->bindValue(':url',$v["thumb"]);
		$statementThumb->execute();

		$statementID->reset();
		$statementID->clear();
		$statementID->bindValue(':title',$v["titulo"]);
		$statementID->execute();
	}
	$statement->close();
	$statementThumb->close();
	$statementID->close();
	$db->query("UPDATE metadata SET value = '".$imagesData->lastTimestamp."' WHERE name='photosDate';");
	$db->query("COMMIT;");
}

class ImagesData
{
	public $images = array();
	public $lastTimestamp = "";
}

?>