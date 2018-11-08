<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="apple-touch-icon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <title>Abfahrten</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    html {
        font-size: 20px;
    }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
        padding-bottom: 2rem;
    }
    header {
        background-color: #ddd;
        padding: 0.5rem;
        border-bottom: 1px solid #ccc;
        cursor: pointer;
    }
    h1 {
        font-weight: bold;
        font-size: 1.3em;
        display: inline-block;
    }
    h2 {
        color: #888;
        font-size: 1em;
        display: inline-block;
    }
    table {
        width: 100%;
    }
    table:not(:empty) {
        padding: 0.5rem;
    }
    table tr:not(:last-child) td {
        padding-bottom: 0.2rem;
    }
    table td.line {
        width: 12%;
        font-style: italic;
    }
    table td.direction {
        width: 70%;
    }
    i.and {
        font-style: inherit;
        color: #666;
        font-size: 0.7rem;
        margin: 0.15rem 0.2rem 0.15rem 0.15rem;  /* keming */
        vertical-align: top;
        display: inline-block;
    }
    table td.time {
        width: 18%;
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: bold;
    }
    table td.error {
        color: gray;
    }
    input {
        width: 100%;
        position: fixed;
        bottom: 0;
        border: 0;
        border-radius: 0;
        background-color: #e8e8e8;
        font: inherit;
        height: 2rem;
        padding: 0.5rem;
        outline: none;
    }
    ul, #searched {
        box-shadow: 0 -0.5rem 2rem rgba(0,0,0,0.2);
        position: fixed;
        bottom: 2rem;
        max-height: 70vh;
        overflow: scroll;
        background-color: white;
        width: 100%;
    }
    li {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        cursor: pointer;
    }
    li:not(:last-child) {
        border-bottom: 1px solid #ddd;
    }
    sup {
        vertical-align: top;
        font-size: 0.7rem;
        position: relative;
        left: -0.1rem;
    }
    </style>
    <script>
        function prettify(targetSectionId) {
            var lineColors = ["000000","e00a1e","18a199","d85f1c","8fc79a","640c32","c89c6a","6e92c5","6e92c5","6eb5b7","deda2f","2172b6","db6da5","518095","085267","f09fc4","95bf30","a41c7f","a69dcb","642780","000000","1a9fe0","96ad9a","07488c"];  // taken from official line plan
            var timeColors = ["000", "222", "444", "666", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888", "888"];
            var timeColorIndex = 0;

            // extract table and check for errors, in which case no
            // prettification is required
            var target = document.getElementById(targetSectionId);
            table = target.getElementsByTagName("table")[0];
            if (table.getElementsByTagName("tr")[0].getElementsByClassName("error").length == 0) {

                // prettify!
                var rows = table.getElementsByTagName("tr");
                for (row of rows) {
                    var line = row.getElementsByClassName("line")[0];
                    var direction = row.getElementsByClassName("direction")[0];

                    // set line color
                    line.style.color = "#" + lineColors[parseInt(line.innerHTML) || 0];

                    // draw inbound/outbound (rather: downhill/uphill)
                    // indicators
                    if (["Hbf", "Hauptbahnhof"].some(d => direction.innerHTML.indexOf(d) >= 0)) {
                        line.innerHTML = line.innerHTML + "<sup>↓</sup>";
                    } else if (["Kliniken", "Wanne", "WHO", "Pfrondorf"].some(d => direction.innerHTML.indexOf(d) >= 0)) {
                        line.innerHTML = line.innerHTML + "<sup>↑</sup>";
                    }

                    // replace "-" with "▶" in some places
                    if (["Hbf-", "Wanne-", "WHO-", "Sand-", "Schönblick-", "Sternwarte-", "Haußerstraße-", "Kliniken-", "Lustnau Nord-", "Weststadt-", "Haagtor-", "Schwärzlocher Straße-"].some(d => direction.innerHTML.startsWith(d))) {
                        direction.innerHTML = direction.innerHTML.replace(/-/, "<i class='and'>▶</i>");
                    }

                    // highlight imminent departures
                    var time = row.getElementsByClassName("time")[0];
                    if (time.innerHTML == "0") {
                        time.innerHTML = "■";
                    }
                    if (["■", "1", "2"].includes(time.innerHTML)) {
                        time.style.color = "#e60000";
                    } else {
                        time.style.color = "#" + timeColors[timeColorIndex++];
                    }
                }
            }
        }
        function apifahrten(request, onCompletion) {  // request parameters and a function dealing with response text
            var api = new XMLHttpRequest();

            api.onreadystatechange = function() {
                if (api.readyState == 4 && api.status == 200) {
                    onCompletion(api.responseText);
                }
            }

            api.open("POST", "apifahrten.php", true);
            api.setRequestHeader("Content-type","application/x-www-form-urlencoded");
            api.send(request);
        }
        function get(id, targetSectionId) {
            apifahrten("format=html&id=" + id, function(response) {
                var departures = response;
                var target = document.getElementById(targetSectionId);

                // replace the old table with the new one, which is just a
                // string, necessitating the creation of a temporary helper div
                var table = target.getElementsByTagName("table")[0];
                var div = document.createElement("div");
                div.innerHTML = departures;
                table.parentNode.replaceChild(div.firstChild, table);

                // add some color, direction markers (uphill or downhill) and
                // highlight imminent departures
                prettify(targetSectionId);
            });
        }
        function search() {
            var searchString = document.getElementById("searchy").value;

            apifahrten("format=html&search=" + encodeURIComponent(searchString), function(response) {
                var matches = response;

                // display search results
                var results = document.getElementById("results");
                results.innerHTML = matches;

                // if one of them is clicked, load its upcoming departures
                lis = document.getElementsByTagName("li");
                for (li of lis) li.onclick = function() {

                        // hide search results
                        results.innerHTML = "";

                        // grab target section
                        var searched = document.getElementById("searched");

                        // clean up from potential earlier searches
                        searched.innerHTML = "";

                        // construct header and an empty table, later to be
                        // replaced by get()
                        var header = document.createElement("header");
                        header.setAttribute("onclick", "this.parentNode.innerHTML = ''");
                        header.innerHTML = (this.innerHTML);
                        searched.appendChild(header);
                        var table = document.createElement("table");
                        searched.appendChild(table);

                        get(this.id, "searched");
                };
            });
        }
        function toggle(header) {
            var section = header.parentNode;
            var tables = section.getElementsByTagName("table");
            if (tables.length == 0) {
                var table = document.createElement("table");
                section.appendChild(table);
                get(section.id, section.id);
                section.scrollIntoView();
            } else {
                tables[0].parentNode.removeChild(tables[0]);
            }
        }
    </script>
</head>
<body>
    <?php
        require_once("core.php");
        echo renderDefaultsAsHTML();

        // php-less (i.e., typical js-based webapp) variant
        /*
        <section id="100005">
            <header onclick="toggle(this)">
                <h1>Hauptbahnhof</h1>
                <h2>→ Steig E</h2>
            </header>
        </section>
        <section id="25207">
            <header onclick="toggle(this)">
                <h1>Sand Drosselweg</h1>
            </header>
            <table></table>
        </section>
        <script>
            get(25207, "25207");
        </script>
        <section id="50504">
            <header onclick="toggle(this)">
                <h1>Pauline-Krone-Heim</h1>
                <h2>→ Hauptbahnhof</h2>
            </header>
        </section>
        <section id="50804">
            <header onclick="toggle(this)">
                <h1>Stadtgraben</h1>
            </header>
        </section>
        <section id="81004">
            <header onclick="toggle(this)">
                <h1>Sternplatz</h1>
                <h2>→ Hauptbahnhof</h2>
            </header>
        </section>
        */
    ?>

    <section id="searched"></section>
    <footer>
        <!-- boku saatchi! -->
        <input id="searchy" onkeyup="search()" placeholder="Haltestelle...">
        <section id="results"></section>
    </footer>
</body>
</html>
