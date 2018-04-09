﻿<?php

$databasePath = "db";

session_start();

$db = requireDatabase($databasePath);

$_SESSION['user'] = getCurrentUser();
$USER = $_SESSION['user'];

if ($USER==="")
{
    showLoginForm();
	exit();
}

if (isset($_GET["logout"]))
{
	logout();
	exit();
}

if (isset($_POST["rating"]))
{
    updateRatingsFromPost();
	exit();
}

$images = loadImagesMetadata();
$scores = loadImagesScores();

$COUNT_OK = countScoresEqualTo(1);
$COUNT_SKIP = countScoresEqualTo(-1);
$COUNT_TODO = sizeof($images)-sizeof($scores);

$gallery = isset($_GET['skipped'])? getSkippedImages() : getUnseenImages();

shuffle($gallery);

showGallery($gallery);

exit();

function requireDatabase($path)
{
	$db = new SQLite3($path);
	if (!$db)
	{
		exit("No se encontró el archivo de base de datos.");
	}
	return $db;
}

function getCurrentUser()
{
	$loginUser = getLoginUser();

	if ($loginUser!==null) return $loginUser;
	if (isset($_SESSION["user"])) return $_SESSION["user"];
	return "";
}

function getLoginUser()
{
	global $db;
	if (!isset($_POST["token"])) return null;

	$token = $_POST['token'];
	return $db->querySingle("SELECT user FROM judgesData WHERE pass='".$token."'");
}

function showLoginForm()
{
?>
<!DOCTYPE html>
<html>
<head>
	<title>Juez</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body>
	<center>
		<form action="" method="post">
			<table>
				<tr>
					<td>Token</td>
					<td>
						<input type="password" name="token" size="20" />
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<center>
							<input type="submit" value="Iniciar sesión" />
						</center>
					</td>
				</tr>
			</table>
		</form>
	</center>
</body>
</html>
<?php
}

function logout()
{
	session_destroy();
	header("Location: index.php");
}

function updateRatingsFromPost()
{
	global $USER, $db;
	date_default_timezone_set('UTC');

	$query = $db->prepare('INSERT INTO judges (photoid, judge, score, timestamp) VALUES (:id, :judge, :score, :time)');
    foreach($_POST["rating"] as $rating)
	{
		$query->reset();
		$query->bindValue(':id', key($rating), SQLITE3_TEXT);
		$query->bindValue(':score', $rating[key($rating)], SQLITE3_INTEGER);
		$query->bindValue(':time', date('Y-m-d H:i:s'), SQLITE3_TEXT);
		$query->bindValue(':judge', $USER, SQLITE3_TEXT);
		$resultado = $query->execute();
	}
}

function loadImagesMetadata()
{
	global $db;
	$images = array();
	$queryResult = $db->query("SELECT photos.title title, id, url FROM photos, thumbs, ids WHERE photos.title=thumbs.title AND photos.title=ids.title ORDER BY id");
	while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
		$row['url'] = preg_replace("!/[0-9]+px-!", "/500px-", $row['url']);
		$images[] = array('#'=>$row['id'], 'file'=>$row['title'], 'thumb'=>$row['url']);
	}
	return $images;
}

function loadImagesScores()
{
	global $USER, $db;
	$scores = array();
	$queryResult = $db->query("SELECT photoid, score FROM judges WHERE judge='".$USER."'");
	while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
		$scores[$row['photoid']] = $row['score'];
	}
	return $scores;
}

function countScoresEqualTo($value)
{
	global $scores;
	$total = 0;
	foreach ($scores as $score)
	{
		if ($score==$value) $total += 1;
	}
	return $total;
}

function getSkippedImages()
{
	global $images, $scores;
	$skippedImages = array();
	foreach ($images as $image) if (isset($scores[$image["#"]]) && $scores[$image["#"]]=="-1") $skippedImages[] = $image;
	return $skippedImages;
}

function getUnseenImages()
{
	global $images, $scores;
	$unseenImages = array();
	foreach ($images as $image) if (key_exists($image["#"], $scores)==false) $unseenImages[] = $image;
	return $unseenImages;
}

function showGallery($gallery)
{
	global $USER, $COUNT_OK, $COUNT_SKIP, $COUNT_TODO, $images;

	$curImg = 0;
	$cols = 2;
	$rows = 10;

?>
<!DOCTYPE html>
<html>
<head>
	<title>Calificación fotográfica</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="css.css" />
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
	<script type="text/javascript">

		var rating = {};

		$(document).ready(function () {
			$(".imageFrame").each(function (i, obj) {
				rating[$(this).attr("id")] = 0;
			});

			$(".imageFrame").click(function () {
				var image_id = $(this).attr("id");

				if (rating[image_id] != 1) {
					rating[image_id] = 1;
					$("#" + image_id).css({ "border-style": "solid", "border-width": "5px", "border-color": "#33CC33" });
				}
				else {
					rating[image_id] = 0;
					$("#" + image_id).css({ "border-style": "none" });
				}
			});
			$(".skipButton").click(function () {
				var image_id = $(this).attr("id");
				image_id = image_id.substr(1);

				if (rating[image_id] != -1) {
					rating[image_id] = -1;
					$("#" + image_id).css({ "border-style": "dashed", "border-width": "5px", "border-color": "#C0C0C0" });
				}
				else {
					rating[image_id] = 0;
					$("#" + image_id).css({ "border-style": "none" });
				}
			});
			$(".saveButton").click(function () {
				$.post(document.location.href, { "rating[]": rating }, function (data) {
					if (data != null) {
						location.reload(true);
						scroll(0, 0);
					}
				});
			});
		});
	</script>

</head>
<body>
	<center>
		<p>
			Juez: <?=$USER?> (<a href=".?logout">cerrar sesión</a>) · Aprobadas: <?=$COUNT_OK?> ·
			<a href=".?skipped">Saltadas</a>: <?=$COUNT_SKIP?> ·
			<a href=".">Por mirar</a>: <?=$COUNT_TODO?> de <?=sizeof($images)?>
		</p>
		<hr />
		<table style="text-align: center; border: none;">
			<?php while ($curImg<$cols*$rows): ?>
				<tr>
					<?php for ($i=0; $i<$cols; $i++): ?>
					<td>
						<?php if ($curImg<sizeof($gallery)): ?>
						<img src="<?=$gallery[$curImg]['thumb']?>" class="imageFrame" id="<?=$gallery[$curImg]['#']?>" />
						<br />
						[<a target="_blank" href="http://commons.wikimedia.org/wiki/<?=rawurlencode($gallery[$curImg]['file'])?>">ver</a>] 
						[<span class="skipButton" id="s<?=$gallery[$curImg]['#']?>">saltar</span>]
						<br />
						<?php endif; ?>
						<?php $curImg += 1; ?>
					</td>
					<?php endfor; ?>
				</tr>
			<?php endwhile; ?>
		</table>
		<input type="button" class="saveButton" value="Guardar y ver más" />
	</center>
</body>
</html>';

<?php

}

?>