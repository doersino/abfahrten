# abfahrten

Ein besseres¹ Interface zum Online-Abfahrtsmonitor der Stadtwerke Tübingen. Aktuell stark auf meine eigenen Bedürfnisse abgestimmt.


## Systemvoraussetzungen

Ein PHP-fähiger Webserver. Handelübliche Sharehosting-Angebote sollten wohl funktionieren?


## Installation

Inhalt dieses Repositories in `public_html` oder so des obigen Webservers schieben.


## Konfiguration

Über `defaults.json` und/oder durch allgemeine Hackbarkeit.


## Wartung

Wenn die Stadtwerke Haltestellen hinzufügen oder entfernen, `stops.json` entsprechend neu kopieren aus dem gegen Ende des Quellcodes von `https://www.swtue.de/abfahrt.html` verlinkten Skript.


---

¹ Finde ich. Deine Kilometerleistung kann variieren.
