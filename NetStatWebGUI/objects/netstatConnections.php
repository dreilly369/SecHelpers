<?php

/*
 * Writes a json file containing the current connection details
 * To be used later by the locateIp script
 */

//look up OS and use system specific command for Windows or Linux
$onLinux = (strcasecmp(PHP_OS, "Linux") == 0) ? true : false;
$cmd = ($onLinux) ? "netstat --tcp --numeric" : "netstat -n";
$connPipe = dirname(__FILE__) . '/connectionPipe.json';
$response = array();
$out;
exec($cmd, $response, $out);

$headers = ($onLinux) ?
        array(
    'proto',
    'recv_q',
    'send_q',
    'local_address',
    'foreign_address',
    'state'
        ) :
        array(
    'proto',
    'local_address',
    'foreign_address',
    'state'
);
$outputData = array();
$dataStart = false;
foreach ($response as $line) {
    if (stripos($line, "Foreign Address")) {
        $dataStart = true;
    } else if ($dataStart == true) {
        $data = rowToArray($line);
        $newRow = array();
        for ($i = 0; $i < count($headers); $i++) {
            $newRow[$headers[$i]] = $data[$i];
        }
        //Get the local address and port split
        $ipx = explode(":", $newRow['local_address']);
        $newRow['local_port'] = @$ipx[1]; //Save the port to it's own field'
        $newRow['local_address'] = $ipx[0]; //Now without the port
        //Now the remote info
        $ipx = explode(":", $newRow['foreign_address']);
        $newRow['foreign_port'] = @$ipx[1]; //Save the port to it's own field'
        $newRow['foreign_address'] = $ipx[0]; //Now without the port
        $outputData[$ipx[0]] = $newRow;
    } else {
        //skip unused row
    }
}
$conns = json_encode($outputData);

print $conns."\n";
exit();

function rowToArray($row) {
    $stripped = preg_replace('!\s+!', ' ', $row);
    $tokens = explode(" ", $stripped);
    return $tokens;
}
