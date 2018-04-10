<?php

$projectName = "Wikivacaciones 2017";
$categoryName = "Wikivacaciones_2017";
$projectURL = "http://wikimediacolombia.org/vacaciones";
$projectLogo = "https://upload.wikimedia.org/wikipedia/commons/thumb/9/91/Wikivacaciones.png/100px-Wikivacaciones.png";

?>

<!DOCTYPE html>
    <html>
    <head>
        <title>Estadísticas de <?php echo $projectName; ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="wlm.css" />
    </head>
    <body>

    <center>
    <p style="font-size:small">Autor, compositor e intérprete: <a href="http://racso.co">Racso</a></p>
    <hr />



<?php

if (!isset($db)) $db = new SQLite3('db');
	
if (!$db) {
	die("No se encontró el archivo de base de datos.");
}

include_once("updateCache.php");

//== Cargar datos de los municipios colombianos ==
$monuments = array();
$monuments["?"] = array('id'=>"?", 'departamento'=>'?', 'municipio' => '?');

$res = $db->query("SELECT id,topName,name FROM places");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
	$monuments[$r['id']] = array('id'=>$r['id'],'departamento'=>$r['topName'],'municipio'=>$r['name']);
}

if (key_exists('gallery',$_GET))
    exec_gallery ();
else
    exec_statistics();
echo '</body>
    </html>';
exit();


function exec_statistics()
//EJECUTA EL MÓDULO DE IMPRIMIR ESTADÍSTICAS DE LA COMPETENCIA
{
    global $db, $monuments, $projectName,$projectLogo,$projectURL;
    //== Calcular datos ==

    //=== Estadísticas básicas ===
    //Total de imágenes subidas
    $out_total_subidas = $db->querySingle("SELECT COUNT(*) FROM photos;");
    
    //=== Estadísticas por persona ===
	$out_datos_personas = array();
    $res = $db->query("SELECT author,COUNT(*) total,COUNT(DISTINCT place) places FROM photos GROUP BY author ORDER BY total DESC,places DESC");
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$out_datos_personas[$r['author']] = array('total'=>$r['total'],'places'=>$r['places']);
	}

    //=== Estadísticas por departamento ===
    $out_datos_departamentos = array();
	$res = $db->query("select ifnull(topname,'?') topName,count(*) total,count(distinct name) places from photos left outer join places on photos.place=places.id group by topname order by total desc,places desc");
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$out_datos_departamentos[$r['topName']] = array('total'=>$r['total'],'places'=>$r['places']);
	}
    
    //=== Estadísticas por municipio ===
    $out_datos_municipios = array();
	$res = $db->query("SELECT ifnull(id,'?') id,ifnull(name,'?') name,COUNT(*) total, COUNT(DISTINCT author) people
FROM photos LEFT OUTER JOIN places ON photos.place=places.id GROUP BY id,name ORDER BY total DESC,people DESC");
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$out_datos_municipios[$r['id']] = array('name'=>$r['name'],'total'=>$r['total'],'people'=>$r['people']);
	}
    
    //== Generar textos de estadísticas ==

    $out_estadisticas_monumentos = '<table class="wikitable" style="text-align: center;">
    <tr><th>Código</th><th>Municipio</th><th>Fotos totales</th><th>Participantes</th></tr>';
    foreach ($out_datos_municipios as $id => $monumento)
        $out_estadisticas_monumentos .= '<tr><td><a href=".?gallery&municipio='.$id.'">'.$id.'</a></td><td>'.$monumento['name'].'</td><td>'.$monumento['total'].'</td><td>'.$monumento['people'].'</td></tr>';
    $out_estadisticas_monumentos .= '</table>';

    $out_estadisticas_departamentos = '<table class="wikitable" style="text-align: center;">
    <tr><th>Departamento</th><th>Fotos totales</th><th>Municipios<br />fotografiados</th></tr>';
    foreach ($out_datos_departamentos as $nombre=>$departamento)
        $out_estadisticas_departamentos .= '<tr><td><a href=".?gallery&departamento='.$nombre.'">'.$nombre.'</a></td><td>'.$departamento['total'].'</td><td>'.$departamento['places'].'</td></tr>';
    $out_estadisticas_departamentos .= '</table>';

    $out_estadisticas_personas = '<table class="wikitable" style="text-align: center;">
    <tr><th>Participante</th><th>Fotos totales</th><th>Municipios<br />fotografiados</th></tr>';
    foreach ($out_datos_personas as $nombre=>$persona)
        $out_estadisticas_personas .= '<tr><td><a href=".?gallery&participante='.rawurlencode ($nombre).'">'.$nombre.'</a></td><td>'.$persona['total'].'</td><td>'.$persona['places'].'</td></tr>';
    $out_estadisticas_personas .= '</table>';

    $out_estadisticas_basicas = '<div style="border:1px dotted; display: inline-block; padding: 15px;" >
        <p style="font-size:small;">Hasta el momento,</p>
        <p style="font-size:large;font-weight:bold;">'.sizeof($out_datos_personas).' participantes</p>
        <p style="font-size:small;">han subido</p>
        <p style="font-size:x-large;font-weight:bold;">'.$out_total_subidas.' fotografías</p>
        <p style="font-size:small;">con las cuales han captado</p>
        <p style="font-size:large;font-weight:bold;">'.sizeof($out_datos_municipios).' municipios</p>
        </div>';

    // == Imprimir ==
    echo '
    <table style="text-align:center; border:none;">
    <tr>
    <td><img src="'.$projectLogo.'" /></td>
    <td>
    <span style="font-size:xx-large"><a href="'.$projectURL.'">'.$projectName.'</a></span><br />
    <span style="font-size:large">Estadísticas y datos de interés</span>
    </td>
    </tr>
    </table>

    '.$out_estadisticas_basicas.'
    <h1>Los departamentos</h1>
    '.$out_estadisticas_departamentos.'
    <table style="border:none;" width="90%">
    <tr><td valign="top" >
    <center>
    <h1>Los participantes</h1>
    '.$out_estadisticas_personas.'
    </center>
    </td><td valign="top">
    <center>
    <h1>Los municipios</h1>
    '.$out_estadisticas_monumentos.'
    </center>
    </td></tr>
    </table>

    </center>
    ';

}

