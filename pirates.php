<?php
    //debug-Optionen
   ini_set('display_errors', 1);
   ini_set('log_errors', 1);
   ini_set('error_log', './ERROR.LOG');
   error_reporting(E_ALL & ~E_NOTICE);
   
    //Funktionen für Log-auf die Konsole
    function console_log_json( $data ){
        echo '<script>';
        echo 'console.log('. json_encode( $data ) .')';
        echo '</script>';
    }
    function console_log( $data ){
      echo '<script>';
      echo 'console.log("'. $data .'")';
      echo '</script>';
    }
    
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
   
    // Initialize the session
    session_start();
    // für Testzwecke unset session variable
    //unset($_SESSION["clientid"]);
    //console_log("Client-ID gelöscht: ".$_SESSION["clientid"]);
    //session_destroy();
    console_log("Session-ID: ".session_id());
 
    setClientIDUndInselTyp();
    
    //ab hier sollte ein Inseltyp bekannt sein
    //Name des Bildes ermitteln
    //$bildname=gibBildName($_SESSION["inseltyp"]);
   
 
    // Include config file
    require_once "config.php";
    
        // Processing get-data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "GET") {
      if (isset($_GET["neueBK"])) { //hier soll eine neue Bordkarte erzeugt werden
        $bknr = gibNeueBordkartenNummer();
        $message_info = "Neue Bordkarte mit der Nummer ".$bknr." erstellt - du befindest dich auf Pirates' Island";
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
              } else {
                $message_err = $routenInfo['message'];
              }
            }
          } else { // Pirat befindet sich auf der falschen Insel
            if ($piratenInfo['letzteInsel']==-1) {
              $message_err = "Du bist nicht auf dieser Insel! Du befindest dich auf ".gibInselName($piratenInfo['aktInsel'])."!";
            } else {
              $message_err = "Du bist nicht auf dieser Insel! Du bist von ".gibInselName($piratenInfo['letzteInsel'])." nach ".gibInselName($piratenInfo['aktInsel'])." gefahren!";            
            }
          }
          
        } else { // Bordkarte gibt es nicht
          $message_err = "Die Bordkarte mit der Nummer ".$bknr." gibt es nicht!";
        }
      }
      if (isset($_GET["incInselNr"])) { // Inselnr um eins erhöhen
        $neueNr = $_SESSION['inseltyp']+1;
        if ($neueNr > 6) {
          $neueNr = 1;
        }
        if (inselNrVonClientSetzen($_SESSION['clientid'], $neueNr)) {
          console_log("Inseltyp wird neu gesetzt");
          $_SESSION['inseltyp']=$neueNr;
        } else {
          console_log("FEHLER: Inseltyp konnte nicht gesetzt werden");
        }
      } 
    }
    
    //nur für Testzwecke
    console_log(gibInselNr());

    
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <title>Treasure-Island - with Bootstrap4 and php</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <style>
  .fakeimg {
    height: 200px;
    background: #aaa;
  }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-sm bg-dark navbar-dark fixed-top">
  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="#">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="?neueBK">Neue Bordkarte</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="?incInselNr">Nächste Insel</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="admin.php">Admin</a>
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
      <h5>Du befindest dich auf:</h5>
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

<?php
  include("footer.html");
?>

</body>
</html>

<?php
    $db->close();
?>
