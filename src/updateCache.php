<?php

$isAdmin = ($_GET["admin"]==="1");
$forceRefresh = ($_GET["refresh"]==="1");
$isUpdating = true;

date_default_timezone_set('America/Bogota');

if ($categoryName===null) $categoryName = "Wikivacaciones_2016";

if ($db===null) $db = new SQLite3('db');
	
if (!$db) {
	die("No se encontró el archivo de base de datos.");
}

if ($forceRefresh) {
    if ($isAdmin) echo "Refrescando toda la base de datos.<br>";
    $db->query("DELETE FROM photos;");
    $db->query("DELETE FROM thumbs;");
    $db->query("UPDATE metadata SET value='' WHERE name IN ('photosDate','thumbsDate');");
}

$newImages = array();

$cacheDate = $db->querySingle("SELECT value FROM metadata WHERE name='photosDate'");
if ($cacheDate===NULL) $cacheDate="";

if ($isAdmin) echo "Iniciando actualizacion. Ultimo timestamp: ".$cacheDate."<br>";

if ($isUpdating) {
	require_once( 'RacsoBot2.php' );
	$bot = new RacsoBot2();

	$loginResult = $bot->login('BOTzilla','contrasena','commons','wikimedia');

	if ($isAdmin) echo "Login hecho? " . ($loginResult ? "S":"N")."<br>";

	if ($loginResult) {
		$continueToken = "";
		$lastTimestamp = "";

		do{
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
			if ($cacheDate!=="") {
				$params['gcmstart']=$cacheDate;
			}
			if ($continueToken!=="") {
				$params['gcmcontinue']=$continueToken;
			}

			$data = $bot->callAPI($params);
			if ($isAdmin) echo "Datos recibidos: ".sizeof($data)."<br>";
			if ($isAdmin) echo "Datos recibidos de ".sizeof($data['query']['pages'])." archivos.<br>";

			if (key_exists('query-continue',$data)) {
				$continueToken = $data['query-continue']['categorymembers']['gcmcontinue'];
			}
			else {
				$continueToken = "";
			}
			
			foreach ($data['query']['pages'] as $page){
				$tmpTimestamp = $page['revisions'][0]['timestamp'];
				if ($tmpTimestamp>$lastTimestamp) $lastTimestamp = $tmpTimestamp;

				if ($lastTimestamp<=$cacheDate) continue; //Saltarse los que ya hayan sido incluidos en la última caché.
				preg_match('!\{\{Tomada[ _]en[ _]Colombia\|([0-9]{4,6})\}\}!',$page['revisions'][0]['*'],$id);
				$id = $id[1];
				preg_match('!author=\[\[User:(.*?)\|.*?\]\]!',$page['revisions'][0]['*'],$author);
				$author = $author[1];
				if ($author==="" || $author===null) $author="?";
				$thumbURL = $page['imageinfo'][0]['thumburl'];
				$images[] = array('titulo' => $page['title'], 'id' => $id, 'autor' => $author, 'thumb' => $thumbURL);
			}

		} while ($continueToken!=="");
	}
}

if ($isAdmin) echo "Fotos nuevas: ".sizeof($images)."<br>";
if ($isAdmin) echo "Nuevo timestamp de la cache: ".$lastTimestamp."<br>";

if (sizeof($images)>0) {
	$result = $db->query("BEGIN TRANSACTION;");
	$statement = $db->prepare('INSERT OR IGNORE INTO photos (title,author,place) VALUES (:title,:author,:place);');
	$statementThumb = $db->prepare('INSERT OR IGNORE INTO thumbs (title,url) VALUES (:title,:url);');
	$statementID = $db->prepare('INSERT OR IGNORE INTO ids (title) VALUES (:title);');
	foreach ($images as $k => $v) {
		$statement->reset();
		$statement->clear();
		$statement->bindValue(':title', $v["titulo"]);
		$statement->bindValue(':author', $v["autor"]);
		$statement->bindValue(':place', $v["id"]);
		$result = $statement->execute();

		$statementThumb->reset();
		$statementThumb->clear();
		$statementThumb->bindValue(':title',$v["titulo"]);
		$statementThumb->bindValue(':url',$v["thumb"]);
		$result = $statementThumb->execute();

		$statementID->reset();
		$statementID->clear();
		$statementID->bindValue(':title',$v["titulo"]);
		$result = $statementID->execute();
	}
	$statement->close();
	$statementThumb->close();
	$statementID->close();
	$result = $db->query("UPDATE metadata SET value = '".$lastTimestamp."' WHERE name='photosDate';");
	$result = $db->query("COMMIT;");
}
else {
	if ($isAdmin) echo "Sin cambios.<br>";
}

if ($isAdmin) echo "Actualización terminada.<br>";

?>