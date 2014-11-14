<?php

namespace CS\Devices;

use PDO,
    CS\Models\Product\ProductRecord,
    CS\Models\Device\Limitation\DeviceLimitationRecord,
    CS\Models\Device\Limitation\DeviceLimitationNotFoundException;

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
    const BLOCK_NUMBER = 'block_number';
    const BLOCK_WORDS = 'block_words';
    const BROWSER_HISTORY = 'browser_history';
    const BROWSER_BOOKMARK = 'browser_bookmark';
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
    const UNLIMITED_VALUE = 65535;

    private $allowedLimitations = array(
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
        self::KEYLOGGER
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
        if (!in_array($limitationName, $this->allowedLimitations)) {
            throw new InvalidLimitationNameException("Bad limitation name!");
        }

        $devIdValue = $this->db->quote($devId);
        return $this->db->query("SELECT `{$limitationName}` FROM `devices_limitations` WHERE `device_id` = {$devIdValue} LIMIT 1")->fetchColumn() > 0;
    }

    private function getLimitationValue($devId, $limitationName)
    {
        $devIdValue = $this->db->quote($devId);
        return $this->db->query("SELECT `{$limitationName}` FROM `devices_limitations` WHERE `device_id` = {$devIdValue} LIMIT 1")->fetchColumn();
    }

    public function decrementLimitation($devId, $limitationName)
    {
        if ($limitationName != self::SMS || $limitationName != self::CALL) {
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
        $this->db->query("SELECT
                                l.`sms`,
                                l.`call`,
                                l.`gps`,
                                l.`block_number`,
                                l.`block_words`,
                                l.`browser_history`,
                                l.`browser_bookmark`,
                                l.`contact`,
                                l.`calendar`,
                                l.`photos`,
                                l.`viber`,
                                l.`whatsapp`,
                                l.`video`,
                                l.`skype`,
                                l.`facebook`,
                                l.`vk`,
                                l.`emails`,
                                l.`applications`,
                                l.`keylogger`
                            FROM `licenses` lic
                            INNER JOIN `products` p ON lic.`product_id` = p.`id`
                            INNER JOIN `limitations` l ON p.`limitation_id` = l.`id`
                            WHERE 
                                lic.`device_id` = {$devIdValue} AND
                                lic.`status` = 'active'
                                lic.`product_type` = '{$productType}'")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDeviceLimitations($devId, $resetCount = false)
    {
        $mainPackages = $this->getDeviceLicenseLimitationsList($devId);

        if (count($mainPackages) > 1) {
            throw new InvalidLimitationsCountException("Device can have only one main package!");
        }

        $deviceLimitation = new DeviceLimitationRecord($this->db);

        try {
            $deviceLimitation->loadByDeviceId($devId);
        } catch (DeviceLimitationNotFoundException $e) {
            // device limitations not found
        }

        if (count($mainPackages) == 0) {
            $this->clearLimitation($deviceLimitation)->save();
            return;
        }

        $deviceLimitation = $this->mergeLimitations($deviceLimitation, $mainPackages[0], $resetCount);

        $options = $this->getDeviceLicenseLimitationsList($devId, ProductRecord::TYPE_OPTION);

        foreach ($options as $optionLimitations) {
            $deviceLimitation = $this->mergeLimitations($deviceLimitation, $optionLimitations, true);
        }

        $deviceLimitation->save();
    }

    private function mergeLimitations(DeviceLimitationRecord $deviceLimitation, $limitations, $resetCount = false)
    {
        foreach ($limitations as $name => $value) {
            switch ($name) {
                case self::SMS:
                    if ($resetCount) {
                        $deviceLimitation->setSms(max($deviceLimitation->getSms(), $value));
                    }
                    break;
                case self::CALL:
                    if ($resetCount) {
                        $deviceLimitation->setCall(max($deviceLimitation->getCall(), $value));
                    }
                    break;
                case self::GPS:
                    $deviceLimitation->setGps(max($deviceLimitation->getGps(), $value));
                    break;
                case self::BLOCK_NUMBER:
                    $deviceLimitation->setBlockNumber(max($deviceLimitation->getBlockNumber(), $value));
                    break;
                case self::BLOCK_WORDS:
                    $deviceLimitation->setBlockWords(max($deviceLimitation->getBlockWords(), $value));
                    break;
                case self::BROWSER_HISTORY:
                    $deviceLimitation->setBrowserHistory(max($deviceLimitation->getBrowserHistory(), $value));
                    break;
                case self::BROWSER_BOOKMARK:
                    $deviceLimitation->setBrowserBookmark(max($deviceLimitation->getBrowserBookmark(), $value));
                    break;
                case self::CONTACT:
                    $deviceLimitation->setContact(max($deviceLimitation->getContact(), $value));
                    break;
                case self::CALENDAR:
                    $deviceLimitation->setCalendar(max($deviceLimitation->getCalendar(), $value));
                    break;
                case self::PHOTOS:
                    $deviceLimitation->setPhotos(max($deviceLimitation->getPhotos(), $value));
                    break;
                case self::VIBER:
                    $deviceLimitation->setViber(max($deviceLimitation->getViber(), $value));
                    break;
                case self::WHATSAPP:
                    $deviceLimitation->setWhatsapp(max($deviceLimitation->getWhatsapp(), $value));
                    break;
                case self::VIDEO:
                    $deviceLimitation->setVideo(max($deviceLimitation->getVideo(), $value));
                    break;
                case self::SKYPE:
                    $deviceLimitation->setSkype(max($deviceLimitation->getSkype(), $value));
                    break;
                case self::FACEBOOK:
                    $deviceLimitation->setFacebook(max($deviceLimitation->getFacebook(), $value));
                    break;
                case self::VK:
                    $deviceLimitation->setVk(max($deviceLimitation->getVk(), $value));
                    break;
                case self::EMAILS:
                    $deviceLimitation->setEmails(max($deviceLimitation->getEmails(), $value));
                    break;
                case self::APPLICATIONS:
                    $deviceLimitation->setApplications(max($deviceLimitation->getApplications(), $value));
                    break;
                case self::KEYLOGGER:
                    $deviceLimitation->setVk(max($deviceLimitation->getVk(), $value));
                    break;
            }
        }

        return $deviceLimitation;
    }

    private function clearLimitation(DeviceLimitationRecord $deviceLimitation)
    {
        $limitations = array(
            self::SMS => 0,
            self::CALL => 0,
            self::GPS => 0,
            self::BLOCK_NUMBER => 0,
            self::BLOCK_WORDS => 0,
            self::BROWSER_HISTORY => 0,
            self::BROWSER_BOOKMARK => 0,
            self::CONTACT => 0,
            self::CALENDAR => 0,
            self::PHOTOS => 0,
            self::VIBER => 0,
            self::WHATSAPP => 0,
            self::VIDEO => 0,
            self::SKYPE => 0,
            self::FACEBOOK => 0,
            self::VK => 0,
            self::EMAILS => 0,
            self::APPLICATIONS => 0,
            self::KEYLOGGER => 0
        );

        return $this->mergeLimitations($deviceLimitation, $limitations);
    }

}
