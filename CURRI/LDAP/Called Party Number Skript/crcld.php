<?php
#################################################
#    Dateiname:               ecc.php           #
#    Dateiversion:           1.0                #
#    Ersteller:              Peter Muchau       #
#    Beschreibung:           ecc.php            #
#    Letzter Bearbeiter:     Peter Muchau       #
#    Datum der Bearbeitung:  17.07.2018         #
#################################################
#                                               #
#    Beschreibung:                              #
#    CUCM ECCP CURRI - Calling Pary Number      #
#                                               #
#################################################
#
# Logging im Skript aktivieren (true | false)
$logging_enabled=true;
# Logginglevel (debug | info) 
$logging_level="debug";
#
#################################################
#                  Parameter                    #
#################################################
#
# Parameter - LDAP Username
$auth_user="XXX";
# Parameter - LDAP Passwort
$auth_pass="ZZZ";
# Parameter - LDAP Server
$ldap_server = "ldap://XX.XX.XX.XX";
# Parameter - LDAP Server Port
$ldap_port = "389";
# Parameter - LDAP User Base
$base_dn = "OU=xx,DC=xx,DC=xx,DC=xx";
# Zeitzone definieren
date_default_timezone_set("Europe/Berlin");
#
#################################################
#                 Funktionen                    #
#################################################
#
# INFO - Prozedur zum Erstellen eines Logfiles
function proc_logging($level,$typ,$message){
  $logfile = fopen("./log/threads.log","a");
  fwrite($logfile, date("d.m.y H:i:s")." [".$level."] [".$typ."] ".$message."\r\n");
  fclose($logfile);
}
#################################################
#                  Mainskript                   #
#################################################
#
# INFO - Dynamisches Transaction-ID für die Sitzung erstellen
$transaction_id = "TRANSID".rand(10,90).rand(30,90).rand(20,90).rand(20,50).chr(rand(65,90)); 
# INFO - Default Antwort, wenn kein Anrufername gesetzt ist
$response_default = '<?xml encoding="UTF-8" version="1.0"?><Response><Result><Decision>Permit</Decision><Status></Status><Obligations><Obligation FulfillOn="Permit" ObligationId="urn:cisco:xacml:policy-attribute"><AttributeAssignment AttributeId="Policy:simplecontinue"><AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">&lt;cixml ver="1.0"&gt;&lt;continue&gt;&lt;/continue&gt; &lt;/cixml&gt;</AttributeValue></AttributeAssignment></Obligation></Obligations></Result></Response>';
# INFO - Herstellen der Verbindung zum LDAP Server
if (!($connect=@ldap_connect($ldap_server,$ldap_port)))
{
  # ERROR - LDAP Fehler
  echo "ldap_error: " . ldap_error($connect);
}
# INFO Parameter für die LDAP Verbindung
ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($connect, LDAP_OPT_REFERRALS, 0);
# INFO Anbindung an das LDAP Verzeichnis
if (!($bind=@ldap_bind($connect, $auth_user, $auth_pass)))
{
  # ERROR - LDAP Fehler
  echo "ldap_error: " . ldap_error($connect);
}
# INFO - Prüfen, ob HTTP Post korrekt ist
if (file_get_contents("php://input")=="")
{
	if ($_SERVER["REQUEST_METHOD"]!="HEAD")
	{ 
	header("Location: ./bin/403.php");
	}
exit;
}      
#################################################
#               CURRI Anfrage                   #
#################################################
#
# INFO - CURRI Anfrage vom CUCM in der Variabel $cm_request speichern
$cm_request=print_r( file_get_contents("php://input"),true);
#
# INFO - CURRI Anfrage vom CUCM in Logfile speichern
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"REQUEST CM->ACCT - ".$cm_request);};
#
# INFO - Anfrage vom CUCM in XML Struktur extrahieren
#        Variabel vom Typ DOMDocument erstellen
$xml_request = new DOMDocument;
#
# INFO - Übergabe der XML Anfrage in Variabel
$xml_request->loadXML(file_get_contents("php://input"));
#
# INFO - Ebene zum Abgreifen der Variabel definieren
$xml_request_path=new DOMXPath($xml_request);
$xml_request_path->registerNamespace("o", "urn:oasis:names:tc:xacml:2.0:context:schema:os");
#
# INFO - Absenderrufnummer aus XML extrahieren
$xml_value_array = $xml_request_path->query("//o:Attribute[@AttributeId='urn:Cisco:uc:1.0:callednumber']/o:AttributeValue");
#
# INFO - Schleife, Rufnumer in der Variabel $callerid speichern
foreach ($xml_value_array as $value) {
     $callerid=$value->nodeValue;    
}
# INFO - Prüfung der Rufnummernlänge
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"STRING LENGTH - ".strlen($callerid));};
# INFO - Prüfen, ob die Absenderrufnummer unterdrückt wurde
if (($callerid!='')and(strlen($callerid)>6))
{
# INFO - Rufnummernformatierung
$filtercallerid="*".substr($callerid,-3);
# INFO - LDAP Filter
$filter = "(&(|(telephoneNumber=".$filtercallerid.")(mobile=".$filtercallerid."))(|(objectClass=contact)(objectClass=user)))";
# INFO - Logging LDAP Filter
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"LDAP FILTER - ".$filter);};
# INFO - Abfrage an den DC Senden
if (!($search=@ldap_search($connect,$base_dn,$filter))) {
        echo "ldap_error: " . ldap_error($connect);
}
#
# INFO - Ergebnis der Abfrage im Array speichern
$info = ldap_get_entries($connect, $search);
# INFO - Anzahl der Datensätze ermitteln
$count = ldap_count_entries($connect,$search);
# INFO - Logging LDAP Count
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"LDAP FILTER - RESULTS: ".$count);};
}
else {$count=0;};
#
# INFO - Logging - Regex Normalisierung E164
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"XTRANS NUMBER - ".$callerid);};
#INFO - Normalisierung - Ortsnetz
if(preg_match('/^0[1-9].*/', $callerid)){$callerid=preg_replace('/^0([1-9].*)/', '+49xxx$1', $callerid);} 
#INFO - Normalisierung - National
else if (preg_match('/^00[1-9].*/', $callerid)){$callerid=preg_replace('/^00([1-9].*)/', '+49$1', $callerid);}
#INFO - Normalisierung - International
else if (preg_match('/^000[1-9].*/', $callerid)){$callerid=preg_replace('/^000([1-9].*)/', '+$1', $callerid);} 
#
# INFO - Logging - Regex Normalisierung E164
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"XTRANS NUMBER - ".$callerid);};
#
# INFO - Parameter Callername zurücksetzen
$callername='';
# INFO - Datensatz gefunden
if ($count>0)
 {
    # INFO -  Logging - Parsing startet
    if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"PARSING FILTER - START");};
	# INFO -  Zurücksetzen der Variabeln - $company
	$company='';
    # INFO -  Zurücksetzen der Variabeln - $firstname
	$firstname='';	
	# INFO -  Zurücksetzen der Variabeln - $lastname
	$lastname='';
	$i=0;
	$match="false";
	# INFO - Auswerten der Rückgabewerte aus dem LDAP
    do {	
	    # Normalisierung der Rufnummer
		if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"FORMATING NUMBER - LDAP DATA: telephonenumber: ".$info[$i]["telephonenumber"][0]." mobile: ".$info[$i]["mobile"][0]);};
		$phonenumber=str_replace(' ','',$info[$i]["telephonenumber"][0]); 
		$phonenumber=str_replace('(','',$phonenumber); 
		$phonenumber=str_replace(')','',$phonenumber); 
		$phonenumber=str_replace('-','',$phonenumber); 
		$mobilephone=str_replace(' ','',$info[$i]["mobile"][0]); 
		$mobilephone=str_replace('(','',$mobilephone); 
		$mobilephone=str_replace(')','',$mobilephone); 
		$mobilephone=str_replace('-','',$mobilephone);
		if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"FORMATING NUMBER - RESULT: telephonenumber: ".$phonenumber." mobile: ".$mobilephone);};
		# INFO - Logging - Rarsing 
		
		if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"PARSING CHECK - ".$phonenumber." (LDAP) = ".$callerid." (NUMBER) OR ".$mobilephone." (LDAP) = ".$callerid." - C=".utf8_decode($info[$i]["company"][0])." GN=".utf8_decode($info[$i]["givenname"][0])." SN=".utf8_decode($info[$i]["sn"][0]));};
		if (($phonenumber==$callerid)OR($mobilephone==$callerid)){
		    # INFO - Logging
		    if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"PARSING CHECK - MATCH");};
			# INFO - Setzen der Parameter
			$company=utf8_decode($info[$i]["company"][0]);
			$firstname=utf8_decode($info[$i]["givenname"][0]);
			$lastname=utf8_decode($info[$i]["sn"][0]);
			$match="true";
			if ($company <> ''){$callername="(".$company.") ".$lastname.", ".$firstname;}
			else {$callername=$lastname.", ".$firstname;}
		}
		else {if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"PARSING CHECK - NO MATCH");};}
		$i++;
	}while(($match<>'true')AND($i<$count));
 }
# INFO - CURRI Anfrage vom CUCM beantworten 
if ($callername==''){
	# INFO - Anrufname ist nicht gesetzt -> Default Response
	$cm_response=$response_default;	
}
else {
	# INFO - Anrufername ist gesetzt -> modify Response
	$cm_response = '<?xml encoding="UTF-8" version="1.0"?><Response><Result><Decision>Permit</Decision><Status></Status><Obligations><Obligation FulfillOn="Permit" ObligationId="urn:cisco:xacml:policy-attribute"><AttributeAssignment AttributeId="Policy:simplecontinue"><AttributeValue DataType="http://www.w3.org/2001/XMLSchema#string">&lt;cixml ver="1.0"&gt;&lt;continue&gt;&lt;modify calledname="'.$callername.'"/&gt;&lt;/continue&gt; &lt;/cixml&gt;</AttributeValue></AttributeAssignment></Obligation></Obligations></Result></Response>';	
}
# INFO - Response erstellen
echo $cm_response;
# LOGGING (DEBUG) - CURRI Antwort vom Server in Logfile speichern
if (($logging_enabled==true)and($logging_level=="debug")){proc_logging("DEBUG",$transaction_id,"RESPONSE ACCT->CM - ".$cm_response);};	
?>
