<?php

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-08-10 at 16:23:39.
 */
class IpLogManagerTest extends PHPUnit_Framework_TestCase {

    /**
     * @var IpLogManager
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new IpLogManager();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }

    /**
     * @covers IpLogManager::__construct
     */
    public function testConstructor_retrunsIpLogManager() {
        $this->assertEquals("IpLogManager",  get_class($this->object));
    }
    
    /**
     * @covers IpLogManager::getSeenIps
     */
    public function testGetSeenIps_retrunsArray() {
        $ips = $this->object->getSeenIps();
        $this->assertTrue(is_array($ips));
    }

    /**
     * @covers IpLogManager::getSeenIps
     */
    public function testGetSeenIps_afterAddAndSave_fileIsValidJSON() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $this->object->saveLog();
        $raw = file_get_contents(dirname(dirname(__FILE__)) . "/configuration/seenIPs.json");
        $str = json_decode($raw);
        if ($str === NULL) {
            //Failed to parse JSON
            $this->assertTrue(False);
        } else {
            //Parsed as valid JSON
            $this->assertTrue(True);
        }
    }
    
    /**
     * @covers IpLogManager::addLog
     */
    public function testAddLog_goodLog_ipAddedToSeen() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $seen = $this->object->getSeenIps();
        $ips = array();
        foreach ($seen as $data) {
            $ips[] = $data;
        }
        $this->assertTrue(in_array($fakeIp, $ips));
    }
    
    /**
     * @covers IpLogManager::deleteLog
     * @depends testAddLog_goodLog_ipAddedToSeen
     */
    public function testDeleteLog_logExists_removedFromArray() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $this->object->deleteLog($fakeIp);
        $seen = $this->object->getSeenIps();
        $ips = array();
        foreach ($seen as $data) {
            $ips[] = $data;
        }
        $this->assertFalse(in_array($fakeIp, $ips));
    }
    
    /**
     * @covers IpLogManager::deleteLog
     */
    public function testDeleteLog_logNotExist_returnsFalse() {
        $fakeIp = "999.999.999.999";
        $this->assertTrue($this->object->deleteLog($fakeIp) == null);
    }

    /**
     * @covers IpLogManager::hasLog
     * @depends testAddLog_goodLog_ipAddedToSeen
     */
    public function testHasLog_logExists_returnsTrue() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $seen = $this->object->getSeenIps();
        $ips = array();
        foreach ($seen as $data) {
            $ips[] = $data;
        }
        $this->assertTrue($this->object->hasLog($fakeIp));
    }

    /**
     * @covers IpLogManager::hasLog
     * @depends testAddLog_goodLog_ipAddedToSeen
     */
    public function testHasLog_logNotExists_returnsFalse() {
        $fakeIp = "999.999.999.999";
        $seen = $this->object->getSeenIps();
        $ips = array();
        foreach ($seen as $data) {
            $ips[] = $data;
        }
        $this->assertFalse($this->object->hasLog($fakeIp));
    }

    /**
     * @covers IpLogManager::getLog
     * @depends testAddLog_goodLog_ipAddedToSeen
     * @depends testHasLog_logExists_returnsTrue
     */
    public function testGetLog_logExists_retursLog() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $seen = $this->object->getLog($fakeIp);

        $this->assertTrue($seen["test"] == "data");
    }

    /**
     * @covers IpLogManager::saveLog
     */
    public function testSaveLog_newManagerSeesLog() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));
        $seen = $this->object->getSeenIps();
        $this->object->saveLog();
        $nMan = new IpLogManager();
        $secondSeen = $nMan->getSeenIps();
        $ips = array();
        $this->assertTrue($nMan->hasLog($fakeIp));
    }

    /**
     * @covers IpLogManager::getSeenIps
     * @depends testAddLog_goodLog_ipAddedToSeen
     * @depends testDeleteLog_logExists_removedFromArray    
     */
    public function testGetSeenIps_afterDeleteAndSave_fileIsValidJSON() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));//Make Sure log exists
        $this->object->deleteLog($fakeIp);//Now remove it
        $this->object->saveLog();
        $raw = file_get_contents(dirname(dirname(__FILE__)) . "/configuration/seenIPs.json");
        $str = json_decode($raw);
        if ($str === NULL) {
            //Failed to parse JSON
            $this->assertTrue(False);
        } else {
            //Parsed as valid JSON
            $this->assertTrue(True);
        }
    }
    
    /**
     * @covers IpLogManager::getSeenIps
     * @depends testAddLog_goodLog_ipAddedToSeen
     * @depends testDeleteLog_logExists_removedFromArray
     * @depends testSaveLog_newManagerSeesLog
     */
    public function testGetSeenIps_afterDeleteAndSave_newManagerDoesntSeeLog() {
        $fakeIp = "111.222.111.222";
        $this->object->addLog($fakeIp, array("test" => "data"));//Make Sure log exists
        $this->object->deleteLog($fakeIp);//Now remove it
        $this->object->saveLog();
        $nMan = new IpLogManager();
        $this->assertFalse($nMan->hasLog($fakeIp));
    }
}
