<?php

use CS\Devices\Manager,
    CS\Models\Site\SiteNotFoundException;

class SiteRecordTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     * @var \PDO
     */
    static $db;
    static $createdId;

    /**
     *
     * @var Manager 
     */
    private $manager;

    public static function setUpBeforeClass()
    {
        global $db;
        self::$db = $db;
    }

    public function setUp()
    {
        $this->manager = new Manager(self::$db);
    }

    public function testCreate()
    {
        $code = $this->manager->getUserDeviceAddCode(1);
        $this->assertNotNull($code);
        $this->assertEquals($code, $this->manager->getUserDeviceAddCode(1));
        
        var_dump($this->manager->getDevId('12312'));
    }


    public function taestLoadError()
    {
        try {
            $this->site->load(-1);
        } catch (SiteNotFoundException $e) {
            return;
        }

        $this->fail('An expected exception has not been raised.');
    }

}
