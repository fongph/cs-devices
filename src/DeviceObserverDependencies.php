<?php namespace CS\Devices;

use System\DI;
use Monolog\Logger;
use CS\Models\Device\DeviceRecord;
use CS\Models\Device\DeviceICloudRecord;
use CS\Models\Device\DeviceNotFoundException;
use CS\Models\License\LicenseRecord;
use CS\Models\License\LicenseNotFoundException;

abstract class DeviceObserverDependencies {
    
    /** @var Logger */
    protected $logger;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    
    /** @var DeviceRecord */
    private $device;
    public function setDevice(DeviceRecord $deviceRecord)
    {
        $this->device = $deviceRecord;
        return $this;
    }
    public function getDevice()
    {
        if(is_null($this->device))
            throw new DeviceNotFoundException;
        
        return $this->device;
    }
    
    /** @var DeviceICloudRecord */
    private $iCloudDevice;
    public function setICloudDevice(DeviceICloudRecord $iCloudDevice)
    {
        $this->iCloudDevice = $iCloudDevice;
        return $this;
    }
    public function getICloudDevice()
    {
        if(is_null($this->iCloudDevice))
            throw new DeviceNotFoundException;

        return $this->iCloudDevice;
    }

    
    /** @var LicenseRecord */
    private $license;
    public function setLicense(LicenseRecord $licenseRecord)
    {
        $this->license = $licenseRecord;
        return $this;
    }
    public function getLicense()
    {
        if(is_null($this->license))
            throw new LicenseNotFoundException;
        
        return $this->license;
    }


    private $afterSave;
    public function setAfterSave(callable $callable)
    {
        $this->afterSave = $callable;
        return $this;
    }
    protected function afterSave()
    {
        if(is_callable($this->afterSave))
            call_user_func($this->afterSave);
    }

    /** @var \PDO */
    private $mainDb;
    public function setMainDb(\PDO $mainDb)
    {
        $this->mainDb = $mainDb;
        return $this;
    }
    protected function getMainDb()
    {
        if(is_null($this->mainDb)){
            throw new InvalidMainDb;
        }
        return $this->mainDb;
    }
    
    private $dataDbHandler;
    //todo try set callable
    public function setDataDbHandler($dbHandler)
    {
        $this->dataDbHandler = $dbHandler;
        return $this;
    }
    /**
     * @param $devId
     * @return \PDO
     * @throws InvalidDataDb
     */
    public function getDataDb($devId)
    {
        if(is_null($this->dataDbHandler))
            throw new InvalidDataDb;
        return call_user_func_array($this->dataDbHandler, array($devId));
    }

    /**
     * @deprecate
     * @param \PDO $db
     * @param $devId
     * @return bool
     */
    protected function createDeviceInitialSettings(\PDO $db, $devId)
    {
        //todo класс должен оперировать обьектами
        $status = (int)$db->exec("INSERT INTO `dev_settings` SET `dev_id` = {$db->quote($devId)}");
        $status *= (int)$db->exec("INSERT INTO `dev_info` SET `dev_id` = {$db->quote($devId)}");
        
        if(!$status){
            $this->logger->addCritical(__FUNCTION__ . " FAIL DevID: {$devId}");
            return false;
            
        } else return true;
    }

}

class InvalidDataDb extends \Exception {}
class InvalidMainDb extends \Exception {}