<?php
error_reporting(E_ERROR);
header("access-control-allow-origin: *");
define("TILE_SIZE", 256);
define("VERSION", 2);

if (!isset($_REQUEST["x"]) || !isset($_REQUEST["y"]) || !isset($_REQUEST["z"])) {
    die("Missing Parameter");
}

$cached = true;
if (isset($_REQUEST["nocache"])) {
    $cached = false;
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
if ($cached) {
    $query = 'SELECT "data","version" FROM ONLY base_cached WHERE x=$1 AND y=$2 AND z=$3';
    pg_prepare($conn, "bqx2", $query);
    $result = pg_execute($conn, "bqx2", [$x, $y, $z]);
    if (!$result) {
        print(pg_last_error());
    }
    $arr = pg_fetch_all($result);
    if (count($arr) > 0) {
        $data = $arr[0]["data"];
        $version = $arr[0]["version"];
        if ($version == VERSION) {
            $data = $data;
            header('Content-Type: image/svg+xml');
            header('Cached: true');
            if (isset($_REQUEST["datauri"])) {
                header('Content-Type: text/plain');
                //data uri in body
                echo "data:image/svg+xml;base64," . base64_encode($data);
            } else {
                header('Content-Type: image/svg+xml');
                echo $data;
            }
            exit();
        }
    }
}

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
$txts = [];

for ($i = 0; $i < count($res); $i++) {
    $pol = $res[$i];
    $polygon = new SVGPolygon();
    if ($pol["path"]["type"] != "Polygon") {
        continue;
    }
    if (is_null($pol["building"]) && is_null($pol["landuse"]) && is_null($pol["natural"]) && is_null($pol["leisure"]) && is_null($pol["amenity"])) {
        continue;
    }
    for ($j = 0; $j < count($pol["path"]["coordinates"][0]); $j++) {
        $coord = $pol["path"]["coordinates"][0][$j];
        $c = get_xy($coord[1], $coord[0], $lat_begin, $lon_begin, $lat_end, $lon_end);
        $polygon->addPoint($c["x"], $c["y"]);
    }
    $color = get_color($pol["building"], $pol["landuse"], $pol["natural"], $pol["leisure"], $pol["amenity"]);
    $polygon->setStyle("fill", $color);
    $polygon->setStyle("stroke", 'none');
    $doc->addChild($polygon);
}


// echo $image;
if (isset($_REQUEST["datauri"])) {
    header('Content-Type: text/plain');
    //data uri in body
    echo "data:image/svg+xml;base64," . base64_encode($image);
} else {
    header('Content-Type: image/svg+xml');
    echo $image;
}

$image = $image;

$query = 'INSERT INTO base_cached (x,y,z,data,version) VALUES ($1,$2,$3,$4,$5) ON CONFLICT (x,y,z) DO UPDATE SET data=$4,version=$5';
pg_prepare($conn, "bq3", $query);
pg_execute($conn, "bq3", [$x, $y, $z, $image, VERSION]);
