<?php

  define("DEBUG", false); //Konsolenausgaben aktivieren oder deaktivieren
  define("CHECKLIMITS", true); //sollen die Grenzwerte geprüft werden
  define("MAXTIME",90*60); //Maximale Zeit, die ein Client leben darf
  define("MAXCLIENTS", 100); //Maximal zulässige Anzahl von Clients
  define("MAXCLIENTIDS", 6); //Maximal zulässige Anzahl von ClientIDs per Session
  define("MAXBK", 500); //Maximale Anzahl an Bordkarten
  define("ADMINPASS", "pass"); //Passwort für Adminseite
  define("HOME_URL", "pirates.php"); //Ziel des "Home"-Links auf der Piraten-Seite

  define("ALLOWTABLEEDIT", true); //Editieren einzelner Tabellenzeilen im Admin-Bereich "Tabellen anzeigen" erlauben
  define("ALLOWTABLEDELETE", true); //Löschen einzelner Tabellenzeilen/-tabellen im Admin-Bereich "Tabellen anzeigen" erlauben

?>
