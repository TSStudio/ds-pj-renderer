<?php
error_reporting(E_ERROR);
header("access-control-allow-origin: *");
define("TILE_SIZE", 256);

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
function tile2latarc($y, $z)
{
    $n = pi() - (2 * pi() * $y) / pow(2, $z);
    return atan(0.5 * (exp($n) - exp(-$n)));
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

function lenoftile1o100($y, $z)
{
    return 400750 * cos(tile2latarc($y, $z)) / pow(2, $z);
}

function sizeoftile1o10000($y, $z)
{
    $l = lenoftile1o100($y, $z);
    return $l * $l;
}

require_once "settings.php";

$s = sizeoftile1o10000($y, $z);
$conn = pg_pconnect($dbconn_str)
    or die('Could not connect: ' . pg_last_error());

$query = 'SELECT "building","landuse","natural","leisure","amenity","name",ST_AsGeoJSON(ST_Transform(way,4326)) from planet_osm_polygon where ST_Intersects(ST_TileEnvelope($1, $2, $3),way) AND way_area>$4 LIMIT 2000;';
pg_prepare($conn, "bq1", $query);
$result = pg_execute($conn, "bq1", [$z, $x, $y, $s * 6]);
if (!$result) {
    print(pg_last_error());
}
$arr = pg_fetch_all($result);
//print_r($arr);

$res = [];

require_once "parsers.php";

for ($i = 0; $i < count($arr); $i++) {
    $resp = array();
    $resp["priority"] = get_priority($arr[$i]["building"],  $arr[$i]["landuse"],  $arr[$i]["natural"],  $arr[$i]["leisure"],  $arr[$i]["amenity"]);
    $resp["path"] = json_decode($arr[$i]["st_asgeojson"], true);
    $resp["building"] = $arr[$i]["building"];
    $resp["landuse"] = $arr[$i]["landuse"];
    $resp["natural"] = $arr[$i]["natural"];
    $resp["leisure"] = $arr[$i]["leisure"];
    $resp["amenity"] = $arr[$i]["amenity"];
    $resp["name"] = $arr[$i]["name"];
    if (is_null($resp["building"]) && is_null($resp["landuse"]) && is_null($resp["natural"]) && is_null($resp["leisure"]) && is_null($resp["amenity"])) {
        continue;
    }

    $res[] = $resp;
}

sort($res);



require_once __DIR__ . '/php-svg/autoloader.php';

use SVG\SVG;
use SVG\Nodes\Shapes\SVGPolygon;
use SVG\Nodes\Texts\SVGText;

$image = new SVG(TILE_SIZE, TILE_SIZE);
$doc = $image->getDocument();

for ($i = 0; $i < count($res); $i++) {
    $pol = $res[$i];
    if ($pol["path"]["type"] != "Polygon") {
        continue;
    }
    if ($pol["name"] == null) {
        continue;
    }
    if (is_null($pol["building"]) && is_null($pol["landuse"]) && is_null($pol["natural"]) && is_null($pol["leisure"]) && is_null($pol["amenity"])) {
        continue;
    }
    $x_min = 256;
    $x_max = 0;
    $y_min = 256;
    $y_max = 0;
    for ($j = 0; $j < count($pol["path"]["coordinates"][0]); $j++) {
        $coord = $pol["path"]["coordinates"][0][$j];
        $c = get_xy($coord[1], $coord[0], $lat_begin, $lon_begin, $lat_end, $lon_end);
        if ($c["x"] < $x_min) {
            $x_min = $c["x"];
        }
        if ($c["x"] > $x_max) {
            $x_max = $c["x"];
        }
        if ($c["y"] < $y_min) {
            $y_min = $c["y"];
        }
        if ($c["y"] > $y_max) {
            $y_max = $c["y"];
        }
    }
    if ($pol["name"] != null) {
        $text = new SVGText($pol["name"], ($x_min + $x_max) / 2, ($y_min + $y_max) / 2);
        $text->setFontFamily('Arial');
        $text->setFontSize('10');
        $doc->addChild($text);
    }
}

$doc->setStyle('overflow', 'visible');
header('Content-Type: image/svg+xml');
echo $image;
