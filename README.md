# Cisco CUCM Reverse-Number-Lookup 
Script for native number resolution - Cisco Unified Communications Manager using CURRI

<h1 align="center">
  <img width="80%" src="https://github.com/PMD3VSolution/CUCM.Reverse-Number-Lookup/blob/master/src/integration.png">
</h1>

### General information
It is implemented via the Cisco Unified Remote Routing Interface (CURRI), which the CUCM supports from version 10.
The CURRI interface offers a multitude of possibilities to intervene in the call routing and to realize individual routing scenarios easily. Using an external call control profile, the CUCM sends an HTTP post (XML Schema) to a defined server that processes this request.
The ECC profile can be individually linked to different routing elements (directory number, translation pattern, route pattern etc.). The function can therefore be activated for specific extensions, areas or the entire cluster.

### CUCM Konfiguration
Setup an ECC profile

<h1 align="center">
  <img width="80%" src="https://github.com/PMD3VSolution/CUCM.Reverse-Number-Lookup/blob/master/src/ecc_profile_config_0.png">
</h1>

The External Call Control Profile defines to which server the CUCM sends the HTTP post. Optionally, a backup system and load balancing can be activated. The Routing Request Timer determines the maximum waiting time in which the CUCM expects a response from the external server.</br>The "Call Treatment on Failures" parameter defines the event that is executed when an error occurs or the Routing Request Timer is exceeded.</br></br>
Note: The specified port is important when configuring the URL. This must be 80 (HTTP), otherwise the CUCM will not send an HTTP POST.

<h1 align="center">
  <img width="80%" src="https://github.com/PMD3VSolution/CUCM.Reverse-Number-Lookup/blob/master/src/ecc_profile_config_1.png">
</h1>
</br>The connection of the ECC profile can be used to decide individually which services are to be provided.</br>

### Troubleshooting
For the analysis of the HTTP requests and the processing you can adjust the logging on the CUCM.
<h1 align="center">
  <img width="50%" src="https://github.com/PMD3VSolution/CUCM.Reverse-Number-Lookup/blob/master/src/tracelevel.png">
</h1>

You can find the corresponding information in the Call Manager SDI Log.
Additionally I built a logging into the script, which can be adjusted via the parameter "$logging_level".

### Supported Datasources
- local DB (mariaDB, Mysql e.g)
- LDAP MS (DC)
