<?php
error_reporting(E_ERROR);
header("access-control-allow-origin: *");
define("TILE_SIZE", 256);
require_once "parsers.php";

if (!isset($_REQUEST["x"]) || !isset($_REQUEST["y"]) || !isset($_REQUEST["z"])) {
    die("Missing Parameter");
}

$x = $_REQUEST["x"];
$y = $_REQUEST["y"];
$z = $_REQUEST["z"];

if (!is_numeric($x) || !is_numeric($y) || !is_numeric($z)) {
    die("Invalid Parameter");
}
$x = (int)$x;
$y = (int)$y;
$z = (int)$z;

function tile2lat($y, $z)
{
    $n = pi() - (2 * pi() * $y) / pow(2, $z);
    return (180 / pi()) * atan(0.5 * (exp($n) - exp(-$n)));
}
function tile2long($x, $z)
{
    return ($x / pow(2, $z)) * 360 - 180;
}
$lat_begin = tile2lat($y, $z);
$lon_begin = tile2long($x, $z);
$lat_end = tile2lat($y + 1, $z);
$lon_end = tile2long($x + 1, $z);
function get_xy($lat, $lon, $lat_begin, $lon_begin, $lat_end, $lon_end)
{
    $x = (($lon - $lon_begin) / ($lon_end - $lon_begin)) * TILE_SIZE;
    $y = (($lat - $lat_begin) / ($lat_end - $lat_begin)) * TILE_SIZE;
    return array("x" => $x, "y" => $y);
}

require_once "settings.php";

$conn = pg_pconnect($dbconn_str)
    or die('Could not connect: ' . pg_last_error());

$sub = get_appearance($z);
$query = 'SELECT "z_order","highway","construction",ST_AsGeoJSON(ST_Transform(way,4326)) from planet_osm_line where ST_Intersects(ST_TileEnvelope(' . $z . ', ' . $x . ', ' . $y . '),way) AND highway IS NOT NULL' . $sub . ' LIMIT 2000;';
$result = pg_query($conn, $query);
if (!$result) {
    print(pg_last_error());
}
$arr = pg_fetch_all($result);
//print_r($arr);

$res = [];

for ($i = 0; $i < count($arr); $i++) {
    $resp = array();
    $resp["z_order"] = $arr[$i]["z_order"];
    if ($resp["z_order"] == null || !is_numeric($resp["z_order"])) $resp["z_order"] = 33;
    else $resp["z_order"] = (int)$resp["z_order"];
    $resp["path"] = json_decode($arr[$i]["st_asgeojson"], true);
    $resp["highway"] = $arr[$i]["highway"];
    $resp["construction"] = $arr[$i]["construction"];
    $res[] = $resp;
}

sort($res);

require_once __DIR__ . '/php-svg/autoloader.php';


use SVG\SVG;
use SVG\Nodes\Shapes\SVGPolyline;

$image = new SVG(TILE_SIZE, TILE_SIZE);
$doc = $image->getDocument();

for ($i = 0; $i < count($res); $i++) {
    $pol = $res[$i];
    $polyline = new SVGPolyline();
    if ($pol["path"]["type"] != "LineString") {
        continue;
    }
    for ($j = 0; $j < count($pol["path"]["coordinates"]); $j++) {
        $coord = $pol["path"]["coordinates"][$j];
        $c = get_xy($coord[1], $coord[0], $lat_begin, $lon_begin, $lat_end, $lon_end);
        $polyline->addPoint($c["x"], $c["y"]);
    }
    $construction = $pol["highway"] == "construction";

    $color = get_color_road($construction ? $pol["construction"] : $pol["highway"]);
    $polyline->setStyle("fill", 'none');
    $polyline->setStyle("stroke", $color);
    $polyline->setStyle("stroke-width", get_width_road($construction ? $pol["construction"] : $pol["highway"], $z));
    $polyline->setStyle("stroke-linejoin", "round");
    if ($construction) {
        $polyline->setStyle("stroke-dasharray", "8 4");
    }
    $doc->addChild($polyline);
}

header('Content-Type: image/svg+xml');
echo $image;
