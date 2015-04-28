<?php

namespace CS\Devices;

use CS\Models\Product\ProductRecord;
use CS\Models\License\LicenseRecord;
use CS\Models\Device\Limitation\DeviceLimitationRecord;

class DeviceObserver extends DeviceObserverDependencies {
    
    public function assignLicenseToDevice()
    {
        $this->getMainDb()->beginTransaction();

        if(!$this->beforeSave())
            throw new \Exception('DeviceObserver->beforeSave() return FALSE');
            
        if(!$this->getDevice()->getId())
            $this->getDevice()->save();
        
        $this->getLicense()
            ->setDeviceId($this->getDevice()->getId())
            ->setStatus(LicenseRecord::STATUS_ACTIVE)
            ->save();

        $deviceManager = new \CS\Devices\Manager($this->getMainDb());
        $deviceManager->licenseOnAssign($this->getLicense());
        
        $resetCount = ($this->getLicense()->getProductType() == ProductRecord::TYPE_PACKAGE);
        $limitations = new Limitations($this->getMainDb());
        $limitations->updateDeviceLimitations($this->getDevice()->getId(), $resetCount);

        if($this->getMainDb()->commit()) {
            $this->afterSave();
            return true;
        } else {
            $this->logger->addCritical("Can't assign Device {$this->getDevice()->getId()} to License {$this->getLicense()->getId()} on " . __FILE__ . ' ' . __LINE__);
            return false;
        }
    }

    public function addICloudDevice(){
        $this->getMainDb()->beginTransaction();

        $this->getDevice()->save();

        $this->getICloudDevice()
            ->setDevId($this->getDevice()->getId())
            ->save();

        $this->getLicense()
            ->setDeviceId($this->getDevice()->getId())
            ->setStatus(LicenseRecord::STATUS_ACTIVE)
            ->save();
        
         $deviceManager = new \CS\Devices\Manager($this->getMainDb());
        $deviceManager->licenseOnAssign($this->getLicense());

        $deviceDb = $this->getDataDb($this->getDevice()->getId());
        $deviceDb->beginTransaction();
        
        if(!$this->createDeviceInitialSettings($deviceDb, $this->getDevice()->getId()))
            return false;

        (new DeviceLimitationRecord($this->getMainDb()))
            ->setDevice($this->getDevice())
            ->save();
        (new Limitations($this->getMainDb()))
            ->updateDeviceLimitations($this->getDevice()->getId(), true);

        if($deviceDb->commit() && $this->getMainDb()->commit()){
            $this->afterSave();
            return true;
            
        } else return false;
    }

}