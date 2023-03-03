<?php
################################################################################################################################################
######
######		APSystems2MQTT.php
######		Script by Ole Kristian Lona, to read data locally from APSystems ECU-R, and transfer through MQTT.
######		Version 0.1
######
################################################################################################################################################

################################################################################################################################################
######		Global variables
################################################################################################################################################

$code='';
$mosquitto_host='';
$mosquitto_user='';
$mosquitto_pass='';
$topicbase='';
$config='';
$ipaddress='';
$tcpport='';

################################################################################################################################################
######		getLocation - Function used to retrieve location data based on IP.
################################################################################################################################################

function getLocation() {
    /* Find my location based on IP. This should be sufficient. */
    $ch = curl_init("https://ipinfo.io");                                                                      
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,"GET");                                                                     
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers=array();
    $result = curl_exec($ch);
    #var_dump($result );

    $data=json_decode($result,true);
    $location=explode(",",$data["loc"]);

    return $location;

}

################################################################################################################################################
######		createconfig - Function to prompt for config data, and create config file.
################################################################################################################################################
function createconfig() {	
	$configcreated=false;
	global $folder;
	global $ipaddress;
    global $tcpport;
	global $mosquitto_host;
	global $mosquitto_user;
	global $mosquitto_pass;
	global $topicbase;
	global $create;
	global $debug;
	
	$configdefault=array(
		'ipaddress'=> '',
		'tcpport'=> '8899',
		'mosquitto_host'=> 'localhost',
		'mosquitto_user'=> '',
		'mosquitto_pass'=> '',
		'topicbase'=> '/APSystems/'
	);
	if(file_exists($folder . '/APSystems-config.php')){
		$config=array_replace($configdefault,include($folder . '/APSystems-config.php'));
	}
	else {
		$config=$configdefault;
	}
	
	$ipaddress=readline('Please input the IP address for your APSystems ECU [' . $config["ipaddress"] . ']: ');
	if($ipaddress == "") {$ipaddress=$config["ipaddress"];}
	$tcpport=readline('Please input the TCP port for your APSystems ECU [' . $config["tcpport"] . ']: ');
	if($tcpport == "") {$tcpport=$config["tcpport"];}

	$mosquitto_host=readline("Type the name of your mosquitto host [" . $config["mosquitto_host"] . "]: ");
	if($mosquitto_host == "") {$mosquitto_host=$config["mosquitto_host"];}
	$mosquitto_user=readline("Type login-name for Mosquitto [" . $config["mosquitto_user"] . "]: ");
	if($mosquitto_user == "") {$mosquitto_user=$config["mosquitto_user"];}
	if (strlen($mosquitto_user) >> 0 ) {
		$mosquitto_pass=readline("Type the password for your mosquitto user (will be saved in PLAIN text) [" . $config["mosquitto_pass"] . "]: ");
		if($mosquitto_pass == "") {$mosquitto_pass=$config["mosquitto_pass"];}
	}
	else {
		$mosquitto_pass="";
	}
	$topicbase=readline('Type the base topic name to use for Mosquitto [' . $config["topicbase"] . ']: ');
	if($topicbase == "") {$topicbase=$config["topicbase"];}
	if (strlen($topicbase) == 0) {
		$topicbase="/APSystems/";
	}
	if (substr($topicbase,-1) <> "/") {
		$topicbase = $topicbase . "/";
	}

    if($debug){print "Config info collected..." . PHP_EOL;}
    $config="<?php" . PHP_EOL . "return array(" . PHP_EOL . "        'ipaddress'=> '" . $ipaddress . "'," . PHP_EOL;
    $config = $config . "	'tcpport'=> '" . $tcpport . "'," . PHP_EOL;
    $config = $config . "	'mosquitto_host'=> '" . $mosquitto_host . "'," . PHP_EOL;
    $config = $config . "	'mosquitto_user'=> '" . $mosquitto_user . "'," . PHP_EOL;
    $config = $config . "	'mosquitto_pass'=> '" . $mosquitto_pass . "'," . PHP_EOL;
    $config = $config . "	'topicbase'=> '" . $topicbase . "'" . PHP_EOL;
    $config = $config . ");" . PHP_EOL . "?>" . PHP_EOL . PHP_EOL;

    if (file_put_contents($folder . "/APSystems-config.php", $config) <> false ) {
        if($debug){print "Configuration file created!" . PHP_EOL;}
        $configcreated=true;
    }

	return $configcreated;
}


################################################################################################################################################
######
######		This is the main script block
######
################################################################################################################################################
require("phpMQTT.php");

$folder=dirname($_SERVER['PHP_SELF']);

$shoptopts="dsc";
$longopts=array("debug","single","create");
$options=getopt($shoptopts,$longopts);

# Map options to variables, to simplify further script processing...
$debug=(array_key_exists("d",$options) || array_key_exists("debug",$options));
$single=(array_key_exists("s",$options) || array_key_exists("single",$options));
$create=(array_key_exists("c",$options) || array_key_exists("create",$options));

