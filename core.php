<?php

function renderDefaultsAsHTML() {
    $defaults = json_decode(file_get_contents("defaults.json"), true);

    $html = "";

    foreach ($defaults as $default) {
        $id = $default["id"];
        $expand = $default["expand"];

        $html .= "<section id='$id'>";
        $html .= "<header onclick='toggle(this)'>";

        $details = getDetails($id, NULL, NULL);
        $stop = $details["stop"];
        $platform = $details["platform"];

        $html .= "<h1>$stop</h1>";
        if (!empty($platform)) {
            $html .= " <h2>→ $platform</h2>";
        }

        $html .= "</header>";

        if ($expand) {
            $departures = getDepartures($id);
            $html .= encodeAsHTML("departures", $departures);
            $html .= "<script>prettify('$id')</script>";
        }

        $html .= "</section>";
    }

    return $html;
}

function getStops() {
    $stops = json_decode(file_get_contents("stops.json"), true);

    // remove non-English "t" from "plattform"
    foreach ($stops as $i => $stop) {
        $stop["platform"] = $stop["plattform"];
        unset($stop["plattform"]);

        $stops[$i] = $stop;
    }

    return $stops;

    // TODO expand this list: merge all platforms of same stop into one (additional entry), provide that to the user as well
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

function getDetails($id, $stopName, $platform) {
    $stops = getStops();

    foreach ($stops as $stop) {
        if ($id !== NULL && $stop["id"] == $id
            || $stopName !== NULL && $platform === NULL && $stop["stop"] == $stopName
            || $stopName !== NULL && $platform !== NULL && $stop["stop"] == $stopName && $stop["platform"] == $platform) {
            return $stop;
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

    // spoof user agent to evade bot detection (probably won't matter, but can't
    // hurt to be safe)
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $ua = $_SERVER['HTTP_USER_AGENT'];
    } else {
        $ua = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Safari/605.1.15";
    }
    $options = array("http" => array("user_agent" => $ua));
    $context = stream_context_create($options);

    // get source abfahrt.html for relevant halt
    $html = @file_get_contents("https://www.swtue.de/abfahrt.html?halt=" . $id, false, $context);

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

    if (empty($result)) {
        return ["error" => "Zur Zeit keine verfügbaren Abfahrten."];
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
