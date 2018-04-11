<?php

$projectName = "Wikivacaciones 2017";
$categoryName = "Wikivacaciones_2017";
$projectURL = "http://wikimediacolombia.org/vacaciones";
$projectLogo = "https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/Wikivacaciones.png/100px-Wikivacaciones.png";

if (!isset($db)) $db = new SQLite3('db');

if (!$db)
{
	die("No se encontró el archivo de base de datos.");
}

include_once("updateCache.php");

$monuments = loadMonumentsData();

showHeader();

if (isset($_GET['gallery']))
{
    showGallery();
}
else
{
    showStatistics();
}

showFooter();
exit();

function loadMonumentsData()
{
	global $db;

	$monuments = array();
	$monuments["?"] = array('id'=>"?", 'departamento'=>'?', 'municipio' => '?');

	$queryResult = $db->query("SELECT id, topName, name FROM places");
	while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
		$monuments[$row['id']] = array('id'=>$row['id'], 'departamento'=>$row['topName'], 'municipio'=>$row['name']);
	}

	return $monuments;
}

function showHeader()
{
?>

<!DOCTYPE html>
<html>
<head>
	<title>
		Estadísticas de <?php echo $projectName; ?>
	</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="wlm.css" />
</head>
<body>
    <center>
        <p style="font-size:small">
            Autor, compositor e intérprete:
            <a href="http://racso.co">Racso</a>
        </p>
        <hr />
	</center>
	<?php
}

function showGallery()
{
    global $db, $projectName, $projectLogo, $projectURL;

    $F_DEPTO = 'departamento'; $F_USER = 'participante'; $F_MONUM = 'municipio';
    $filter_type = null; $filter_value = null;
    $nombreGaleria = null;

    foreach (array($F_DEPTO,$F_MONUM,$F_USER) as $f)
	{
		if (isset($_GET[$f]))
		{
			$filter_type = $f;
			$filter_value = $_GET[$f];
			break;
		}
	}

	$query = "SELECT photos.title title, url FROM photos LEFT JOIN places ON photos.place=places.id JOIN thumbs ON thumbs.title=photos.title WHERE ";

	if ($filter_value==="?")
	{
		$query .= "topName is NULL";
		$nombreGaleria = "?";

	}
	else
	{
		switch ($filter_type)
		{
			case $F_DEPTO:
				$query .= "topName='".$filter_value."'";
				$nombreGaleria = $filter_value;
				break;
			case $F_USER:
				$query .= "author='".$filter_value."'";
				$nombreGaleria = $filter_value;
				break;
			case $F_MONUM:
				$query .= "place='".$filter_value."'";
				$nombreGaleria = $db->querySingle("SELECT name FROM places WHERE id='".$filter_value."'");
				break;
		}
	}
	$gallery = array();
	$res = $db->query($query);
	while ($r = $res->fetchArray(SQLITE3_ASSOC))
	{
		$gallery[] = array('title'=>$r['title'], 'url'=>$r['url']);
	}

    $cols = 5;
    $curImg = 0;

    ?>
	<center>
		<h1>
			<?=$nombreGaleria?>
			<span style="font-size:60%;">
				<br />Galería del <?=$filter_type?>
			</span>
		</h1>
		<p>
			<a href="?">[ Volver a las estadísticas ]</a>
		</p>
		<table style="text-align: center; border: none;">
			<?php while ($curImg<sizeof($gallery)): ?>
			<tr>
				<?php for ($i=0 ; $i<$cols; $i++): ?>
				<td>
					<?php if ($curImg<sizeof($gallery)): ?>
					<a href="http://commons.wikimedia.org/wiki/<?=rawurlencode($gallery[$curImg]['title'])?>" target="_blank">
						<img src="<?=$gallery[$curImg]['url']?>" />
					</a>
					<?php endif; ?>
				</td>
				<?php $curImg+=1;
					  endfor; ?>
			</tr>
			<?php endwhile; ?>
		</table>
	</center>';
	<?php
}

