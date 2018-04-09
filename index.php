<?php

session_start();

if ($db===null) $db = new SQLite3('../stats/db');
	
if (!$db) {
	die("No se encontró el archivo de base de datos.");
}

if ($_POST['token']!=null){
    //Está iniciando sesión. Verificar token.
    $_SESSION['user'] = "";

    $token = $_POST['token'];
    $_SESSION['user'] = $db->querySingle("SELECT user FROM judgesData WHERE pass='".$token."'");
}

$USER = $_SESSION['user'];

if ($USER =="" || $USER==null){
    echo '
		<!DOCTYPE html>
		<html>
		  <head>
			<title>Juez</title>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		  </head>
		  <body>
				<center>
				<form action="" method="post">
				<table>
				<tr><td>Token</td><td><input type="password" name="token" size="20"/></td></tr>
				<tr><td colspan="2"><center><input type="submit" value="Iniciar sesión" /></center></td></tr>
				</form>
				</center>
		  </body>
		</html>';
    exit();
}  

if (key_exists("rating",$_POST)){
    date_default_timezone_set('UTC');
	$sentencia = $db->prepare('INSERT INTO judges (photoid,judge,score,timestamp) VALUES (:id,:judge,:score,:time);');
    foreach($_POST["rating"] as $rating){
		$sentencia->reset();
		$sentencia->bindValue(':id', key($rating),SQLITE3_TEXT);
		$sentencia->bindValue(':score', $rating[key($rating)],SQLITE3_INTEGER);
		$sentencia->bindValue(':time', date('Y-m-d H:i:s'),SQLITE3_TEXT);
		$sentencia->bindValue(':judge', $USER,SQLITE3_TEXT);
		$resultado = $sentencia->execute();
	}
    exit();
}

$images = array();
$res = $db->query("SELECT photos.title title,id,url FROM photos,thumbs,ids WHERE photos.title=thumbs.title AND photos.title=ids.title
ORDER BY id");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
	$r['url'] = preg_replace("!/[0-9]+px-!","/500px-",$r['url']);
	$images[] = array('#'=>$r['id'],'file'=>$r['title'],'thumb'=>$r['url']);
}

$scores = array();
$res = $db->query("SELECT photoid,score FROM judges WHERE judge='".$USER."'");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
	$scores[$r['photoid']] = $r['score'];
}

//¡Sacar algunos datos de estado!
$COUNT_OK = 0;
foreach ($scores as $score) if ($score==1) $COUNT_OK++;
$COUNT_SKIP = 0;
foreach ($scores as $score) if ($score==-1) $COUNT_SKIP++;
$COUNT_TODO = sizeof($images)-sizeof($scores);

//¡Filtrar las imágenes según lo solicitado (por calificar, aprobadas, reprobadas, saltadas)!
$cols = 2;
$rows = 10;
$gallery = array();
if (key_exists('skipped',$_GET))
{foreach ($images as $image) if ($scores[$image["#"]]=="-1") $gallery[] = $image;}
else
{foreach ($images as $image) if (key_exists($image["#"], $scores)==false) $gallery[] = $image;}

shuffle($gallery);

$outGallery = '
<table style="text-align: center; border: none;">
';

$curImg = 0;

while ($curImg<$cols*$rows)
{
    $outGallery.= "<tr>";
    for ($i=0 ; $i<$cols; $i++)
    {
        $outGallery.= "<td>";
        if ($curImg<sizeof($gallery)){
            $outGallery.='<img src="'.$gallery[$curImg]['thumb'].'" class="imageFrame" id="'.$gallery[$curImg]['#'].'"/><br />';
            $outGallery.='[<a target="_blank" href="http://commons.wikimedia.org/wiki/'. rawurlencode($gallery[$curImg]['file']).'">ver</a>] ';
            $outGallery.='[<span class="skipButton" id="s'.$gallery[$curImg]['#'].'"/>saltar</span>]<br />';
            $outGallery.= "</td>";
        }
        $curImg++;
    }
    $outGallery.= "</tr>";
}
$outGallery.= '</table>';


    // == Imprimir ==
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Calificación de Wiki Loves Monuments Colombia</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="wlm.css" />
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
	<script type="text/javascript">
        
        var rating = {};
        
        $(document).ready(function()
        {
            $(".imageFrame").each(function(i,obj){
                rating[$(this).attr("id")]=0;
            });

            $(".imageFrame").click(function()
            {
                var image_id = $(this).attr("id");
                
                if (rating[image_id]!=1)
                {
                    rating[image_id] = 1;
                    $("#"+image_id).css({"border-style":"solid","border-width":"5px","border-color":"#33CC33"});
                }
                else
                {
                    rating[image_id] = 0;
                    $("#"+image_id).css({"border-style":"none"});
                }
            });
            $(".skipButton").click(function()
            {
                var image_id = $(this).attr("id");
                image_id = image_id.substr(1);
                
                if (rating[image_id]!=-1)
                {
                    rating[image_id] = -1;
                    $("#"+image_id).css({"border-style":"dashed","border-width":"5px","border-color":"#C0C0C0"});
                }
                else
                {
                    rating[image_id] = 0;
                    $("#"+image_id).css({"border-style":"none"});
                }
            });
            $(".saveButton").click(function()
            {
                $.post(document.location.href,{"rating[]":rating}, function(data)
                    {
                        if (data != null){
                            location.reload(true);
                            scroll(0,0);
                        }
                    });
                
            });


        });
        </script>        

    </head>
    <body>

    <center>
    <p style="font-size:small">Juez: '.$USER.' (<a href="logout.php">cerrar sesión</a>) · Aprobadas: '.$COUNT_OK.' · <a href=".?skipped">Saltadas</a>: '.$COUNT_SKIP.' · <a href=".">Por mirar</a>: '.$COUNT_TODO.' de '.sizeof($images).'</p>
    <hr />
    '.$outGallery.'
    <input type="button" class="saveButton" value="Guardar y ver más" />
    </center>
    </body>
    </html>';

?>