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

    /**
     *
     * @var callable
     */
    protected $deviceDbConfigGenerator;

    /**
     *
     * @var array
     */
    protected $dbOptions = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8;',
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );

    /**
     * (20 min)
     */
    const ONLINE_PERIOD = 1200;

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

    public function setDeviceDbConfigGenerator(callable $generator)
    {
        $this->deviceDbConfigGenerator = $generator;
    }

    public function setDbOptions($options)
    {
        $this->dbOptions = $options;
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

    public function getUserDeviceAddCode($userId, $licenseId = null)
    {
        $deviceCode = new DeviceCode($this->db);

        return $deviceCode->createCode($userId, $licenseId);
    }

    public function getDeviceDbConnection($devId)
    {
        if ($this->deviceDbConfigGenerator == null) {
            throw new DeviceDbConfigGeneratorNotExistException("Device db config generator not found!");
        }

        $dbConfig = call_user_func($this->deviceDbConfigGenerator, $devId);

        return new \PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}", $dbConfig['username'], $dbConfig['password'], $this->dbOptions);
    }

    public function getUserAddCodeInfo($userId, $code) {
        $deviceCode = new DeviceCode($this->db);
        
        return $deviceCode->getUserCodeInfo($userId, $code);
    }
    
    //@TODO: send emails
    public function addDeviceWithCode($deviceUniqueId, $code, $name)
    {
        $deviceCode = new DeviceCode($this->db);

        if (($info = $deviceCode->getActiveCodeInfo($code)) == false) {
            throw new DeviceCodeNotFoundException("Code not found!");
        }

        $this->db->beginTransaction();

        $deviceRecord = new DeviceRecord($this->db);
        $deviceRecord->setUniqueId($deviceUniqueId)
                ->setUserId($info['user_id'])
                ->setName($name)
                ->save();

        if ($info['license_id'] !== null) {
            $licenseRecord = new LicenseRecord($this->db);
            $licenseRecord->load($info['license_id']);

            if ($licenseRecord->getDeviceId() === null &&
                    $licenseRecord->getStatus() === LicenseRecord::STATUS_AVAILABLE) {
                
                $licenseRecord->setDeviceId($deviceRecord->getId())
                    ->setStatus(LicenseRecord::STATUS_ACTIVE)
                    ->save();
            }
        }

        $deviceCode->setCodeDevice($code, $info['user_id'], $deviceRecord->getId());
        
        $deviceDb = $this->getDeviceDbConnection($deviceRecord->getId());
        $deviceDb->beginTransaction();
        $this->createDeviceIitialSettings($deviceDb, $deviceRecord->getId());

        $deviceLimitations = new \CS\Models\Device\Limitation\DeviceLimitationRecord($this->db);
        $deviceLimitations->setDevice($deviceRecord)
                ->save();

        $limitations = new Limitations($this->db);
        $limitations->updateDeviceLimitations($deviceRecord->getId(), true);

        /* $message = '<!DOCTYPE html>
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

          $this->sendMail($email, "Pumpic: New Device Added", $message); */

        $deviceDb->commit();
        $this->db->commit();

        return $deviceRecord->getId();
    }

    private function createDeviceIitialSettings(\PDO $db, $devId)
    {
        $escapedDevId = $db->quote($devId);
        $db->exec("INSERT INTO `dev_settings` SET `dev_id` = {$escapedDevId}");
        $db->exec("INSERT INTO `dev_info` SET `dev_id` = {$escapedDevId}");
    }

    public function isDeviceLimitationAllowed($devId, $option)
    {
        $limitations = new Limitations($this->db);
        return $limitations->isAllowed($devId, $option);
    }

    public function decrementLimitation($devId, $option)
    {
        $limitations = new Limitations($this->db);
        $limitations->decrementLimitation($devId, $option);
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

    public function getUserActiveDevices($userId)
    {
        $escapedUserId = $this->getDb()->quote($userId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);
        $status = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        $minOnlineTime = time() - self::ONLINE_PERIOD;

        return $this->getDb()->query("SELECT
                    d.`id`,
                    d.`name`,
                    d.`os`,
                    d.`os_version`,
                    d.`app_version`,
                    d.`network`,
                    d.`model`,
                    IF(d.`last_visit` > {$minOnlineTime}, 1, 0) online,
                    d.`rooted`,
                    p.`name` package_name
                FROM `devices` d
                LEFT JOIN `licenses` l ON 
                    l.`device_id` = d.`id` AND
                    l.`product_type` = {$productType} AND
                    l.`status` = {$status}
                LEFT JOIN `products` p ON p.`id` = l.`product_id`
                WHERE
                    d.`user_id` = {$escapedUserId} AND
                    d.`deleted` = 0")->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
    }

    public function deleteDevice($deviceId)
    {
        $this->getDevice($deviceId)
                ->setDeleted()
                ->save();

        $status = $this->db->quote(LicenseRecord::STATUS_INACTIVE);
        $escapedDeviceId = $this->getDb()->quote($deviceId);

        $this->getDb()->exec("UPDATE `licenses` SET `status` = {$status}, `device_id` = NULL WHERE `device_id` = {$escapedDeviceId}");

        $this->updateDeviceLimitations($deviceId, true);
    }

}
