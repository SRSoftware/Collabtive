Dieser Zweig erlaubt eine Anbindung des Timetrackers an die Faktura-Software "open3a".

Collabtive wurde hierzu so modifiziert und erweitert, dass man im Timetracker eine zusätzliche Exportfunktion "open3A" hat.
Wählt man diese, wird in Open3A ein neuer Auftrag und darin eine neue Rechnung mit den Posten aus der Zeiterfassung erzeugt.

Voraussetzungen:
* in der Datei config/open3a/config.php sind die Variablen $db_host, $db_name, $db_user, $db_pass, $user_id, $location und $hourly_wage gesetzt.
  * $db_host ist der Name oder die IP des Rechners auf dem die open3a-Installation läuft.
  * $db_name ist der Name der open3A-Datenbank auf dem entsprechenden Rechner (momentan wird nur mysql unterstützt)
  * $db_user ist der Name des Datenbankbenutzers, der auf die open3a-Datenbank Zugriff hat.
  * $db_pass ist das zugehörige Passwort für die Datenbank.
  * $user_id ist die Open3a-interne ID des Benutzers, unter dessen Konto die Rechnungen erstellt werden sollen.
  * $location ist der Pfad, unter dem die open3a-Installation zu erreichen ist. Das kann entweder ein relativer Pfad ausgehend von Collabtive sein, oder eine absolute URL.
  * $hourly_wage ist der Stundenlohn, der standardmäßig für die exportierten Stundenzettel angesetzt wird (siehe unten).
* in jedem Projekt, für welches in Open3a Rechnungen erzegt werden sollen, muss in der Projektbeschreibung eine Kundennummer in der Form "Kundennummer: xxxxx" angegeben sein.
* zusätzlich kann in die Projektbeschreibung ein von der config.php abweichender Stundenlohn in der Form "Stundensatz: xxxxx" eingetragen werden. 

Anmerkungen:
Diese Modifikation ist im Moment noch nicht übersetzbar. Ob es Übersetzungen geben wird, hängt vom Interesse der Allgemeinheit ab.

Diese Modifikation steht unter der gleichen Lizenz wie Collabtive.

viel Spaß, der Autor.

(Stephan Richter)
 

