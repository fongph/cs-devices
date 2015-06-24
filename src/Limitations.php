<?php

namespace CS\Devices;

use PDO,
    CS\Models\Product\ProductRecord,
    CS\Models\License\LicenseRecord,
    CS\Models\Limitation,
    CS\Models\Device\Limitation\DeviceLimitationRecord;

/**
 * Description of Limitations
 *
 * @author root
 */
class Limitations
{

    const SMS = 'sms';
    const CALL = 'call';
    const GPS = 'gps';
    const BLOCK_NUMBER = 'blockNumber';
    const BLOCK_WORDS = 'blockWords';
    const BROWSER_HISTORY = 'browserHistory';
    const BROWSER_BOOKMARK = 'browserBookmark';
    const CONTACT = 'contact';
    const CALENDAR = 'calendar';
    const PHOTOS = 'photos';
    const VIBER = 'viber';
    const WHATSAPP = 'whatsapp';
    const VIDEO = 'video';
    const SKYPE = 'skype';
    const FACEBOOK = 'facebook';
    const VK = 'vk';
    const EMAILS = 'emails';
    const APPLICATIONS = 'applications';
    const KEYLOGGER = 'keylogger';
    const SMS_COMMANDS = 'smsCommands';
    const INSTAGRAM = 'instagram';
    const KIK = 'kik';
    const NOTES = 'notes';
    const SNAPCHAT = 'snapchat';
    const SMS_DAILY_LIMIT = 'smsDailyLimit';
    const APPLICATIONS_BLOCKING = 'applicationsBlocking';
    const APPLICATIONS_LIMITING = 'applicationsLimitiong';
    const WEBSITES_BLOCKING = 'websitesBlocking';
    const SIM_CHANGE_ALERT = 'simChangeAlert';
    const REBOOT_DEVICE = 'rebootDevice';
    const REBOOT_APPLICATION = 'rebootApplication';
    const LOCK_DEVICE = 'lockDevice';
    const ICLOUD = 'icloud';
    const UNLIMITED_VALUE = 65535;

    private static $allowedLimitations = array(
        self::SMS,
        self::CALL,
        self::GPS,
        self::BLOCK_NUMBER,
        self::BLOCK_WORDS,
        self::BROWSER_HISTORY,
        self::BROWSER_BOOKMARK,
        self::CONTACT,
        self::CALENDAR,
        self::PHOTOS,
        self::VIBER,
        self::WHATSAPP,
        self::VIDEO,
        self::SKYPE,
        self::FACEBOOK,
        self::VK,
        self::EMAILS,
        self::APPLICATIONS,
        self::KEYLOGGER,
        self::SMS_COMMANDS,
        self::INSTAGRAM,
        self::KIK,
        self::NOTES,
        self::SNAPCHAT,
        self::SMS_DAILY_LIMIT,
        self::APPLICATIONS_BLOCKING,
        self::APPLICATIONS_LIMITING,
        self::WEBSITES_BLOCKING,
        self::SIM_CHANGE_ALERT,
        self::REBOOT_DEVICE,
        self::REBOOT_APPLICATION,
        self::LOCK_DEVICE,
        self::ICLOUD
    );
    private static $masks = array(
        self::SMS => Limitation::SMS,
        self::CALL => Limitation::CALL,
        self::GPS => Limitation::GPS,
        self::BLOCK_NUMBER => Limitation::BLOCK_NUMBER,
        self::BLOCK_WORDS => Limitation::BLOCK_WORDS,
        self::BROWSER_HISTORY => Limitation::BROWSER_HISTORY,
        self::BROWSER_BOOKMARK => Limitation::BROWSER_BOOKMARK,
        self::CONTACT => Limitation::CONTACT,
        self::CALENDAR => Limitation::CALENDAR,
        self::PHOTOS => Limitation::PHOTOS,
        self::VIBER => Limitation::VIBER,
        self::WHATSAPP => Limitation::WHATSAPP,
        self::VIDEO => Limitation::VIDEO,
        self::SKYPE => Limitation::SKYPE,
        self::FACEBOOK => Limitation::FACEBOOK,
        self::VK => Limitation::VK,
        self::EMAILS => Limitation::EMAILS,
        self::APPLICATIONS => Limitation::APPLICATIONS,
        self::KEYLOGGER => Limitation::KEYLOGGER,
        self::SMS_COMMANDS => Limitation::SMS_COMMANDS,
        self::INSTAGRAM => Limitation::INSTAGRAM,
        self::KIK => Limitation::KIK,
        self::NOTES => Limitation::NOTES,
        self::SNAPCHAT => Limitation::SNAPCHAT,
        self::SMS_DAILY_LIMIT => Limitation::SMS_DAILY_LIMIT,
        self::APPLICATIONS_BLOCKING => Limitation::APPLICATIONS_BLOCKING,
        self::APPLICATIONS_LIMITING => Limitation::APPLICATIONS_LIMITING,
        self::WEBSITES_BLOCKING => Limitation::WEBSITES_BLOCKING,
        self::SIM_CHANGE_ALERT => Limitation::SIM_CHANGE_ALERT,
        self::REBOOT_DEVICE => Limitation::REBOOT_DEVICE,
        self::REBOOT_APPLICATION => Limitation::REBOOT_APPLICATION,
        self::LOCK_DEVICE => Limitation::LOCK_DEVICE,
        self::ICLOUD => Limitation::ICLOUD
    );

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

