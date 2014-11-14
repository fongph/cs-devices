<?php

namespace CS\Devices;

use PDO,
    CS\Models\Site\SiteRecord,
    CS\Models\User\UserRecord,
    CS\Models\Device\DeviceRecord;

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

    public function getDevId($devUniqueId)
    {
        $uniqueId = $this->db->quote($devUniqueId);

        return $this->getDb()->query("SELECT `id` FROM `devices` WHERE `unique_id` = {$uniqueId} LIMIT 1")->fetchColumn();
    }

    public function getUserDeviceAddCode($userId)
    {
        $deviceCode = new DeviceCode($this->getRedis());

        return $deviceCode->createCode($userId);
    }

    public function addDeviceWithCode($deviceUniqueId, $code)
    {
        $deviceCode = new DeviceCode($this->getRedis());

        if (($userId = $deviceCode->getCodeUser($code)) === null) {
            throw new DeviceCodeNotFoundException("Code not found!");
        }

        $deviceRecord = new DeviceRecord();
        $deviceRecord->setUniqueId($deviceUniqueId)
                ->setUserId($userId)
                ->save();

        $limitations = new Limitations($this->db);
        $limitations->updateDeviceLimitations($deviceRecord->getId(), true);

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

}
