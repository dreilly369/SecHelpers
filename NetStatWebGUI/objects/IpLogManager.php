<?php

/**
 * Manages a JSON list of previously mapped IP addresses
 * to speed up mapping and reduce API calls
 *
 * @author Daniel Reilly (Executivewd at Hotmail) 2014
 */
class IpLogManager {

    private $log;
    private $config;

    public function __construct() {
        $logFile = dirname(dirname(__FILE__)) . "/configuration/seenIPs.json";
        $json = file_get_contents($logFile);
        $this->log = json_decode($json, true);
        $configFile = dirname(dirname(__FILE__)) . "/configuration/netstaterConfig.json";
        $json = file_get_contents($configFile);
        $this->config = json_decode($json, true);
    }

    public function getSeenIps() {
        $done = array();
        foreach ($this->log['seen'] as $ip => $dat) {
            $done[] = $ip;
        }
        return $done;
    }

    public function addLog($ip, $ipData) {
        $this->log['seen'][$ip] = $ipData;
        //$this->saveLog();
    }

    public function hasLog($ip) {
        $logs = $this->log['seen'];
        $seen = false;
        foreach ($logs as $rIP => $ipData) {
            if ($rIP == $ip) {
                $seen = true;
                break;
            }
        }
        return $seen;
    }

    public function getLog($ip) {
        return $this->log['seen'][$ip];
    }

    public function saveLog() {
        $logFile = dirname(dirname(__FILE__)) . "/configuration/seenIPs.json";
        file_put_contents($logFile, json_encode($this->log));
    }

    public function deleteLog($ip){
        if(isset($this->log['seen'][$ip])){
            unset($this->log['seen'][$ip]);
            return True;
        }
    }
}
