<?php
error_reporting(E_ERROR);
define("MARGIN", 5);
header("access-control-allow-origin: *");

if (!isset($_REQUEST["lat_begin"]) || !isset($_REQUEST["lon_begin"]) || !isset($_REQUEST["lat_end"]) || !isset($_REQUEST["lon_end"]) || !isset($_REQUEST["width"]) || !isset($_REQUEST["height"])) {
    die("Missing Parameter");
}

$lat_begin = $_REQUEST["lat_begin"];
$lon_begin = $_REQUEST["lon_begin"];
$lat_end = $_REQUEST["lat_end"];
$lon_end = $_REQUEST["lon_end"];
$width = $_REQUEST["width"];
$height = $_REQUEST["height"];

if (!is_numeric($lat_begin) || !is_numeric($lon_begin) || !is_numeric($lat_end) || !is_numeric($lon_end) || !is_numeric($width) || !is_numeric($height)) {
    die("Invalid Parameter");
}
$lat_begin = (float)$lat_begin;
$lon_begin = (float)$lon_begin;
$lat_end = (float)$lat_end;
$lon_end = (float)$lon_end;
$width = (int)$width;
$height = (int)$height;


function get_area_size($lat_begin, $lon_begin, $lat_end, $lon_end)
{
    $earth_radius = 6378137; // Earth radius in meters
    $lat_diff = deg2rad($lat_end - $lat_begin);
    $lon_diff = deg2rad($lon_end - $lon_begin);
    $lat_avg = deg2rad(($lat_begin + $lat_end) / 2);
    $width = $earth_radius * $lon_diff * cos($lat_avg);
    $height = $earth_radius * $lat_diff;
    return array('width' => $width, 'height' => $height);
}

$size = get_area_size($lat_begin, $lon_begin, $lat_end, $lon_end);
$area_size = $size['width'] * $size['height'];
function get_xy($lat, $lon, $lat_begin, $lon_begin, $lat_end, $lon_end, $width, $height)
{
    $x = (($lon - $lon_begin) / ($lon_end - $lon_begin)) * $width;
    $y = (($lat - $lat_begin) / ($lat_end - $lat_begin)) * $height;
    return array("x" => $x, "y" => $y);
}

require_once "settings.php";

$conn = pg_pconnect($dbconn_str)
    or die('Could not connect: ' . pg_last_error());

$query = 'SELECT "building","landuse","natural","leisure","amenity","name",Box2D(ST_Transform(way,4326)) FROM planet_osm_polygon WHERE ST_Intersects(ST_Transform(ST_MakeEnvelope($1, $2, $3, $4 ,4326), 3857),way) AND way_area>$5 AND "name" IS NOT NULL ORDER BY way_area DESC LIMIT 2000;';

pg_prepare($conn, "tqr4", $query);
$result = pg_execute($conn, "tqr4", [$lon_begin, $lat_begin, $lon_end, $lat_end, $area_size / 10000]);
if (!$result) {
    print(pg_last_error());
}
$arr = pg_fetch_all($result);
//print_r($arr);

$res = [];

require_once "parsers.php";

for ($i = 0; $i < count($arr); $i++) {
    $resp = array();
    $resp["bound"] = $arr[$i]["box2d"];
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


require_once __DIR__ . '/php-svg/autoloader.php';

use SVG\SVG;
use SVG\Nodes\Shapes\SVGPolygon;
use SVG\Nodes\Texts\SVGText;

$image = new SVG($width, $height);
$doc = $image->getDocument();

static $textBoxes = [];

for ($i = 0; $i < count($res); $i++) {
    $pol = $res[$i];
    if ($pol["name"] == null) {
        continue;
    }
    if (is_null($pol["building"]) && is_null($pol["landuse"]) && is_null($pol["natural"]) && is_null($pol["leisure"]) && is_null($pol["amenity"])) {
        continue;
    }
    //Bound alike BOX(116.5215969 39.88205650025122,116.540941 39.89148870025251)
    $bound = explode(",", $pol["bound"]);
    $bound[0] = substr($bound[0], 4);
    $bound[1] = substr($bound[1], 0, -1);
    $bound[0] = explode(" ", $bound[0]);
    $bound[1] = explode(" ", $bound[1]);
    $latlng_1 = get_xy($bound[0][1], $bound[0][0], $lat_begin, $lon_begin, $lat_end, $lon_end, $width, $height);
    $latlng_2 = get_xy($bound[1][1], $bound[1][0], $lat_begin, $lon_begin, $lat_end, $lon_end, $width, $height);
    $x_min = min($latlng_1["x"], $latlng_2["x"]);
    $x_max = max($latlng_1["x"], $latlng_2["x"]);
    $y_min = min($latlng_1["y"], $latlng_2["y"]);
    $y_max = max($latlng_1["y"], $latlng_2["y"]);


    if ($pol["name"] != null) {
        $x = ($x_min + $x_max) / 2;
        $y = ($y_min + $y_max) / 2;
        $fontSize = 10;
        $textWidth = strlen($pol["name"]) * $fontSize * 0.6;
        $textHeight = $fontSize;
        $newBox = [
            'x_min' => $x - $textWidth / 2 - MARGIN,
            'x_max' => $x + $textWidth / 2 + MARGIN,
            'y_min' => $y - $textHeight / 2 - MARGIN,
            'y_max' => $y + $textHeight / 2 + MARGIN
        ];
        $conflict = false;
        foreach ($textBoxes as $box) {
            if (
                $newBox['x_min'] < $box['x_max'] &&
                $newBox['x_max'] > $box['x_min'] &&
                $newBox['y_min'] < $box['y_max'] &&
                $newBox['y_max'] > $box['y_min']
            ) {
                $conflict = true;
                break;
            }
        }

        if (!$conflict) {
            $color = get_pol_text_color($pol["building"], $pol["landuse"], $pol["natural"], $pol["leisure"], $pol["amenity"]);
            $text = new SVGText($pol["name"], $x, $y);
            $text->setFontFamily('Arial');

            $text->setFontSize($fontSize);
            $text->setStyle('fill', $color);
            $text->setStyle('stroke', 'white');
            $text->setStyle('stroke-width', '0.5');
            $text->setStyle('paint-order', 'stroke');
            $text->setFontSize('11');
            $text->setStyle('text-anchor', 'middle');
            $text->setStyle('dominant-baseline', 'central');
            $doc->addChild($text);
            $textBoxes[] = $newBox;
        }
    }
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
