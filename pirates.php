<?php
  //debug-Optionen
  //ini_set('display_errors', 1);
  ini_set('log_errors', 1);
  ini_set('error_log', './ERROR.LOG');
  error_reporting(E_ALL & ~E_NOTICE);
  
  require_once("config.php"); // konfiguration lesen

  // Initialize the session
  session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Treasure-Island - explore finite automata</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<?php
   
    //Funktionen für Log-auf die Konsole
    function console_log_json( $data ){
      if (DEBUG) {
        echo '<script>';
        echo 'console.log('. json_encode( $data ) .')';
        echo '</script>';
      }
    }
    function console_log( $data ){
      if (DEBUG) {
        echo '<script>';
        echo 'console.log("'. $data .'")';
        echo '</script>';
      }
    }
    // für Testzwecke unset session variable
    //unset($_SESSION["clientid"]);
    //console_log("Client-ID gelöscht: ".$_SESSION["clientid"]);
    //session_destroy();
    console_log("Session-ID: ".session_id());
    
    //Variablen anlegen und leer setzen
    $bildname = "";
    $message_info = $message_err = "";
    define("Z_SCHIFFSWAHL",2);
    define("Z_BKEINGABE",1);
    $zustand = Z_BKEINGABE;
    
   //Open-and-prepare database
    require_once("sqlite_inc.php");

   if(!$db) {
      echo $db->lastErrorMsg();
   } else {
      console_log( "Opened database successfully");
   }

   //Client registrieren
    if (!changeToClientID($_SESSION["clientid"])) { //war nicht schon registriert
      if (!setClientIDUndInselTyp()) { //Registrierung fehlgeschlagen
        $message_err = "Client konnte nicht angemeldet werden, evtl. maximale Anzahl (".MAXCLIENTS.") überschritten...";
      }
    }
    
    //ab hier sollte ein Inseltyp bekannt sein
    //Name des Bildes ermitteln
    //$bildname=gibBildName($_SESSION["inseltyp"]);
   
 
    
    // Processing get-data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "GET") {
      if (isset($_GET["neueBK"])) { //hier soll eine neue Bordkarte erzeugt werden
        if ($bknr = gibNeueBordkartenNummer()) {
          $message_info = "Neue Bordkarte mit der Nummer ".$bknr." erstellt - du befindest dich auf Pirates' Island";
        } else { //Neue Bordkarte konnte nicht erstellt werden
          $message_err = "Erstellen einer neuen Bordkarte nicht möglich - evtl. Maximum (".MAXBK.") überschritten";
        }
      } 
      if (isset($_GET["bordkarte"])) { //Pirat befindet sich hier
        $bknr = trim(filter_input(INPUT_GET, 'bordkarte', FILTER_VALIDATE_INT));
        $piratenInfo = gibPiratenInfo($bknr);
        if ($piratenInfo['valid']) { //Diese Bordkarte gibt es
          if ($piratenInfo['aktInsel']==$_SESSION["inseltyp"]) { // Pirat befindet sich auf richtiger Insel
            if (!isset($_GET["schiff"])) {
              $zustand = Z_SCHIFFSWAHL;
            } else { //dieser Pirat will fahren
              $schiff = trim(filter_input(INPUT_GET, 'schiff', FILTER_SANITIZE_STRING));
              $routenInfo = gibRoutenInfo($bknr,$schiff,$_SESSION["inseltyp"]);
              if ($routenInfo['valid']) {
                $message_info = $routenInfo['message'];
                if ($routenInfo['ziel']==7) { //Schatzinsel erreicht
                  $schatz_gefunden=true;
                }
              } else {
                $message_err = $routenInfo['message'];
              }
            }
          } else { // Pirat befindet sich auf der falschen Insel
            if ($piratenInfo['letzteInsel']==-1) {
              $message_err = "Du bist nicht auf dieser Insel! Du befindest dich auf ".gibInselName($piratenInfo['aktInsel'])."!";
            } else if ($piratenInfo['aktInsel']==7) {
              $schatz_gefunden = true;
            } else {
              $message_err = "Du bist nicht auf dieser Insel! Du bist von ".gibInselName($piratenInfo['letzteInsel'])." nach ".gibInselName($piratenInfo['aktInsel'])." gefahren!";            
            }
          }
          
        } else { // Bordkarte gibt es nicht
          $message_err = "Die Bordkarte mit der Nummer ".$bknr." gibt es nicht!";
        }
      }
      if (isset($_GET["incInselNr"]) || isset($_GET["decInselNr"])) { // Inselnr um eins erhöhen oder veringern
        if (isEnabled("allowToChangeIsland")) {
          if (isset($_GET["incInselNr"])) { //inselnummer um eins erhöhen
            $neueNr = $_SESSION['inseltyp']+1;
            if ($neueNr > 6) {
              $neueNr = 1;
            }
          } else { //inselnummer um eins verringern
            $neueNr = $_SESSION['inseltyp']-1;
            if ($neueNr<1) {
              $neueNr = 6;
            }
          }
          if (inselNrVonClientSetzen($_SESSION['clientid'], $neueNr)) {
            console_log("Inseltyp wird neu gesetzt");
            $_SESSION['inseltyp']=$neueNr;
          } else {
            console_log("FEHLER: Inseltyp konnte nicht gesetzt werden");
          }
        } else {
          $message_err = "Änderung der Inselnummer nicht erlaubt";
        }
      }
      if (isset($_GET["neueClientID"])) { //Neue ClientID erzeugen
        if (isEnabled("allowMultipClientsPerIP") && generateExtraClientID()) {
          $message_info = "Neue Client-ID erzeugt";
        } else {
          $message_err = "Neue Client-ID erzeugen - nicht erlaubt, evtl. Maximum (".MAXCLIENTS.") oder Maximum pro Session (".MAXCLIENTIDS.") überschritten...";
        }        
      }
      if (isset($_GET["preferredClientID"])) { //zu anderer ClientID-wechseln wenn möglich
        $pref_id = trim(filter_input(INPUT_GET, 'preferredClientID', FILTER_VALIDATE_INT));
        if (changeToClientID($pref_id)) {
          $message_info = "Client-ID geändert";
        } else {
          $message_err = "Wechsel zu Client-ID ".$pref_id." nicht möglich";
        }        
      } 
    }
    
    
