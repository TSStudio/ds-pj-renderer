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

require_once "settings.php";

$conn = pg_pconnect($dbconn_str)
    or die('Could not connect: ' . pg_last_error());

$query = 'SELECT "z_order","highway",ST_AsGeoJSON(ST_Transform(way,4326)) from planet_osm_line where ST_Intersects(ST_TileEnvelope($1, $2, $3),way) AND highway IS NOT NULL LIMIT 2000;';
pg_prepare($conn, "rq2", $query);
$result = pg_execute($conn, "rq2", [$z, $x, $y]);
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
    $resp["path"] = json_decode($arr[$i]["st_asgeojson"]);
    $resp["highway"] = $arr[$i]["highway"];
    $res[] = $resp;
}

print(json_encode($res));
