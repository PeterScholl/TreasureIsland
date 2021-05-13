<?php declare(strict_types=1); // strict requirement - Variablentypen werden geprüft!
  //Open the database
  class MyDB extends SQLite3 {
    function __construct() {
       $this->open('pirates.db');
    }
  }
  $db = new MyDB();
  
  function getUserIpAddr(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        //ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        //ip pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }
  
  //alle Datenbankoperationen sollten hier als Funktion deklariert sein, so dass man 
  //diese für eine MySQL-Version austauschen könnte
  
  function gibInselNr() {
    global $db;
    //Sollte eine Inselnummer ausgeben an der der größte Bedarf ist
    $stmt = $db->prepare('SELECT count(*) AS anz FROM clients WHERE inseltyp=:inseltyp');
    $anzahlen = array();
    for ($i=1;$i<7;$i++) {
      $stmt->bindValue(':inseltyp', "".$i, SQLITE3_TEXT);
      $result = $stmt->execute();
      $row = $result->fetchArray();
      console_log("".$i.": ".$row["anz"]);
      $anzahlen[$i]=$row["anz"];
      //var_dump($result->fetchArray());
    }
    $min = min($anzahlen);
    $inselnr = array_search($min,$anzahlen);
    console_log("min: ".$min." fuer ".$inselnr);
    //return rand(1,7);
    return $inselnr;    
  }
  
  function gibBildName(int $inseltyp) {
    global $db;
    //Name des Bildes ermitteln
    $sql = "select bilddatei from inseln where inselnr=".$inseltyp.";";
    console_log("SQL: ".$sql);
    $res=$db->query($sql);
    if ($row = $res->fetchArray(SQLITE3_ASSOC)) { // gefunden
      $bildname=$row['bilddatei'];
      console_log("Bildname: ".$bildname);
      return $bildname;
    } else { // nicht gefunden
      console_log("Bildname nicht gefunden?!");
      //TODO: abbrechen - wie?
      return null;
    }
  }

  function setClientIDUndInselTyp() {
        global $db;
        console_log("Session-ID setzen und in Datenbank registrieren - Inseltyp bestimmen");
        //Client in Datenbank suchen
        $sql = "select rowid,inseltyp from clients where session_id='".session_id()."';";
        console_log("SQL: ".$sql);
        $res=$db->query($sql);
        if ($row = $res->fetchArray(SQLITE3_ASSOC)) { // gefunden
          $sql = "UPDATE clients set lastedited=strftime('%Y-%m-%d %H:%M:%S','now') where session_id='".session_id()."';";
          console_log("SQL: ".$sql);
          $res=$db->exec($sql);
          $_SESSION["clientid"]=$row['rowid'];
          $_SESSION["inseltyp"]=$row['inseltyp'];
          console_log("clientid: ".$_SESSION["clientid"]." inseltyp: ".$_SESSION["inseltyp"]);
        } else { // nicht gefunden
          //TODO: Prüfen ob zu viele Clients in der letzten Zeit (Minute, Stunde, 5 Minuten... ?) erstellt wurden
          $_SESSION["inseltyp"]=gibInselNr();
          $sql = "INSERT INTO clients (session_id, inseltyp, ipaddr, lastedited, created) VALUES ".
          "('".session_id()."',".$_SESSION["inseltyp"].",'".getUserIpAddr()."',strftime('%Y-%m-%d %H:%M:%S','now'),strftime('%Y-%m-%d %H:%M:%S','now'));";
          console_log("SQL: ".$sql);
          if($db->exec($sql)) {
            console_log("Insel registriert");
          } else {
            console_log("Eintrag nicht erfolgt");
            console_log("Fehler: ".$db->lastErrorMsg);
          }
          $sql = "select rowid from clients where session_id='".session_id()."';";
          console_log("SQL: ".$sql);
          if($res=$db->querySingle($sql)) {
            $_SESSION["clientid"]=$res;
          }
        }  
  }
  
  function gibNeueBordkartenNummer() {
      global $db;
      //Anzahl vorhandener Bordkarten ermitteln
      $anz_bk=-1;
      $sql = "select count(*) from piraten;";
      console_log("SQL: ".$sql);
      if($res=$db->querySingle($sql)) {
        $anz_bk=$res;
      }
      console_log("Es gibt ".$anz_bk." Bordkarten");
      $bknr = -1;
      do {
        $bknr = rand(1234,1912+10*$anzbk);
        $sql = "select count(*) from piraten;";
        console_log("SQL: ".$sql);
      } while (false); // while(!$res=$db->querySingle($sql));
      //in Datenbank eintragen
      $sql = "INSERT INTO piraten (bordcardnr, aktinsel, letzteInsel, tour, letzteFahrtZeit, erzeugt) VALUES ".
      "('".$bknr."','1','-1','',strftime('%Y-%m-%d %H:%M:%S','now'),strftime('%Y-%m-%d %H:%M:%S','now'));";
      console_log("SQL: ".$sql);
      if($db->exec($sql)) {
        console_log("Bordkartennummer registriert");
      } else {
        console_log("Eintrag nicht erfolgt");
        console_log("Fehler: ".$db->lastErrorMsg);
      }
      return $bknr;      
  }
  
  //Lässt den Piraten mit der Bordkartennr von der Inselnr
  //Mit dem gewählten Schiff fahren und gibt Info zurück
  //Array mit valid = true/false und message
  function gibRoutenInfo($bknr, $schiff, $inselnr) {
    global $db;
    //Return Array
    $arr = array();
    $arr['valid'] = false;
    //zielInsel ermitteln
    $zielinsel = 0;
    $sql = "select ziel".$schiff." from inseln where inselnr=".$inselnr.";";
    console_log("SQL: ".$sql);
    if($res=$db->querySingle($sql)) {
      $zielinsel=$res;
    } else {
      $arr['message']="Fehler! Dieses Schiff (".$schiff.") gibt es nicht !";
      return $arr;
    }
    //Tabelle piraten aktualisieren
    $sql = "UPDATE piraten set letzteFahrtZeit=strftime('%Y-%m-%d %H:%M:%S','now'),aktinsel='".$zielinsel."',letzteInsel='".$inselnr."', tour=tour||'".$schiff."' where bordcardnr='".$bknr."';";
    console_log("SQL: ".$sql);
    if ($res=$db->exec($sql)) {
      $arr['valid'] = true;
      $arr['message'] = "Der Pirat mit der Bordkarte ".$bknr." fährt von ".gibInselName($inselnr)." nach ".gibInselName($zielinsel)."!";
    } else {
      $arr['message'] = "Reise konnte leider nicht gebucht werden - Fehler";
    }
    return $arr;
  }
  
  function inselNrVonClientSetzen($clientid, $neueInselNr) {
    global $db;
    $sql = "UPDATE clients set inseltyp='".$neueInselNr."', lastedited=strftime('%Y-%m-%d %H:%M:%S','now') where rowid='".$clientid."';";
    console_log("SQL: ".$sql);
    if($res=$db->exec($sql)) {
      console_log("Success");
      return true;
    }
    return false; 
   }
  
  // gibt eine Array zum Piraten zurück, dass die Infos enthält
  // valid->true/false, aktInsel, letzteInsel, tour
  function gibPiratenInfo($bknr) {
    global $db;
    $sql = "select * from piraten where bordcardnr='".$bknr."';";
    console_log("SQL: ".$sql);
    $res=$db->query($sql);
    $arr = array();
    if ($row = $res->fetchArray(SQLITE3_ASSOC)) {
      $arr['valid']=true;
      $arr['aktInsel']=$row['aktInsel'];
      $arr['letzteInsel']=$row['letzteInsel'];
      $arr['tour']=$row['tour'];
    } else {
      $arr['valid']=false;
    }
    return $arr;    
  }
  
  // Gibt den Namen der Insel mit der Nummer zurück
  function gibInselName($nr) {
    global $db;
    $sql = "select name from inseln where inselnr='".$nr."';";
    console_log("SQL: ".$sql);
    if($res=$db->querySingle($sql)) {
      return $res;
    }
    return "unbekannte Insel";  
  }
    


  // prüfen ob tabellen existieren
  // clients
  if (is_null($db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='clients';"))) {
    console_log("Tabelle clients existiert nicht!");
    $sql = "CREATE TABLE clients (session_id TEXT, inseltyp INTEGER, ipaddr TEXT, lastedited TEXT, created TEXT);";
    $ret = $db->exec($sql);
    if(!$ret){
        echo $db->lastErrorMsg();
    } else {
      console_log("Table clients created successfully");
    }
  }
  
  // piraten
  if (is_null($db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='piraten';"))) {
    console_log("Tabelle piraten existiert nicht!");
    $sql = "CREATE TABLE piraten (bordcardnr INT PRIMARY KEY, aktInsel INT, letzteInsel INT, tour TEXT, letzteFahrtZeit TEXT, erzeugt TEXT);";
    $ret = $db->exec($sql);
    if(!$ret){
        echo $db->lastErrorMsg();
    } else {
      console_log("Table piraten created successfully");
    }
  }

  // inseln
  if (is_null($db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='inseln';"))) {
    console_log("Tabelle inseln existiert nicht!");
    $sql = "CREATE TABLE inseln (inselnr INT PRIMARY KEY, name TEXT, bilddatei TEXT, zielA INT, zielB INT);";
    $ret = $db->exec($sql);
    if(!$ret){
        echo $db->lastErrorMsg();
    } else {
      console_log("Table inseln created successfully");
         $sql =<<<EOF
      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (1, 'Pirates´ Island','pira.jpg', 2, 3 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (2, 'Shipwreck Bay','ship.jpg', 3, 4 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (3, 'Musket Hill','musk.jpg', 1, 5 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (4, 'Dead Man´s Island','dead.jpg', 3, 2 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (5, 'Mutineers´ Island','muti.jpg', 6, 4 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (6, 'Smugglers´ Cove','smug.jpg', 1, 7 );

      INSERT INTO inseln (inselnr,name,bilddatei,zielA,zielB)
      VALUES (7, 'Treasure Island','trea.jpg', -1, -1 );
EOF;
     $ret = $db->exec($sql);
     if(!$ret) {
        echo $db->lastErrorMsg();
     } else {
        console_log("Inseldaten eingetragen");
     }
    }
  }
   

?>
