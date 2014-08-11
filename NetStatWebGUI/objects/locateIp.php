<?php

/**
 * This script reads the output of the netstatConnections.php script and
 * detirmines if each IP needs to be searched via the API
 * 
 * @author Daniel Reilly <ExecutiveWD at Hotmail dot com>
 */
require_once 'IpLogManager.php';

//Setup misc variables
$apiAddress = "http://www.telize.com/geoip/";
$updateCmd = "php " . dirname(__FILE__) . "/netstatConnections.php";
$connectedIps = array();
$connectionDetails = array();

//Pass the configuration to the front-end
if (isset($_REQUEST['conf'])) {
    $conf = file_get_contents(dirname(dirname(__FILE__)) . "/configuration/netstaterConfig.json");
    print $conf;
    exit(0);
}


$res = -1;
$rawData = array();
exec($updateCmd, $rawData, $res); //Force an update to the connections pipe file
if ($res != 0) {
    print "Couldn't Run netstatConnections.php\n";
    exit($res);
}

//Decode the data from the script into an associated array keyed by foreign IP address
$datas = json_decode($rawData[0], true);

foreach ($datas as $l) {
    $connectedIps[$l['foreign_address']] = $l;
}

//print_r(json_encode($connectedIps));
//IP Log Setup
$logMan = new IpLogManager();
$testIps = array();
$seenIps = $logMan->getSeenIps();

//Get the list of IPs to filter
$rawConf = file_get_contents(dirname(dirname(__FILE__)) . "/configuration/netstaterConfig.json");
$conf = json_decode($rawConf, true);
$filterList = $conf['ignored_ips'];
unset($rawConf); //Free up some memory
$response = array("rows" => count($connectedIps), "filters" => implode(",", $filterList));
$i = 0;
if (count($connectedIps) == 0) {
    print j;
}
foreach ($connectedIps as $ip => $dat) {
    $i++;
    if (startsWithAny($filterList, $ip)) {
        $response["row_{$i}"] = "Filtered";
        continue;
    }
    if (in_array($ip, $seenIps)) {
        $response["row_{$i}"] = "In array";
        //Get saved search result
        $rawResult = $logMan->getLog($ip);
    } else {
        //Get the result from the External API
        $curl = curl_init($apiAddress . $ip);
        curl_setopt($curl, CURLOPT_VERBOSE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $rawResult = curl_exec($curl); //Get the JSON response
        $response["row_{$i}"] = "New";
    }
    
    $realResult = json_decode($rawResult, true); //Turn it into an array
    if($realResult===null){
        $realResult = $rawResult;
    }
    
    if (!isset($realResult['code']) || $realResult['code'] != 401) {
        $logMan->addLog($ip, $realResult);
        $merged = array_merge($realResult, $dat);
        $connectionDetails[] = $merged;
    }
}
$numIps = count($connectionDetails);
$responseData = array_merge($response,array('connections' => $connectionDetails));
respondWith(0, "Completed Lookup with {$numIps} IPs", $responseData);

/**
 * Given an IP and an array of Strings to search
 * determine if the IP starts with any of hte provided strings.
 * To match any IP in a netmask use an asterisk after the IP Block to sarch for
 * 
 * Example Strings to search for:
 * 127.0.0.1 Matches the full IP exactly
 * 127.0.* Matches any IP starting with 127.0.x.x
 * 127.* Matches any IP starting with 127.x.x.x
 * 
 * @param Array or String $strings The strings to compare the IP against
 * @param String $ip The IP to compare the start of
 * @return boolean True if a match is found False otherwise
 */
function startsWithAny($strings, $ip) {
    if (is_array($strings)) {
        foreach ($strings as $str) {

            $ns = str_replace("*", "", $str);
            if (strpos($ip, $ns) === 0) {
                return true;
            }
        }
        return false;
    } else {
        $ns = str_replace("*", "", $str);
        if (strpos($ip, $ns) == 0) {
            return true;
        }
        return false;
    }
}

/**
 * Create a JSON response with the Status, message, and Data array.
 * Print the response to the screen and exit with the indicated status
 */
function respondWith($status, $msg, $data = array()) {
    $infoBlock = array("status" => $status, "message" => $msg);
    $response = array_merge($infoBlock, $data);
    print(json_encode($response));
    exit($status);
}
