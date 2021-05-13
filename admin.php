<?php
    //debug-Optionen
   ini_set('display_errors', 1);
   ini_set('log_errors', 1);
   ini_set('error_log', './ERROR.LOG');
   error_reporting(E_ALL & ~E_NOTICE);

  require_once("config.php"); // konfiguration lesen

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

    // Initialize the session
    session_start();
    // Check if the user is logged in, if not then redirect him to login page
    // TODO - erledigen
    if(!isset($_SESSION["adminloggedin"]) || $_SESSION["adminloggedin"] !== true){
        header("location: adminlogin.php");
        exit;
    }
    
    //Open-and-prepare database
    require_once("sqlite_inc.php");

   if(!$db) {
      echo $db->lastErrorMsg();
   } else {
      console_log( "Opened database successfully");
   }
   
    
    // Define variables and initialize with empty values
    $username = $password = "";
    $username_err = $password_err = $login_err = "";
    $result = $result_err = "";
    $showtables = false;
    
    // Processing get-data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "GET") {
      if(isset($_GET["logout"])) { // should log out
        $_SESSION = array();
         
        // Destroy the session.
        session_destroy();
         
        // Redirect to login page
        header("location: pirates.php");
        exit;
      } else if (isset($_GET["deleteSQLtables"])) {
        $sql =<<<EOF
      DROP TABLE inseln;
      DROP TABLE clients;
      DROP TABLE piraten;
EOF;
        $ret = $db->exec($sql);
         if(!$ret) {
            $result_err = $db->lastErrorMsg();
         } else {
            $result = "Tabellen gelöscht";
         }
      } else if (isset($_GET["showtables"])) {
        $showtables=true;
      }
      
      if (isset($_GET["delrow"])) { //hier soll eine Tabellenzeile gelöscht werden
        $rowid = filter_input(INPUT_GET, 'delrow', FILTER_VALIDATE_INT);
        $tablename = trim(filter_input(INPUT_GET, 'table', FILTER_SANITIZE_STRING));
        $sql = "DELETE FROM ".$tablename." WHERE rowid=".$rowid.";";
        console_log("SQL: ".$sql);
        $db->exec($sql);
      } 
      if (isset($_GET["deltable"])) { //hier soll eine Tabelle gelöscht werden
        $tablename = trim(filter_input(INPUT_GET, 'deltable', FILTER_SANITIZE_STRING));
        $sql = "DROP TABLE ".$tablename.";";
        console_log("SQL: ".$sql);
        $db->exec($sql);
      } 
    }
     
    // Processing form data when form is submitted
    if($_SERVER["REQUEST_METHOD"] == "POST"){
     
        
    }

    
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
  <a class="navbar-brand" href="#">Treasure-Island</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="collapsibleNavbar">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="pirates.php">Home</a>
      </li>
    </ul>
  </div>  
</nav>

<div class="container" style="margin-top:80px">
  <?php 
    if(!empty($result)){
      echo '<div class="alert alert-info">' . $result . '</div>';
    }        
    if(!empty($result_err)){
      echo '<div class="alert alert-danger">' . $result_err . '</div>';
    }        
  ?>


  <div class="row">
    <div class="col-sm-4">
      <h3>Admin Menu</h3>
      <p>choose an option</p>
      <ul class="nav nav-pills flex-column">
        <li class="nav-item">
          <a class="nav-link active" href="?logout">Logout</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="?deleteSQLtables">Tabellen l&ouml;schen</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="?showtables">Tabellen anzeigen</a>
        </li>
        <li class="nav-item">
          <a class="nav-link disabled" href="#">Disabled</a>
        </li>
      </ul>
      <hr class="d-sm-none">
    </div>
    <div class="col-sm-8">
    <?php
      if ($showtables) {
        echo "\n";
        //alle Tabellen ermitteln
        $tablesquery = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
        $tables = array();

        while ($table = $tablesquery->fetchArray(SQLITE3_ASSOC)) {
          array_push($tables,$table['name']);
        }
        
        if (!empty($tables)) {
          foreach($tables as $name) {
            $sql = "SELECT rowid,* FROM ".$name . ";";
            if ($res = $db->query($sql)) {
              echo "<h4><a href=\"?deltable=".$name."&showtables\" class=\"text-danger\" role=\"button\">&times;</a>Tabelle ".$name."</h4>\n";
              echo "<div class=\"table-responsive\"><table class=\"table\"><thead><tr>\n";
            		for($i = 0; $i<$res->numColumns(); $i++) {
                  echo "<th>".$res->columnName($i)."</th>\n";			
                }
              echo "</tr></thead><tbody>\n";
                while($row = $res->fetchArray(SQLITE3_NUM)) {
                  echo "<tr>";
                  for($i = 0; $i<$res->numColumns(); $i++) {
                    echo "<td>";
                    if ($i==0) {
                      echo "<a href=\"?delrow=".$row[0]."&table=".$name."&showtables\" class=\"text-danger\" role=\"button\">&times;</a>";
                    }
                    echo $row[$i]."</td>\n";
                  }		
                  echo "</tr>\n";
                }
              echo "</tbody></table></div>\n";
            }
          }
        }
        echo "\n";
      }
    ?>
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