if ((file_exists($folder . '/APSystems-config.php') == false ) || $create) {
	$configcreated=createconfig();
	if($configcreated == false) {
		exit("Failed to create config! " . PHP_EOL);
	}
}

$location=getLocation();
$longitude=floatval($location[0]);
$latitude=floatval($location[1]);

$sun=date_sun_info(time(),$longitude,$latitude);

if ($debug) {echo "Sunrise: " . date("H:i:s dMy", $sun["sunrise"]) . PHP_EOL;}
if ($debug) {echo "Sunset: " . date("H:i:s dMy", $sun["sunset"]) . PHP_EOL;}



$config = include($folder.'/APSystems-config.php');
$run=true;
$count=0;
if (time() > $sun["sunrise"] && time() < $sun["sunset"]) {
    $daytime=true;
}
else {
    $daytime=false;
}

if ($debug && $daytime) {echo "Daytime: Yes" . PHP_EOL;}
if ($debug && !$daytime) {echo "Daytime: NO" . PHP_EOL;}

$mosquitto_host=$config['mosquitto_host'];
$mosquitto_user=$config['mosquitto_user'];
$mosquitto_pass=$config['mosquitto_pass'];
$topicbase=$config['topicbase'];
$ipaddress=$config['ipaddress'];
$tcpport=$config['tcpport'];

$mqtt_id = "APSystems-MQTT"; // make sure this is unique for connecting to sever - you could use uniqid()

$mqtt = new Bluerhinos\phpMQTT($mosquitto_host, "1883", $mqtt_id);

if(!$mqtt->connect(true, NULL, $mosquitto_user, $mosquitto_pass)) {
	exit(1);
}

if($create) {
	# Option create should exit after creating new config
	exit(0);
}

if($single) {
	retrieveandpublish($folder,$mqtt);
	exit(0);
}

$count=60;
while($mqtt->proc()){
	if ( $count==60) {
        if (time() > $sun["sunrise"] && time() < $sun["sunset"]) {
            retrieveandpublish($folder,$mqtt);
        }
        else {
            if (date("d",$sun["sunset"]) != date("d",time())){
                // If day from sun-info is different form "today", refresh sun-info
                $sun=date_sun_info(time(),$longitude,$latitude);
            }
        }
		$count=0;
	}
	sleep(1);
	$count = $count + 1;
}
		

$mqtt->close();

exit(0);


