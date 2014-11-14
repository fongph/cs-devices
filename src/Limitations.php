<?php

namespace CS\Devices;

use PDO,
    CS\Models\Site\SiteRecord,
    CS\Models\User\UserRecord,
    CS\Models\Device\DeviceRecord;

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
        if (!in_array($limitationName, array(self::SMS, self::CALL))) {
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
    
    private function getDeviceLimitationsList($devId)
    {
        $this->db->query("SELECT * FROM ``");
    }

}
