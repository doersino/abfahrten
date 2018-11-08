<?php

require_once("core.php");

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
    } else if (!empty($request["stop"])) {  // departures for given stop name and, if applicable, platform  // TODO untested
        $stop = $request["stop"];
        if (!empty($request["platform"])) {
            $details = getDetails(NULL, $stop, $request["platform"]);
        } else {
            $details = getDetails(NULL, $stop, NULL);
        }
        $departures = getDepartures($details["id"]);
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
