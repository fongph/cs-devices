<?php

namespace CS\Devices;

use PDO,
    CS\Models\Site\SiteRecord,
    CS\Models\User\UserRecord,
    CS\Models\Device\DeviceRecord,
    CS\Models\License\LicenseRecord,
    CS\Models\Product\ProductRecord;

/**
 * Description of Manager
 *
 * @author root
 */
class Manager
{

    private $redisConfig;

    /**
     * Database connection
     * 
     * @var PDO
     */
    protected $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * 
     * @return PDO
     */
    public function getDb()
    {
        return $this->db;
    }

    public function setRedisConfig($array)
    {
        $this->redisConfig = $array;
    }

    /**
     * 
     * @return \Predis\Client
     */
    private function getRedis()
    {
        if ($this->redisConfig !== null) {
            return new \Predis\Client($this->redisConfig);
        }

        return new \Predis\Client();
    }

    /**
     * 
     * @param int $id
     * @return UserRecord
     */
    public function getUser($id = null)
    {
        $user = new UserRecord($this->db);

        if (isset($id)) {
            $user->load($id);
        }

        return $user;
    }

    /**
     * 
     * @param int $id
     * @return SiteRecord
     */
    public function getSite($id = null)
    {
        $site = new SiteRecord($this->db);

        if (isset($id)) {
            $site->load($id);
        }

        return $site;
    }

    /**
     * 
     * @param type $id
     * @return DeviceRecord
     */
    public function getDevice($id = null)
    {
        $device = new DeviceRecord($this->db);

        if (isset($id)) {
            $device->load($id);
        }

        return $device;
    }

    public function deviceExist($devUniqueId)
    {
        $uniqueId = $this->db->quote($devUniqueId);

        return $this->getDb()->query("SELECT COUNT(*) FROM `devices` WHERE `unique_id` = {$uniqueId}")->fetchColumn() > 0;
    }

    public function getDeviceId($devUniqueId)
    {
        $uniqueId = $this->db->quote($devUniqueId);

        return $this->getDb()->query("SELECT `id` FROM `devices` WHERE `unique_id` = {$uniqueId} LIMIT 1")->fetchColumn();
    }

    public function getUserDeviceAddCode($userId)
    {
        $deviceCode = new DeviceCode($this->getRedis());

        return $deviceCode->createCode($userId);
    }

    public function addDeviceWithCode($deviceUniqueId, $code, $name)
    {
        $deviceCode = new DeviceCode($this->getRedis());

        if (($userId = $deviceCode->getCodeUser($code)) === null) {
            throw new DeviceCodeNotFoundException("Code not found!");
        }

        $deviceRecord = new DeviceRecord();
        $deviceRecord->setUniqueId($deviceUniqueId)
                ->setUserId($userId)
                ->setName($name)
                ->save();

        $limitations = new Limitations($this->db);
        $limitations->updateDeviceLimitations($deviceRecord->getId(), true);
        $deviceCode->removeCode($code);
        /*$message = '<!DOCTYPE html>
        <html>
        <head>
        <title>Pumpic: New Device Added</title>
        </head>
        <body>
        <div class="wrap" style="margin: 20px auto;width: 700px;overflow: hidden;font-family: Arial, sans-serif;font-size: 16px;color:#333;">
        <a class="logo" href="http://cp.pumpic.com" style="float: right;margin: 10px 20px;"><img src="http://www.pumpic.com/wp-content/themes/pumpicapp/images/logo.png"></a>
        <div class="block" style="width: 636px;float: left;padding: 0 30px;border-radius: 20px;border: 2px solid #0090d3;">
        <h1 style="text-align:center;font-size:35px;  font-weight: bold;">New <span style="color:#0090d3;">Device</span> Added</h1>
        <p style="line-height: 20px;">Hello again!</p>
        <p style="line-height: 20px;">Your device ' . $name . ' has been added to your account on pumpic.com. Yay!</p>
        <br>
        <p style="line-height:20px;">Pumpic Team<br/>support@pumpic.com<br/>http://pumpic.com</p>
        </div>
        </div>
        </body>
        </html>';

        $this->sendMail($email, "Pumpic: New Device Added", $message);*/
        
        return $deviceRecord->getId();
    }

    public function isDeviceLimitationAllowed($devId, $limitationName)
    {
        $limitations = new Limitations($this->db);
        return $limitations->isAllowed($devId, $limitationName);
    }

    public function decrementLimitation($devId, $limitationName)
    {
        $limitations = new Limitations($this->db);
        $limitations->decrementLimitation($devId, $limitationName);
    }

    public function updateDeviceLimitations($devId, $resetCount = false)
    {
        $limitations = new Limitations($this->db);
        $limitations->updateDeviceLimitations($devId, $resetCount);
    }

    public function isUserLicenseAvailable($id, $userId)
    {
        $escapedId = $this->db->quote($id);
        $escapedUserId = $this->db->quote($userId);
        $status = $this->db->quote(LicenseRecord::STATUS_AVAILABLE);

        return $this->db->query("SELECT 
                                COUNT(*) 
                            FROM `licenses` 
                            WHERE 
                                `id` = {$escapedId} AND
                                `user_id` = {$escapedUserId} AND
                                `status` = {$status}
                            LIMIT 1")->fetchColumn() > 0;
    }

    public function hasDevicePackageLicense($devId)
    {
        $escapedDevId = $this->db->quote($devId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);
        $status = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        return $this->db->query("SELECT 
                                COUNT(*) 
                            FROM `licenses` 
                            WHERE
                                `device_id` = {$escapedDevId} AND
                                `product_type` = {$productType} AND
                                `status` = {$status}
                            LIMIT 1")->fetchColumn() > 0;
    }

    public function assignLicenseToDevice($licenseId, $deviceId)
    {
        $this->db->beginTransaction();

        try {
            $license = new LicenseRecord($this->db);
            $license->load($licenseId)
                    ->setDeviceId($deviceId)
                    ->setStatus(LicenseRecord::STATUS_ACTIVE)
                    ->save();

            if ($license->getProductType() == ProductRecord::TYPE_PACKAGE) {
                $this->updateDeviceLimitations($deviceId, true);
            } else {
                $this->updateDeviceLimitations($deviceId);
            }

            return $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getUserUnAssignedDevicesList($userId)
    {
        $escapedUserId = $this->getDb()->quote($userId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);
        $status = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        return $this->getDb()->query("SELECT
                                            d.`id`,
                                            d.`name`
                                        FROM `devices` d
                                        WHERE
                                            d.`user_id` = {$escapedUserId} AND
                                            d.`deleted` = 0 AND
                                            (SELECT 
                                                    COUNT(*) 
                                                FROM `licenses` 
                                                WHERE 
                                                    `user_id` = d.`user_id` AND
                                                    `device_id` = d.`id` AND
                                                    `status` = {$status} AND
                                                    `product_type` = {$productType} 
                                                LIMIT 1) = 0")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

}
