<?php
error_reporting(E_ERROR);
header("access-control-allow-origin: *");

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
    return atan(0.5 * (exp($n) - exp(-$n)));
}

function lenoftile1o10($y, $z)
{
    return 4007500 * cos(tile2lat($y, $z)) / pow(2, $z);
}

function sizeoftile1o1000($y, $z)
{
    $l = lenoftile1o10($y, $z);
    return $l * $l / 10;
}

require_once "settings.php";

$s = sizeoftile1o1000($y, $z);
$conn = pg_pconnect($dbconn_str)
    or die('Could not connect: ' . pg_last_error());

$query = 'SELECT "building","landuse","natural","leisure","amenity",ST_AsGeoJSON(ST_Transform(way,4326)) from planet_osm_polygon where ST_Intersects(ST_TileEnvelope($1, $2, $3),way) AND way_area>$4 LIMIT 2000;';
pg_prepare($conn, "q6", $query);
$result = pg_execute($conn, "q6", [$z, $x, $y, $s]);
if (!$result) {
    print(pg_last_error());
}
$arr = pg_fetch_all($result);
//print_r($arr);

$res = [];

for ($i = 0; $i < count($arr); $i++) {
    $resp = array();
    $resp["path"] = json_decode($arr[$i]["st_asgeojson"]);
    $resp["building"] = $arr[$i]["building"];
    $resp["landuse"] = $arr[$i]["landuse"];
    $resp["natural"] = $arr[$i]["natural"];
    $resp["leisure"] = $arr[$i]["leisure"];
    $resp["amenity"] = $arr[$i]["amenity"];
    $res[] = $resp;
}

print(json_encode($res));