?>




<body>
  
<?php if (($schatz_gefunden)): ?>
<script>
$(document).ready(function(){
    $("#successModal").modal();
});
</script>
<?php endif ?>

<nav class="navbar navbar-expand-sm bg-dark navbar-dark fixed-top">
  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="pirates.php">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="?neueBK">Neue Bordkarte</a>
      </li>
      <?php
      if (isEnabled("allowToChangeIsland")):?>
      <li class="nav-item ml-2">
        <a class="nav-link" href="?decInselNr">-</a>
      </li>
      <span class="navbar-text">
        Inseltyp
      </span>
      <li class="nav-item mr-2">
        <a class="nav-link" href="?incInselNr">+</a>
      </li>
      <?php endif ?>
      <?php
      if (isEnabled("allowMultipClientsPerIP")):?>
        <!-- Dropdown -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbardrop" data-toggle="dropdown">
            ClientID
          </a>
          <div class="dropdown-menu">
            <?php foreach (getPossibleClientIDs() as $ids) {
              echo "<a class=\"dropdown-item\" href=\"?preferredClientID=".$ids."\">Client-ID ".$ids."</a>";
            }
            ?>
            <a class="dropdown-item" href="?neueClientID">Neue ID</a>
          </div>
        </li>
      <?php endif ?>
      <li class="nav-item">
        <a class="nav-link" href="admin.php">Admin</a>
      </li>    
      <li class="nav-item">
        <a class="nav-link" data-toggle="modal" href="#infoModal">Info</a>
      </li>          
    </ul>
  </div>  
  <a class="navbar-brand ml-auto" href="#">Treasure-Island</a><span class="badge badge-light"><?php echo 'Client-ID:'.$_SESSION["clientid"]; ?></span>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>
</nav>

