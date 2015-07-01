<?php

namespace CS\Devices;

use PDO,
    CS\Models\Site\SiteRecord,
    CS\Models\User\UserRecord,
    CS\Models\Device\DeviceRecord,
    CS\Models\Device\DeviceICloudRecord,
    CS\Models\Device\Limitation\DeviceLimitationRecord,
    CS\Models\Subscription\Task\SubscriptionTaskRecord,
    CS\Models\License\LicenseRecord,
    CS\Models\Product\ProductRecord,
    CS\Mail\MailSender,
    CS\Users\UsersNotes;

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
     * @var MailSender 
     */
    protected $sender;

    /**
     *
     * @var UsersNotes
     */
    protected $usersNotes;

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

    /** 24 hours */
    const SYNC_PERIOD = 86400;

    /** @var DeviceRecord */
    protected $device;

    /** @var DeviceICloudRecord */
    protected $iCloudDevice;

    /** @var LicenseRecord */
    protected $license;
    protected $afterSaveCallback;
    protected $userId, $licenseId, $deviceUniqueId, $appleId, $applePassword, $deviceHash, $name, $model, $osVer, $lastBackup, $quotaUsed;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function setUserId($userID)
    {
        $this->userId = $userID;
        return $this;
    }

    public function setDeviceUniqueId($deviceUniqueId)
    {
        $this->deviceUniqueId = $deviceUniqueId;
        return $this;
    }

    public function setAppleId($appleId)
    {
        $this->appleId = $appleId;
        return $this;
    }

    public function setApplePassword($applePassword)
    {
        $this->applePassword = $applePassword;
        return $this;
    }

    public function setDeviceHash($hash)
    {
        $this->deviceHash = $hash;
        return $this;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    public function setOsVer($osVer)
    {
        $this->osVer = $osVer;
        return $this;
    }

    public function setLastBackup($lastBackup)
    {
        $this->lastBackup = $lastBackup;
        return $this;
    }

    public function setQuotaUsed($quotaUsed)
    {
        $this->quotaUsed = $quotaUsed;
        return $this;
    }

    public function setAfterSave(callable $callback)
    {
        $this->afterSaveCallback = $callback;
        return $this;
    }

    protected function afterSave()
    {
        if (is_callable($this->afterSaveCallback))
            call_user_func_array($this->afterSaveCallback, func_get_args());
    }

    public function getProcessedDevice()
    {
        return $this->device;
    }

    public function getICloudDevice()
    {
        return $this->iCloudDevice;
    }

    public function setLicense(LicenseRecord $licenseRecord)
    {
        $this->license = $licenseRecord;
        $this->licenseId = $licenseRecord->getId();
        return $this;
    }

    public function setLicenseId($licenseId)
    {
        if ($licenseId != $this->licenseId) {
            $this->licenseId = $licenseId;
            $this->license = null;
        }
        return $this;
    }

    public function getLicense()
    {
        if (!$this->license && $this->licenseId) {
            $licenseRecord = new LicenseRecord($this->db);
            $this->license = $licenseRecord->load($this->licenseId);
        }
        return $this->license;
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

    public function setSender(MailSender $sender)
    {
        $this->sender = $sender;

        return $this;
    }

    public function setUsersNotesProcessor(UsersNotes $usersNotes)
    {
        $this->usersNotes = $usersNotes;

        return $this;
    }

    /**
     * 
     * @return UsersNotes
     * @throws Exception
     */
    private function getUsersNotesProcessor()
    {
        if ($this->usersNotes instanceof UsersNotes) {
            return $this->usersNotes;
        }

        throw new Exception("UsersNotes required");
    }

    /**
     * 
     * @return MailSender
     * @throws InvalidSenderObjectException
     */
    private function getSender()
    {
        if (!($this->sender instanceof MailSender)) {
            throw new InvalidSenderObjectException("Invalid mail sender object!");
        }

        return $this->sender;
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

        return $this->getDb()->query("SELECT COUNT(*) FROM `devices` WHERE `unique_id` = {$uniqueId} AND `deleted` = 0 LIMIT 1")->fetchColumn() > 0;
    }

    public function getDeviceId($devUniqueId)
    {
        $uniqueId = $this->db->quote($devUniqueId);

        return $this->getDb()->query("SELECT `id` FROM `devices` WHERE `unique_id` = {$uniqueId} AND `deleted` = 0 LIMIT 1")->fetchColumn();
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

    public function getUserAddCodeInfo($userId, $code)
    {
        $deviceCode = new DeviceCode($this->db);

        return $deviceCode->getUserCodeInfo($userId, $code);
    }

    public function addICloudDevice()
    {
        $this->db->beginTransaction();

        $this->device = new DeviceRecord($this->db);
        $this->device
                ->setUserId($this->userId)
                ->setUniqueId($this->deviceUniqueId)
                ->setName($this->name)
                ->setModel($this->model)
                ->setOSVersion($this->osVer)
                ->save();

        $this->iCloudDevice = new DeviceICloudRecord($this->db);
        $this->iCloudDevice
                ->setDevId($this->device->getId())
                ->setAppleId($this->appleId)
                ->setApplePassword($this->applePassword)
                ->setDeviceHash($this->deviceHash)
                ->setLastBackup($this->lastBackup)
                ->setQuotaUsed($this->quotaUsed)
                ->save();

        if (!$this->license) {
            $this->license = new LicenseRecord($this->db);
            $this->license->load($this->licenseId);
        }
        $this->license
                ->setDeviceId($this->device->getId())
                ->setStatus(LicenseRecord::STATUS_ACTIVE)
                ->save();

        $deviceDb = $this->getDeviceDbConnection($this->device->getId());
        $deviceDb->beginTransaction();
        $this->createDeviceIitialSettings($deviceDb, $this->device->getId());

        (new DeviceLimitationRecord($this->db))
                ->setDevice($this->device)
                ->save();
        (new Limitations($this->db))
                ->updateDeviceLimitations($this->device->getId(), true);

        $deviceDb->commit();
        $this->db->commit();

        $this->afterSave();

        return $this->device->getId();
    }

    public function addDeviceWithCode($deviceUniqueId, $code, $name)
    {
        $deviceCode = new DeviceCode($this->db);

        $usersNotesProcessor = $this->getUsersNotesProcessor();

        if (($info = $deviceCode->getActiveCodeInfo($code)) == false) {
            throw new DeviceCodeNotFoundException("Code not found!");
        }

        $this->db->beginTransaction();

        $deviceRecord = new DeviceRecord($this->db);
        $deviceRecord->setUniqueId($deviceUniqueId)
                ->setUserId($info['user_id'])
                ->setName($name)
                ->save();

        $eventManager = \EventManager::getInstance();
        $eventManager->emit('device-added', array(
            'userId' => $deviceRecord->getUserId(),
            'deviceId' => $deviceRecord->getId()
        ));
        
        $usersNotesProcessor->deviceAdded($deviceRecord->getId(), $deviceRecord->getUserId());

        if ($info['license_id'] !== null) {
            $licenseRecord = new LicenseRecord($this->db);
            $licenseRecord->load($info['license_id']);

            if ($licenseRecord->getDeviceId() === null &&
                    $licenseRecord->getStatus() === LicenseRecord::STATUS_AVAILABLE) {

                $licenseRecord->setDeviceId($deviceRecord->getId())
                        ->setStatus(LicenseRecord::STATUS_ACTIVE)
                        ->save();

                $this->licenseOnAssign($licenseRecord);

                $usersNotesProcessor->licenseAssigned($licenseRecord->getId(), $deviceRecord->getId(), $deviceRecord->getUserId());
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

        $deviceDb->commit();
        $this->db->commit();

        return $deviceRecord;
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

    public function updateDeviceLimitations($devId, $packageUpdated = false, $optionsUpdated = false)
    {
        $limitations = new Limitations($this->db);
        $limitations->updateDeviceLimitations($devId, $packageUpdated, $optionsUpdated);
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

    public function hasDevicePackageLicense($deviceId)
    {
        $escapedDeviceId = $this->db->quote($deviceId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);

        return $this->db->query("SELECT 
                                COUNT(*) 
                            FROM `licenses` 
                            WHERE
                                `device_id` = {$escapedDeviceId} AND
                                `product_type` = {$productType}
                            LIMIT 1")->fetchColumn() > 0;
    }

    public function removeDeviceLicenses($deviceId)
    {
        $status = $this->db->quote(LicenseRecord::STATUS_INACTIVE);
        $escapedDeviceId = $this->getDb()->quote($deviceId);

        return $this->getDb()->exec("UPDATE `licenses` SET `status` = {$status}, `device_id` = NULL WHERE `device_id` = {$escapedDeviceId}");
    }

    /**
     * Add subscription data to specific list that will be proccessed with autorebill canceling
     * 
     * @param type $paymentMethod
     * @param type $referenceNumber
     * @param type $userId license owner
     * @param type $adminId
     */
    private function addSubscriptionAutoRebillStopTask($paymentMethod, $referenceNumber)
    {
        $subscriptionTask = new SubscriptionTaskRecord($this->db);
        $subscriptionTask
                ->setPaymentMethod($paymentMethod)
                ->setReferenceNumber($referenceNumber)
                ->setTask($subscriptionTask::TASK_AUTO_REBILL_STOP)
                ->save();
    }

    public function closeLicense($licenseId, $updateDeviceLimitations = true, $actorAdminId = null)
    {
        $licenseRecord = new LicenseRecord($this->db);
        $licenseRecord->load($licenseId);

        $deviceId = $licenseRecord->getDeviceId();

        // close all device licenses if license is main
        if ($deviceId && $licenseRecord->getProductType() === ProductRecord::TYPE_PACKAGE) {
            $this->closeDeviceLicenses($licenseRecord->getDeviceId(), $updateDeviceLimitations);
            return;
        }

        $escapedLicenseId = $this->db->quote($licenseId);
        $subscription = $this->db->query("SELECT `payment_method`, `reference_number` FROM `subscriptions` WHERE `license_id` = {$escapedLicenseId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if ($subscription !== false) {
            $this->getUsersNotesProcessor()->licenseSubscriptionAutoRebillTaskAdded($licenseRecord->getId(), $licenseRecord->getUserId(), $actorAdminId);
            $this->addSubscriptionAutoRebillStopTask($subscription['payment_method'], $subscription['reference_number']);
        }

        $licenseRecord->setStatus(LicenseRecord::STATUS_INACTIVE);

        if ($deviceId) {
            $licenseRecord->setDeviceId(null)->save();

            if ($updateDeviceLimitations) {
                $this->updateDeviceLimitations($deviceId, true, true);
            }
            return;
        }

        $licenseRecord->save();
    }

    public function closeDeviceLicenses($deviceId, $updateDeviceLimitations = true, $actorAdminId = null)
    {
        $escapedDeviceId = $this->db->quote($deviceId);
        $activeStatus = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        $deviceSubscriptions = $this->db->query("SELECT
                l.`id`,
                l.`user_id`,
                s.`payment_method`,
                s.`reference_number`
            FROM `licenses` l
            INNER JOIN `subscriptions` s ON s.`license_id` = l.`id`
            WHERE
                l.`device_id` = {$escapedDeviceId} AND
                l.`status` = {$activeStatus}")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($deviceSubscriptions as $subscription) {
            $this->getUsersNotesProcessor()->licenseSubscriptionAutoRebillTaskAdded($subscription['id'], $subscription['user_id'], $actorAdminId);
            $this->addSubscriptionAutoRebillStopTask($subscription['payment_method'], $subscription['reference_number']);
        }

        $inactiveStatus = $this->db->quote(LicenseRecord::STATUS_INACTIVE);
        $this->getDb()->exec("UPDATE `licenses` SET `status` = {$inactiveStatus}, `device_id` = NULL WHERE `device_id` = {$escapedDeviceId}");

        if ($updateDeviceLimitations) {
            $this->updateDeviceLimitations($deviceId, true, true);
        }
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
            } elseif ($license->getProductType() == ProductRecord::TYPE_OPTION) {
                $this->updateDeviceLimitations($deviceId, false, true);
            }

            return $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @deprecated 
     * @param type $userId
     * @return type
     */
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

    public function getDevicesToAssign($userId)
    {
        $escapedUserId = $this->getDb()->quote($userId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);

        return $this->getDb()->query("SELECT
                                            d.`id`,
                                            d.`name`,
                                            p.`name` product
                                        FROM `devices` d
                                        LEFT JOIN `licenses` l ON l.`device_id` = d.`id` AND l.`product_type` = {$productType}
                                        LEFT JOIN `products` p ON p.`id` = l.`product_id`
                                        WHERE
                                            d.`user_id` = {$escapedUserId} AND
                                            d.`deleted` = 0")->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
    }

    public function getUserActiveDevices($userId, $showDeleted = false)
    {
        $escapedUserId = $this->getDb()->quote($userId);
        $productType = $this->db->quote(ProductRecord::TYPE_PACKAGE);
        $status = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        $minOnlineTime = time() - self::ONLINE_PERIOD;
        $minSyncTime = time() - self::SYNC_PERIOD;

        $syncErrorNone = $this->getDb()->quote(DeviceICloudRecord::ERROR_NONE);
        $syncErrorParse = $this->getDb()->quote(DeviceICloudRecord::ERROR_PARSE);

        $deleted = 'd.`deleted` = 0';

        if ($showDeleted) {
            $deleted = '1';
        }

        return $this->getDb()->query("SELECT
                    d.`id`,
                    d.`unique_id`,
                    d.`name`,
                    d.`os`,
                    d.`os_version`,
                    d.`app_version`,
                    d.`network`,
                    d.`model`,
                    d.`deleted`,
                    IF(d.`last_visit` > {$minOnlineTime}, 1, 0) online,
                    IF(di.`last_sync` > {$minSyncTime} AND (di.`last_error` = {$syncErrorNone} OR di.`last_error` = {$syncErrorParse}), 1, 0) sync,
                    d.`rooted`,
                    d.`root_access` as rootAccess,
                    if(COUNT(l.`id`), 1, 0) as `active`,
                    p.`name` package_name,
                    di.`last_error`,
                    di.`processing`
                FROM `devices` d
                LEFT JOIN `devices_icloud` di ON
                    d.`os` = {$this->getDb()->quote(DeviceRecord::OS_ICLOUD)} AND
                    d.`id` = di.`dev_id`
                LEFT JOIN `licenses` l ON 
                    l.`device_id` = d.`id` AND
                    l.`product_type` = {$productType} AND
                    l.`status` = {$status}
                LEFT JOIN `products` p ON p.`id` = l.`product_id`
                WHERE
                    d.`user_id` = {$escapedUserId} AND
                    {$deleted}
                GROUP BY d.`id`")->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
    }

    public function iCloudMergeWithLocalInfo($userId, array $iCloudDevices)
    {
        if (empty($iCloudDevices))
            return $iCloudDevices;

        $localDevices = array();
        foreach ($this->getUserActiveDevices($userId) as $dbDevice) {
            $localDevices[$dbDevice['unique_id']] = array(
                'active' => $dbDevice['active']
            );
        }
        foreach ($iCloudDevices as &$iCloudDev) {
            if (array_key_exists($iCloudDev['SerialNumber'], $localDevices)) {

                $iCloudDev['added'] = true;
                $iCloudDev['active'] = $localDevices[$iCloudDev['SerialNumber']]['active'];
            } else
                $iCloudDev['added'] = $iCloudDev['active'] = false;
        }
        return $iCloudDevices;
    }

    /**
     * License Event to call after assign any license...
     * 
     * @param LicenseRecord $license
     */
    public function licenseOnAssign(LicenseRecord $license)
    {
        /**
         * enable promo licenses
         */
        $usersManager = new \CS\Users\UsersManager($this->db);
        $option = 'license-' . $license->getId() . ':on-assign:enable-promo';
        $value = $usersManager->getUserOption($license->getUserId(), $option);

        if ($value !== false) {
            $licensesToEnable = explode(',', $value);

            foreach ($licensesToEnable as $licenseId) {
                $licenseRecord = new LicenseRecord($this->db);
                $licenseRecord->load($licenseId)
                        ->setStatus(LicenseRecord::STATUS_AVAILABLE)
                        ->save();
            }

            $usersManager->removeUserOption($license->getUserId(), $option);
        }
    }

    public function deleteDevice($deviceId, $actorAdminId = null)
    {
        $this->getDevice($deviceId)
                ->setDeleted()
                ->save();

        $this->closeDeviceLicenses($deviceId, true, $actorAdminId);

        $this->getUsersNotesProcessor()->deviceDeleted($deviceId);
    }

}
