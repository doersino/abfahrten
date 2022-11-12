<?php

require_once("core.php");

function handleRequest($request) {
    // switch to html output function if desired
    if (isset($request["format"]) && $request["format"] == "html") {
        $format = 'encodeAsHTML';
    } else {
        $format = 'encodeAsJSON';
    }

    if (isset($request["id"])) {  // departures for given stop id
        $id = $request["id"];
        $departures = getDepartures($id);
        return call_user_func($format, "departures", $departures);
    } else if (isset($request["stop"])) {  // departures for given stop name and, if applicable, platform  // TODO untested
        $stop = $request["stop"];
        if (isset($request["platform"])) {
            $details = getDetails(NULL, $stop, $request["platform"]);
        } else {
            $details = getDetails(NULL, $stop, NULL);
        }
        $departures = getDepartures($details["id"]);
        return call_user_func($format, "departures", $departures);
    } else if (isset($request["search"])) {  // stops matching given search string
        $search = $request["search"];
        $matches = findMatches($search);
        return call_user_func($format, "search", $matches);
    } else {  // anything else is invalid
        return call_user_func($format, "status", ["status" => "Invalid request."]);
    }
}

echo handleRequest($_POST);