function exec_gallery()
//EJECUTA EL MÓDULO DE IMPRIMIR UNA GALERÍA DE IMÁGENES
{
//SELECT * FROM photos left outer join places on photos.place=places.id join thumbs WHERE thumbs.title=photos.title AND places.name is NULL

    global $db,$projectName,$projectLogo,$projectURL;
    
    $F_DEPTO = 'departamento'; $F_USER = 'participante'; $F_MONUM = 'municipio';
    $filter_type = null; $filter_value = null;
    $nombreGaleria = null;
    
    foreach (array($F_DEPTO,$F_MONUM,$F_USER) as $f)
            if (key_exists($f,$_GET)) { $filter_type = $f; $filter_value = $_GET[$f]; break;}
            
	$query = "SELECT photos.title title, url FROM photos LEFT JOIN places ON photos.place=places.id JOIN thumbs ON thumbs.title=photos.title WHERE ";

if ($filter_value==="?") {
			$query .= "topName is NULL";
			$nombreGaleria = "?";

}
else {

    switch ($filter_type) {
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
	while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
		$gallery[] = array('title'=>$r['title'],'url'=>$r['url']);
	}

    $cols = 5;
    $outGallery = '<table style="text-align: center; border: none;">';
    $curImg = 0;

    while ($curImg<sizeof($gallery))
    {
        $outGallery.= "<tr>";
        for ($i=0 ; $i<$cols; $i++)
        {
            $outGallery.= "<td>";
            if ($curImg<sizeof($gallery)) $outGallery.='<a href="http://commons.wikimedia.org/wiki/'.rawurlencode ($gallery[$curImg]['title']).'"><img src="'.$gallery[$curImg]['url'].'" /></a>';
            $outGallery.= "</td>";
            $curImg++;
        }
        $outGallery.= "</tr>";
    }
    $outGallery.= '</table>';


    // == Imprimir ==
    echo '
    <h1>'.$nombreGaleria.'<span style="font-size:60%;"><br/>Galería del '.$filter_type.'</span></h1>
    <p><a href=".">[ Volver a las estadísticas ]</a></p>
    '.$outGallery.'
    </center>';
}
        
   

?>