function showStatistics()
{
    global $db, $monuments, $projectName, $projectLogo, $projectURL;

    $totalNumberOfImages = $db->querySingle("SELECT COUNT(*) FROM photos;");

    $statsByParticipant = getStatsByParticipant();
	$statsByDepartment = getStatsByZone();
	$statsByLocation = getStatsByLocation();

	?>
	<center>
        <table style="text-align:center; border:none;">
            <tr>
                <td><img src="<?=$projectLogo?>" /></td>
                <td>
                    <span style="font-size:xx-large"><a href="<?=$projectURL?>"><?=$projectName?></a></span><br />
                    <span style="font-size:large">Estadísticas y datos de interés</span>
                </td>
            </tr>
        </table>
        <div style="border:1px dotted; display: inline-block; padding: 15px;">
            <p style="font-size:small;">Hasta el momento,</p>
            <p style="font-size:large;font-weight:bold;"><?=sizeof($statsByParticipant)?> participantes</p>
            <p style="font-size:small;">han subido</p>
            <p style="font-size:x-large;font-weight:bold;"><?=$totalNumberOfImages?> fotografías</p>
            <p style="font-size:small;">con las cuales han captado</p>
            <p style="font-size:large;font-weight:bold;"><?=sizeof($statsByLocation)?> municipios</p>
        </div>
        <h1>Los departamentos</h1>

		<table class="wikitable" style="text-align: center;">
            <tr><th>Departamento</th><th>Fotos totales</th><th>Municipios<br />fotografiados</th></tr>
			<?php foreach ($statsByDepartment as $nombre=>$departamento): ?>
            <tr><td><a href="?gallery&departamento=<?=$nombre?>"><?=$nombre?></a></td><td><?=$departamento['total']?></td><td><?=$departamento['places']?></td></tr>
			<?php endforeach; ?>
        </table>
        <table style="border:none;" width="90%">
            <tr>
                <td valign="top">
                    <center>
                        <h1>Los participantes</h1>
                        <table class="wikitable" style="text-align: center;">
                            <tr><th>Participante</th><th>Fotos totales</th><th>Municipios<br />fotografiados</th></tr>
							<?php foreach ($statsByParticipant as $nombre=>$persona): ?>
                            <tr><td><a href="?gallery&participante=<?=rawurlencode ($nombre)?>"><?=$nombre?></a></td><td><?=$persona['total']?></td><td><?=$persona['places']?></td></tr>
							<?php endforeach; ?>
                        </table>
                    </center>
                </td>
                <td valign="top">
                    <center>
                        <h1>Los municipios</h1>
                        <table class="wikitable" style="text-align: center;">
                            <tr><th>Código</th><th>Municipio</th><th>Fotos totales</th><th>Participantes</th></tr>
							<?php foreach ($statsByLocation as $id => $monumento): ?>
                            <tr><td><a href="?gallery&municipio=<?=$id?>"><?=$id?></a></td><td><?=$monumento['name']?></td><td><?=$monumento['total']?></td><td><?=$monumento['people']?></td></tr>
							<?php endforeach; ?>
                        </table>
                    </center>
                </td>
            </tr>
        </table>
    </center>
	<?php

}

function getStatsByParticipant()
{
	global $db;
	$stats = array();
    $queryResult = $db->query("SELECT author,COUNT(*) total,COUNT(DISTINCT place) places FROM photos GROUP BY author ORDER BY total DESC,places DESC");
    while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
		$stats[$row['author']] = array('total'=>$row['total'], 'places'=>$row['places']);
    }
	return $stats;
}

function getStatsByZone()
{
	global $db;
	$stats = array();
    $queryResult = $db->query("select ifnull(topname,'?') topName,count(*) total,count(distinct name) places from photos left outer join places on photos.place=places.id group by topname order by total desc,places desc");
    while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
		$stats[$row['topName']] = array('total'=>$row['total'], 'places'=>$row['places']);
    }
	return $stats;
}

function getStatsByLocation()
{
	global $db;
	$stats = array();
    $queryResult = $db->query("SELECT ifnull(id,'?') id,ifnull(name,'?') name,COUNT(*) total, COUNT(DISTINCT author) people
    FROM photos LEFT OUTER JOIN places ON photos.place=places.id GROUP BY id,name ORDER BY total DESC,people DESC");
    while ($row = $queryResult->fetchArray(SQLITE3_ASSOC))
	{
	    $stats[$row['id']] = array('name'=>$row['name'], 'total'=>$row['total'], 'people'=>$row['people']);
    }
	return $stats;
}

function showFooter()
{
	?>
	</body>
</html>
<?php
}


?>