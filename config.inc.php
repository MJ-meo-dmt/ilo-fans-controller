<?php

/*
  ILO ACCESS CREDENTIALS
  --------------
  These are used to connect to the iLO
  interface and manage the fan speeds.
*/

$ILO_HOST = 'your-ilo-address';  // Ex. 192.168.1.69
$ILO_USERNAME = 'your-ilo-username';  // Ex. Administrator
$ILO_PASSWORD = 'your-ilo-password';  // Ex. AdministratorPassword1234

/*
  MISCELLANEOUS SETTINGS
  --------------
  These allows you to customize
  the behavior of the tool.
*/

/*
OPTIONAL DASHBOARD iLO LINKS
--------------
Direct link opens https://$ILO_HOST.
Tunnel link is useful if iLO is reachable only through SSH local port forwarding.
*/
$ILO_DIRECT_URL = "https://$ILO_HOST";
$ILO_TUNNEL_URL = "https://localhost:8443/"; // If you do ssh -L
$SHOW_ILO_TUNNEL_LINK = true;

// Minimum fan speed percentage, from 0% (DANGEROUS) to 100%
$MINIMUM_FAN_SPEED = 10;

?>