    public function isAllowed($devId, $limitationName)
    {
        if (!in_array($limitationName, self::$allowedLimitations)) {
            throw new InvalidLimitationNameException("Bad limitation name!");
        }

        $devIdValue = $this->db->quote($devId);
        if ($limitationName == self::SMS || $limitationName == self::CALL) {
            return $this->db->query("SELECT `{$limitationName}` FROM `devices_limitations` WHERE `device_id` = {$devIdValue} LIMIT 1")->fetchColumn() > 0;
        }

        $value = $this->db->query("SELECT `value` FROM `devices_limitations` WHERE `device_id` = {$devIdValue} LIMIT 1")->fetchColumn();

        return Limitation::hasValueOption($value, self::$masks[$limitationName]);
    }

    public function decrementLimitation($devId, $limitationName)
    {
        if (!($limitationName == self::SMS) && !($limitationName == self::CALL)) {
            throw new InvalidLimitationNameException("Bad limitation name or limitation not support decrementation!");
        }

        $devIdValue = $this->db->quote($devId);
        $maxValue = self::UNLIMITED_VALUE;
        return $this->db->exec("UPDATE 
                                    `devices_limitations`
                                SET 
                                    `{$limitationName}` = `{$limitationName}` - 1
                                WHERE 
                                    `device_id` = {$devIdValue} AND
                                    `{$limitationName}` > 0 AND
                                    `{$limitationName}` < {$maxValue}
                                LIMIT 1");
    }

    private function getDeviceLicenseLimitationsList($devId, $productType = ProductRecord::TYPE_PACKAGE)
    {
        $devIdValue = $this->db->quote($devId);
        $status = $this->db->quote(LicenseRecord::STATUS_ACTIVE);

        return $this->db->query("SELECT
                                l.`sms`,
                                l.`call`,
                                l.`value`
                            FROM `licenses` lic
                            INNER JOIN `products` p ON lic.`product_id` = p.`id`
                            INNER JOIN `limitations` l ON p.`limitation_id` = l.`id`
                            WHERE 
                                lic.`device_id` = {$devIdValue} AND
                                lic.`status` = {$status} AND
                                lic.`product_type` = '{$productType}'")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setDeviceLimitation($devId, Limitation $limitation)
    {
        $deviceLimitation = new DeviceLimitationRecord($this->db);
        $deviceLimitation->loadByDeviceId($devId);

        $deviceLimitation->setCall($limitation->getCall())
                ->setSms($limitation->getSms())
                ->setValue($limitation->getValue())
                ->save();
    }

    public function getDeviceLimitation($devId)
    {
        $devIdValue = $this->db->quote($devId);

        $result = $this->db->query("SELECT
                                `sms`,
                                `call`,
                                `value`
                            FROM 
                                `devices_limitations`
                            WHERE 
                                `device_id` = {$devIdValue} 
                            LIMIT 1")->fetch();

        $limitation = new Limitation();

        if ($result === false) {
            return $limitation;
        }

        return $limitation->setCall($result['call'])
                        ->setSms($result['sms'])
                        ->setValue($result['value']);
    }

    public function updateDeviceLimitations($devId, $packageUpdated = false, $optionsUpdated = false)
    {
        $mainPackages = $this->getDeviceLicenseLimitationsList($devId);

        if (count($mainPackages) > 1) {
            throw new InvalidLimitationsCountException("Device can have only one main package!");
        }

        $deviceLimitation = new DeviceLimitationRecord($this->db);
        $deviceLimitation->loadByDeviceId($devId);

        if (count($mainPackages) == 0) {
            $this->clearLimitation($deviceLimitation)->save();
            return;
        }

        $package = $mainPackages[0];
        $options = $this->getDeviceOptionsResult($devId);

        if ($packageUpdated) {
            if ($deviceLimitation->getSavedSms() === null) {
                $deviceLimitation->setSms($package['sms']);
            } else {
                $deviceLimitation->setSavedSms($package['sms']);
            }

            if ($deviceLimitation->getSavedCall() === null) {
                $deviceLimitation->setCall($package['call']);
            } else {
                $deviceLimitation->setSavedCall($package['call']);
            }
        }

        if ($optionsUpdated) {
            if ($options->hasOption(Limitation::SMS) && $deviceLimitation->getSavedSms() === null) {
                $deviceLimitation->setSavedSms($deviceLimitation->getSms());
            } elseif (!$options->hasOption(Limitation::SMS) && $deviceLimitation->getSavedSms() !== null) {
                $deviceLimitation->setSms($deviceLimitation->getSavedSms());
                $deviceLimitation->setSavedSms(null);
            }

            if ($options->hasOption(Limitation::CALL) && $deviceLimitation->getSavedCall() === null) {
                $deviceLimitation->setSavedCall($deviceLimitation->getCall());
            } elseif (!$options->hasOption(Limitation::CALL) && $deviceLimitation->getSavedCall() !== null) {
                $deviceLimitation->setCall($deviceLimitation->getSavedCall());
                $deviceLimitation->setSavedCall(null);
            }
        }

        if ($options->getSms() == Limitations::UNLIMITED_VALUE) {
            $deviceLimitation->setSms(Limitations::UNLIMITED_VALUE);
        }

        if ($options->getCall() == Limitations::UNLIMITED_VALUE) {
            $deviceLimitation->setCall(Limitations::UNLIMITED_VALUE);
        }

        $deviceLimitation->setValue($package['value'] | $options->getValue())->save();
    }

    private function getDeviceOptionsResult($devId)
    {
        $result = new Limitation();

        $options = $this->getDeviceLicenseLimitationsList($devId, ProductRecord::TYPE_OPTION);

        foreach ($options as $optionLimitations) {
            $option = new Limitation($optionLimitations['sms'], $optionLimitations['call'], $optionLimitations['value']);

            $result->merge($option, true);
        }

        return $result;
    }

    private function mergeLimitations(DeviceLimitationRecord $deviceLimitation, $limitations, $resetCount = false)
    {
        $device = new Limitation(
                $deviceLimitation->getSms(), $deviceLimitation->getCall(), $deviceLimitation->getValue()
        );

        $package = new Limitation(
                $limitations['sms'], $limitations['call'], $limitations['value']
        );

        $device->merge($package, $resetCount);

        return $deviceLimitation->setCall($device->getCall())
                        ->setSms($device->getSms())
                        ->setValue($device->getValue());
    }

    public static function getOptionValue($name)
    {
        return self::$masks[$name];
    }

    private function clearLimitation(DeviceLimitationRecord $deviceLimitation)
    {
        return $deviceLimitation->setSms(0)
                        ->setCall(0)
                        ->setValue(0)
                        ->setSavedSms(null)
                        ->setSavedCall(null);
    }

}
