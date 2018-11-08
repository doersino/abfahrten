<?php

function getStops() {
    // stops.json is taken from the source code of https://www.swtue.de/abfahrt.html
    return json_decode(file_get_contents("stops.json"), true);

    // TODO expand this list: merge all platforms of same stop into one (additional entry), provide that to the user as well
    // TODO between significant stops: <i style="font-style: inherit;color: rgb(96, 96, 96);font-size: 0.8rem;margin: 0.2rem;/* vertical-align: top; *//* padding: 0.2rem; */;">▶</i>
}

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
    $html = @file_get_contents("https://www.swtue.de/abfahrt.html?halt=" . $id);

    // error handling
    if (!$html) {
        $error = error_get_last();
        return ["error" => "Beim Abruf der Abfahrten ist ein Fehler aufgetreten (\"" . $error . "\")."];
    }
    if (strpos($html, "Diese Haltestelle wird momentan nicht bedient.") !== false) {
        return ["error" => "Diese Haltestelle wird momentan nicht bedient."];
    }
    if (strpos($html, "Ihre Anfrage kann zur Zeit leider nicht bearbeitet werden.") !== false) {
        return ["error" => "Ihre Anfrage kann zur Zeit leider nicht bearbeitet werden."];
    }

    $html = expandAbbreviations($html);

    // parse html
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

function encodeAsHTML($kind, $data) {
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
    } else if ($kind == "status") {
        $html .= "<p>" . $data["status"] . "</p>";
    } else {
        $html .= "<p>HTML output not supported for this type of request.</p>";
    }

    return $html;
}

function encodeAsJSON($kind, $data) {
    return json_encode($data);
}

function handleRequest($request) {
    // switch to html output function if desired
    if (!empty($request["format"]) && $request["format"] == "html") {
        $format = encodeAsHTML;
    } else {
        $format = encodeAsJSON;
    }

    if (!empty($request["id"])) {  // departures for given stop id
        $id = $request["id"];
        $departures = getDepartures($id);
        return $format("departures", $departures);
    } else if (!empty($request["name"])) {  // departures for given stop name
        $name = $request["name"];
        if (!empty($request["platform"])) {
            $id = getId($name, $request["platform"]);
        } else {
            $id = getId($name);
        }
        $departures = getDepartures($id);
        return $format("departures", $departures);
    } else if (!empty($request["search"])) {  // stops matching given search string
        $search = $request["search"];
        $matches = findMatches($search);
        return $format("search", $matches);
    } else {  // anything else is invalid
        return $format("status", ["status" => "Invalid request."]);
    }
}

echo handleRequest($_POST);