// Retrieveing information
function retrieveandpublish($folder,$mqtt) {
	global $mosquitto_host;
	global $mosquitto_user;
	global $mosquitto_pass;
	global $topicbase;
	global $debug;
	global $baseurl; 
	global $ipaddress;
	global $tcpport;


    /* Get common ECU information. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    } else {
        if ($debug) { echo "Socket created OK." . PHP_EOL;}
    }

    if ($debug) { echo "Attempting to connect to '$ipaddress' on port '$tcpport'..."; }
    $result = socket_connect($socket, $ipaddress, $tcpport);
    if ($result === false) {
        echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
    } else {
        if ($debug) { echo "OK." . PHP_EOL; }
    }

    $in = "APS1100160001END\n";
    $out = '';

    if ($debug) { echo "Sending request..."; }
    socket_write($socket, $in, strlen($in));
    if ($debug) { echo "OK." . PHP_EOL; }

    if ($debug) { echo "Reading response:\n\n"; }
    $out = socket_read($socket, 2048); 

    if ($debug) { echo "Closing socket..."; }
    socket_close($socket);
    if ($debug) { echo "OK.\n\n"; }


    if (strlen($out) > 50) {
        $ID=substr($out,13,12);
        $model=substr($out,25,2);
        $power=hexdec(bin2hex(substr($out,31,4)));
        $totday=hexdec(bin2hex(substr($out,35,4)))/100;
        $total=hexdec(bin2hex(substr($out,27,4)))/10;
        $inverters=hexdec(bin2hex(substr($out,46,2)));
        $invonline=hexdec(bin2hex(substr($out,48,2)));
        $verlen=substr($out,52,3);
        $version=substr($out,55,$verlen);
        
        if ($debug) { echo "ID: " . $ID . "\nModel: " . $model . "\nInverters: " . $inverters . "\nOnline: " . $invonline . "\nVersion: " . $version . "\nPower: " . $power . "\nToday: " . $totday . "\nTotal: " . $total . PHP_EOL; }

        $mqtt->publish($topicbase . $ID . "/model", $model);
        $mqtt->publish($topicbase . $ID . "/power", $power);
        $mqtt->publish($topicbase . $ID . "/todaytotal", $totday);
        $mqtt->publish($topicbase . $ID . "/lifetotal", $total);
        $mqtt->publish($topicbase . $ID . "/numberofinverters", $inverters);
        $mqtt->publish($topicbase . $ID . "/invertersonline", $invonline);
        $mqtt->publish($topicbase . $ID . "/version", $version);
    }

    /* Get information per inverter */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    } else {
        if ($debug) { echo "Socket created OK." . PHP_EOL; }
    }


    if ($debug) { echo "Attempting to connect to '$ipaddress' on port '$tcpport'..."; }
    $result = socket_connect($socket, $ipaddress, $tcpport);
    if ($result === false) {
        echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
    } else {
        if ($debug) { echo "OK." . PHP_EOL; }
    }


    $in = "APS1100280002" . $ID . "END\n";
    $out = '';

    if ($debug) { echo "Sending request..." . $in . "..."; }
    socket_write($socket, $in, strlen($in));
    if ($debug) { echo "OK." . PHP_EOL; }

    if ($debug) { echo "Reading response:\n\n"; }
    $out = socket_read($socket, 2048);

    if ($debug) { echo "Output from query: " . $out . PHP_EOL;} 
    if (strlen($out) > 50) {
        $invertersdata=hexdec(bin2hex(substr($out,17,2)));
        if ($debug) { echo "Number of inverters: " .$invertersdata . PHP_EOL; }
        $invoffset=26; 
        for ($i=1;$i<=$invertersdata;$i++){
            $inverterid=bin2hex(substr($out,$invoffset + 0,6));
            $inverterstate=hexdec(bin2hex(substr($out,$invoffset + 6,1)));
            $invtype=substr($out,$invoffset + 7,2);
            switch ($invtype) {
                case 0:
                    echo "Invalid inverter type\n";
                    $i=$invertersdata;
                    break;
                case 1:
                    $invfreq=hexdec(bin2hex(substr($out,$invoffset + 9,2))) * 0.1;
                    $invtemp=hexdec(bin2hex(substr($out,$invoffset + 11,2))) - 100;
                    $invpow1=hexdec(bin2hex(substr($out,$invoffset + 13,2)));
                    $invvolt1=hexdec(bin2hex(substr($out,$invoffset + 15,2)));  
                    $invpow2=hexdec(bin2hex(substr($out,$invoffset + 17,2)));
                    $invvolt2=hexdec(bin2hex(substr($out,$invoffset + 19,2)));
                    $invoffset += 21;
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/state", $inverterstate);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/type", $invtype);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/frequency", $invfreq);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/temperature", $invtemp);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/power", $invpow1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/voltage", $invvolt1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/2/power", $invpow2);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/2/voltage", $invvolt2);
                    break;  
                case 2:
                    $invfreq=hexdec(bin2hex(substr($out,$invoffset + 9,2))) * 0.1;
                    $invtemp=hexdec(bin2hex(substr($out,$invoffset + 11,2))) - 100;
                    $invpow1=hexdec(bin2hex(substr($out,$invoffset + 13,2)));
                    $invvolt1=hexdec(bin2hex(substr($out,$invoffset + 15,2)));  
                    $invpow2=hexdec(bin2hex(substr($out,$invoffset + 17,2)));
                    $invvolt2=hexdec(bin2hex(substr($out,$invoffset + 19,2)));
                    $invpow3=hexdec(bin2hex(substr($out,$invoffset + 21,2)));
                    $invvolt3=hexdec(bin2hex(substr($out,$invoffset + 23,2)));
                    $invoffset += 27;
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/state", $inverterstate);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/type", $invertertype);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/frequency", $inverterfreq);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/temperature", $invertertemp);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/power", $invpow1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/voltage", $invvolt1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/2/power", $invpow2);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/2/voltage", $invvolt2);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/3/power", $invpow3);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/3/voltage", $invvolt3);
                    break;  
                case 3:
                    $invfreq=hexdec(bin2hex(substr($out,$invoffset + 9,2))) * 0.1;
                    $invtemp=hexdec(bin2hex(substr($out,$invoffset + 11,2))) - 100;
                    $invpow1=hexdec(bin2hex(substr($out,$invoffset + 13,2)));
                    $invvolt1=hexdec(bin2hex(substr($out,$invoffset + 15,2)));  
                    $invpow2=hexdec(bin2hex(substr($out,$invoffset + 17,2)));
                    $invpow3=hexdec(bin2hex(substr($out,$invoffset + 19,2)));
                    $invpow4=hexdec(bin2hex(substr($out,$invoffset + 21,2)));
                    $invoffset += 23;
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/state", $inverterstate);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/type", $invertertype);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/frequency", $inverterfreq);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/temperature", $invertertemp);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/power", $invpow1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/1/voltage", $invvolt1);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/2/power", $invpow2);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/3/power", $invpow3);
                    $mqtt->publish($topicbase . $ID . "/" . $inverterid . "/4/power", $invpow4);
                    break;  
                }
                if ($debug) { echo "Inverter: " . $inverterid . "\nInverterstate: " . $inverterstate . "\nInvertertype: " . $invtype . "\nFrequency: " . $invfreq . "\nTemperature: " . $invtemp
            . "\nPower1: " . $invpow1 . "\nVoltage 1: " . $invvolt1 . "\nPower2: " . $invpow2 . "\nVoltage 2: " . $invvolt2 . PHP_EOL; }
        } 
    }


    if ($debug) { echo "Closing socket..."; }
    socket_close($socket);
    if ($debug) { echo "OK." . PHP_EOL; }
}
?>
