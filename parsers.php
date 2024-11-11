<?php
function get_priority($building, $landuse, $natural, $leisure, $amenity)
{
    //lower is LOWER in layers
    if (!is_null($landuse)) {
        return 20;
    }
    if (!is_null($amenity)) {
        return 40;
    }
    if (!is_null($leisure)) {
        return 60;
    }
    if (!is_null($natural)) {
        return 80;
    }
    if (!is_null($building)) {
        return 100;
    }
}
function get_color($building, $landuse, $natural, $leisure, $amenity)
{
    if (!is_null($landuse)) {
        if ($landuse == "commercial" || $landuse == "retail") {
            return "#f8eefb";
        }
        if ($landuse == "residential") {
            return "#e4ebf2";
        }
        if ($landuse == "meadow") {
            return "#c3f1d7";
        }
    }
    if (!is_null($amenity)) {
        if ($amenity == "university" || $amenity == "school" || $amenity == "college") {
            return "#e9f6fa";
        }
        if ($amenity == "hospital" || $amenity == "clinic") {
            return "#faebf0";
        }
        if ($amenity == "exhibition_centre") {
            return "#f3efed";
        }
    }
    if (!is_null($leisure)) {
        return "#c3f1d7";
    }
    if (!is_null($natural)) {
        if ($natural == "water" || $natural == "strait") {
            return "#9ed7ff";
        }
    }
    if (!is_null($building)) {
        return "#ffffff";
    }
    //return "#000000"; //for test
    return "none";
}

function get_appearance($z)
{
    $possible = ["motorway", "motorway_link", "trunk", "trunk_link", "primary", "primary_link", "secondary", "secondary_link", "tertiary", "tertiary_link"];
    $s = 0;
    if ($z >= 16) {
        return "";
    } else if ($z >= 15) {
        $s = 10;
    } else if ($z >= 13) {
        $s = 8;
    } else if ($z >= 12) {
        $s = 6;
    } else if ($z >= 10) {
        $s = 4;
    } else {
        $s = 1;
    }
    $base = " AND (";
    for ($i = 0; $i < $s; $i++) {
        $base .= "\"highway\"='" . $possible[$i] . "' OR ";
    }
    $base .= "1=0)";
    return $base;
}

function get_color_road($highway)
{
    if ($highway == "motorway" || $highway == "motorway_link") {
        return '#ffb676';
    }

    if ($highway == "trunk" || $highway == "trunk_link") {
        return "#ffd86b";
    }
    if ($highway == "primary" || $highway == "primary_link") {
        return "#ffecba";
    }
    return "#ffffff";
    return "#000000"; //for test
    //return 'none';
}


function get_width_road($highway, $z)
{
    if ($z >= 18) {
        if ($highway == "motorway" || $highway == "motorway_link" || $highway == "trunk" || $highway == "trunk_link") {
            return '18px';
        }
        if ($highway == "primary" || $highway == "primary_link") {
            return "15px";
        }
        if ($highway == "tertiary" || $highway == "tertiary_link") {
            return "7px";
        }
        return "5px";
    } else
    if ($z >= 16) {
        if ($highway == "motorway" || $highway == "motorway_link" || $highway == "trunk" || $highway == "trunk_link") {
            return '12px';
        }
        if ($highway == "primary" || $highway == "primary_link") {
            return "10px";
        }
        if ($highway == "tertiary" || $highway == "tertiary_link") {
            return "5px";
        }
        return "3px";
    } else if ($z >= 14) {
        if ($highway == "motorway" || $highway == "motorway_link" || $highway == "trunk" || $highway == "trunk_link") {
            return '6px';
        }
        if ($highway == "primary" || $highway == "primary_link") {
            return "4px";
        }
        if ($highway == "tertiary" || $highway == "tertiary_link") {
            return "3px";
        }
        return "2px";
    } else if ($z >= 12) {
        if ($highway == "motorway" || $highway == "motorway_link" || $highway == "trunk" || $highway == "trunk_link") {
            return '4px';
        }
        return "2px";
    }

    return "2px";
}