<div class="container" style="margin-top:80px">
  <?php
  if ($message_info!="") {
    echo "<div class=\"alert alert-success alert-dismissible\">";
    echo "<button type=\"button\" class=\"close\" data-dismiss=\"alert\">&times;</button>";
    echo "<strong>".$message_info."</strong></div>";
  }
  if ($message_err!="") {
    echo "<div class=\"alert alert-danger alert-dismissible\">";
    echo "<button type=\"button\" class=\"close\" data-dismiss=\"alert\">&times;</button>";
    echo "<strong>".$message_err."</strong></div>";
  }
  ?>
  <div class="row">
    <div class="col-sm-4 mx-auto">
      <h5>Dies ist der Hafen von:</h5>
      <?php
        echo "<img src=\"./images/".gibBildName($_SESSION['inseltyp'])."\" class=\"img-fluid mx-auto d-block\"></img>";
      ?>
      <?php
        if ($zustand==Z_BKEINGABE) {
          echo "<form action=\"pirates.php\" method=\"get\">";
        } else {
          echo "<form class=\"d-none\" action=\"pirates.php\" method=\"get\">";
        }
      ?>
        <div class="form-group">
          <label for="bordkarte">Bordkartennummer</label>
          <input type="text" class="form-control" placeholder="1234" id="bordkarte" name="bordkarte">
        </div>
        <button type="submit" class="btn btn-primary">Submit</button>
      </form> 
      <?php
        if ($zustand==Z_SCHIFFSWAHL) {
          echo "<div class=\"container mx-auto mb-2\"><h5>Wähle ein Schiff</h5>";
          echo "<a href=\"?bordkarte=".$bknr."&schiff=A\" class=\"btn btn-info mx-3\" role=\"button\">Schiff A</a>";
          echo "<a href=\"?bordkarte=".$bknr."&schiff=B\" class=\"btn btn-info mx-3\" role=\"button\">Schiff B</a>";
          echo "</div>";
        }
      ?>
      <hr class="d-sm-none">
    </div>
  </div>
</div>

<div class="container m-3">
  &nbsp;
</div>

<?php
  include("footer.html");
?>

<div class="modal" id="successModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Du hast es geschafft!</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        <img src="./images/trea.jpg" class="img-fluid mx-auto d-block"></img>
      </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-dismiss="modal">Schliessen</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="infoModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Information zu endliche Automaten mit Treasure Island</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        Die Piraten haben eine Bordkarte mit einer Bordkartennummer und befinden sich auf Pirates' Island. Sobald sie im Hafen von
        Pirates' Island ihre Bordkartennummer eingeben und diese als gültig erkannt wird, können Sie ein Schiff (A oder B) wählen,
        mit dem sie zu einer bestimmten anderen Insel fahren. Dort geht die Reise dann weiter... <br>
        Ziel ist es Treasure Island zu erreichen und den Schatz zu heben ;-)<br><br>
        Zur Umsetzung soll auf mehreren Rechnern die Webseite gestartet werden - der Server vergibt automatisch die Inselnamen an
        die Clients so dass von allen Inseln (insgesamt 6) in etwa gleich viele vorhanden sind. Idealerweise notieren sich die 
        SchülerInnen (Piraten) auf einer (Bord-)Karte ihre Bordkartennummer, das jeweils gewählte Schiff und evtl. die jeweils 
        aktuelle Insel, bis sie am Ziel Treasure Island angekommen sind.<br>
        Vorlagen hierzu gibt es bei Computer-Science-Unplugged (von wo auch die Bilder stammen)<br>
        Der/die Admin kann ggf. Bordkarten löschen, Clients löschen oder auch alle Tabellen zurücksetzen.<br>
        Der Wechsel von Inseltyp oder mehreren Clients auf einem Rechner ist nur für den Sonderfall, dass z.B. nur wenige Rechner zur 
        Verfügung stehen oder die SchülerInnen wegen einer Pandemie nicht durcheinander laufen dürfen, vorgesehen.<br><br>
        Viel Spaß beim Reisen!<br><br>
        PS: Als erstes brauchst du eine <a href="?neueBK">Bordkarte</a>!        
      </div>

      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal">Schliessen</button>
      </div>

    </div>
  </div>
</div>

</body>
</html>

<?php
    $db->close();
?>
