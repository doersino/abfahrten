<?php

function getStops() {
    // stops.json is taken from the source code of https://www.swtue.de/abfahrt.html
    return json_decode(file_get_contents("stops.json"), true);

function expandAbbreviations($html) {
    $abbreviations = json_decode(file_get_contents("abbreviations.json"), true);
    $search = array_map(function($arr) {return $arr["short"];}, $abbreviations);
    $replace = array_map(function($arr) {return $arr["full"];}, $abbreviations);
    return str_replace($search, $replace, $html);
}

function translateColumnLabel($colLabel) {
    $map = ["linie" => "line", "richtung" => "direction", "abfahrt" => "time"];
    return isset($map[$colLabel]) ? $map[$colLabel] : $colLabel;
}

function condenseTime($time) {
    return ltrim(preg_replace("/(([0-9]) h )?([0-9]+) Min/", "$2:$3", $time), ":");
}

function getId($name, $platform = "") {
    $stops = getStops();

    foreach ($stops as $stop) {
        if ($stop["stop"] == $name && $stop["platform"] == $platform) {
            return $stop["id"];
        }
    }
}

function findMatches($search) {
    $stops = getStops();

    $matches = [];
    foreach ($stops as $stop) {
        if (stripos($stop["stop"], $search) !== false) {
            $matches[] = $stop;
        }
    }
    return $matches;
}

function getDepartures($id) {
    $html = file_get_contents("https://www.swtue.de/abfahrt.html?halt=" . $id);
    $html = expandAbbreviations($html);

    if (strpos($html, "Diese Haltestelle wird momentan nicht bedient.") !== false) {
        return ["error" => "Diese Haltestelle wird momentan nicht bedient."];
    }
    if (strpos($html, "Ihre Anfrage kann zur Zeit leider nicht bearbeitet werden.") !== false) {
        return ["error" => "Ihre Anfrage kann zur Zeit leider nicht bearbeitet werden."];
    }

    $dom = new DomDocument();
    $dom->loadHTML($html);
    $table = $dom->getElementsByTagName("table")->item(0)->childNodes;
    $result = [];
    foreach ($table as $row) {
        $columns = $row->childNodes;

        $resultRow = [];
        foreach ($columns as $col) {
            if ($col->nodeName == "td") {  // disregard empty in-between text nodes
                $colLabel = translateColumnLabel($col->getAttribute("class"));
                $colValue = trim($col->textContent);
                if ($colLabel == "time") {
                    $colValue = ($colValue == " ") ? "0 Min" : $colValue;  // replace bus icon
                    $colValue = condenseTime($colValue);
                }
                $resultRow[$colLabel] = $colValue;
            }
        }
        $result[] = $resultRow;
    }

    // first element empty for whatever reason
    return array_slice($result, 1);
}

function encodeAsTable($kind, $data) {
    $html = "";

    if ($kind == "departures") {
        $departures = $data;

        if (isset($departures["error"])) {
            $html .= "<table><tr><td colspan=\"3\" class=\"error\">" . $departures["error"] . "</td></tr></table>";
        } else {
            $html .= "<table>";
            foreach ($departures as $row) {
                $html .= "<tr>";
                foreach ($row as $colLabel => $col) {
                    $html .= "<td class='$colLabel'>$col</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
    } else if ($kind == "search") {
        $matches = $data;

        $html .= "<ul>";
        foreach ($matches as $match) {
            $html .= "<li id=\"" . $match["id"] . "\">";
            $html .= "<h1 class='stop'>" . $match["stop"] . "</h1>";
            if (!empty($match["platform"])) {
                $html .= " <h2 class='platform'>→ " . $match["platform"] . "</h2>";
            }
            $html .= "</li>";
        }
        $html .= "</ul>";
    }

    return $html;
}

function encodeAsJSON($kind, $data) {
    return json_encode($data);
}

// switch to html output function if desired
if (!empty($_POST["format"]) && $_POST["format"] == "html") {
    $format = encodeAsTable;
} else {
    $format = encodeAsJSON;
}

if (!empty($_POST["id"])) {
    $id = $_POST["id"];
    $departures = getDepartures($id);
    echo $format("departures", $departures);
} else if (!empty($_POST["name"])) {  // TODO untested, so test this
    $name = $_POST["name"];
    if (!empty($_POST["platform"])) {
        $id = getId($name, $_POST["platform"]);
    } else {
        $id = getId($name);
    }
    $departures = getDepartures($id);
    echo $format("departures", $departures);
} else if (!empty($_POST["search"])) {
    $search = $_POST["search"];
    $matches = findMatches($search);
    echo $format("search", $matches);
}
