<?php
#
#################################################
#   Dateiname:               ecc.php
#    Dateiversion:           1.0
#    Ersteller:              Peter Muchau
#    Beschreibung:           ecc.php
#    Letzter Bearbeiter:     Peter Muchau
#    Datum der Bearbeitung:  04.08.2018
#################################################
#
#
# Logging im Skript aktivieren (true | false)
$logging_enabled=true;
# 
# Logginglevel (debug | info) 
$logging_level="error";
#
# Zeitzone definieren
date_default_timezone_set("Europe/Berlin");
# INFO - Prozedur zum Erstellen eines Logfiles
function proc_logging($level,$typ,$message){
  $logfile = fopen("./log/threads.log","a");
  fwrite($logfile, date("d.m.y H:i:s")." [".$level."] [".$typ."]".$message."\r\n");
  fclose($logfile);
}
function db_connection($DATABASE)
		{
		    #Hestellen der Datenbankverbindung, definieren der Benutzerdaten
			if (!mysql_connect("localhost", "<USERNAME>", "<PASSWORD>", $DATABASE))
				die ("Es konnte keine Verbindung zum Datenbankserver aufgebaut werden, Fehlermeldung:".mysql_error());
	        #Übergabe des Datenbanknamens
			if (!mysql_select_db($DATABASE))
				die ("Die MySQL-Datenbank konnte nicht benutzt werden, Fehlermeldung:".mysql_error());
			mysql_query("SET NAMES 'utf8'");
        };
# INFO - Verbindung zum DB Server herstellen
db_connection('<DBNAME>');
# INFO - Dynamisches Transaction-ID für die Sitzung erstellen
$transaction_id = "TRANSID".rand(10,90).rand(30,90).rand(20,90).rand(20,50).chr(rand(65,90)); 
# INFO - Default Antwort, wenn kein Anrufername gesetzt ist
$response_default = '<?xml encoding="UTF-8" version="1.0"?><Response><Result><Decision>Permit</Decision><Status></Status><Obligations><Obligation FulfillOn="Permit" ObligationId="urn:cisco:xacml:policy-attribute"><AttributeAssignment AttributeId="Policy:simplecontinue"><AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">&lt;cixml ver="1.0"&gt;&lt;continue&gt;&lt;/continue&gt; &lt;/cixml&gt;</AttributeValue></AttributeAssignment></Obligation></Obligations></Result></Response>';
# INFO - Prüfen, ob HTTP Post korrekt ist
if (file_get_contents("php://input")=="")
{
	if ($_SERVER["REQUEST_METHOD"]!="HEAD")
	{ 
	header("Location: ./bin/403.php");
	}
exit;
}      
# INFO - CURRI Anfrage vom CUCM in der Variabel $cm_request speichern
$cm_request=print_r( file_get_contents("php://input"),true);
# 
# INFO - CURRI Anfrage vom CUCM in Logfile speichern
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG","REQUEST CM->ACCT - ".$transaction_id,$cm_request);};

# INFO - Anfrage vom CUCM in XML Struktur extrahieren
#        Variabel vom Typ DOMDocument erstellen
$xml_request = new DOMDocument;

# INFO - Übergabe der XML Anfrage in Variabel
$xml_request->loadXML(file_get_contents("php://input"));

# INFO - Ebene zum Abgreifen der Variabel definieren
$xml_request_path=new DOMXPath($xml_request);
$xml_request_path->registerNamespace("o", "urn:oasis:names:tc:xacml:2.0:context:schema:os");

# INFO - Absenderrufnummer aus XML extrahieren
$xml_value_array = $xml_request_path->query("//o:Attribute[@AttributeId='urn:Cisco:uc:1.0:callednumber']/o:AttributeValue");

# INFO - Schleife, Rufnumer in der Variabel $callerid speichern
foreach ($xml_value_array as $value) {
     $callerid=$value->nodeValue;    
}
# INFO - Prüfen, ob die Absenderrufnummer unterdrückt wurde
if ($callerid!='')
{
# INFO - SQL Abfrage -> Rufnummernauflösung in DB
$query_soapservice_runstatus="SELECT FIRSTNAME,LASTNAME,COMPANY FROM CUSTOMERS WHERE PHONE='".$callerid."' OR PHONE2='".$callerid."' OR MOBILE='".$callerid."' LIMIT 1";	
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG","SQL REQUEST - ".$transaction_id,$query_soapservice_runstatus);};

# INFO - SQL Query an die DB senden
$result_soapservice_runstatus=mysql_query($query_soapservice_runstatus);
$soapservice_runstatus=mysql_fetch_assoc($result_soapservice_runstatus);

$num_rows = mysql_num_rows($result_soapservice_runstatus);
}
else {$num_rows=0;};

if ($num_rows>0)
 {
    $callername=$soapservice_runstatus['LASTNAME'].", ".$soapservice_runstatus['FIRSTNAME']." (".$soapservice_runstatus['COMPANY'].")";
 }
# INFO - CURRI Anfrage vom CUCM beantworten 
if ($callername==null)
{
	# INFO - Anrufname ist nicht gesetzt -> Default Response
	$cm_response=$default_response;	
}
else 
{
	# INFO - Anrufername ist gesetzt -> modify Response
	$cm_response = '<?xml encoding="UTF-8" version="1.0"?><Response><Result><Decision>Permit</Decision><Status></Status><Obligations><Obligation FulfillOn="Permit" ObligationId="urn:cisco:xacml:policy-attribute"><AttributeAssignment AttributeId="Policy:simplecontinue"><AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">&lt;cixml ver="1.0"&gt;&lt;continue&gt;&lt;modify calledname="'.$callername.'"/&gt;&lt;/continue&gt; &lt;/cixml&gt;</AttributeValue></AttributeAssignment></Obligation></Obligations></Result></Response>';	
}
# INFO - Response erstellen
echo $cm_response;
# LOGGING (DEBUG) - CURRI Antwort vom Server in Logfile speichern
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG","RESPONSE ACCT->CM - ".$transaction_id,$cm_response);};	
?>
