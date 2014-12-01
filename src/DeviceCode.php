<?php

namespace CS\Devices;

/**
 * Description of DeviceCode
 *
 * @author root
 */
class DeviceCode
{

    /**
     *
     * @var \Predis\Client
     */
    private $redis;

    /**
     *
     * @var \PDO
     */
    private $db;

    const CODE_PREFIX = 'c';
    const USER_PREFIX = 'u';
    const CODE_LICENSE_PREFIX = 'l';
    const GENERATION_LIMIT = 100;
    const ACTIVETIME = 900; // 15 min

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function getUserCode($userId, $licenseId = null)
    {
        $timeFrom = time() - self::ACTIVETIME;
        $user = $this->db->quote($userId);
        $license = $this->db->quote($licenseId);

        return $this->db->query("SELECT `value` FROM `codes` WHERE `user_id` = {$user} AND `license_id` = {$license} AND `time` > {$timeFrom} LIMIT 1")->fetchColumn();
    }

    private function getAllActiveCodes()
    {
        $timeFrom = time() - self::ACTIVETIME;

        return $this->db->query("SELECT * FROM `codes` WHERE `time` > {$timeFrom}")->fetchALL(\PDO::FETCH_COLUMN);
    }

    private function generateUniqueCode()
    {
        $codes = $this->getAllActiveCodes();

        for ($i = 1; $i <= self::GENERATION_LIMIT; $i++) {
            $code = $this->getNewCode();
            if (!in_array($code, $codes)) {
                return $code;
            } else if ($i == self::GENERATION_LIMIT) {
                throw new DeviceCodeGenerationException("Device code generation limit reached!");
            }
        }
    }

    public function createCode($userId, $licenseId = null)
    {
        if (($code = $this->getUserCode($userId, $licenseId)) !== null) {
            return $code;
        }

        $code = $this->generateUniqueCode();

        $time = time();
        $user = $this->db->quote($userId);
        $license = $this->db->quote($licenseId);

        $this->db->exec("INSERT INTO `codes` SET
                            `value` = {$code},
                            `user_id` = {$user},
                            `license_id` = {$license},
                            `time` > {$time}");

        return $code;
    }

    public function getUserCodeInfo($userId, $codeValue)
    {
        $user = $this->db->quote($userId);
        $code = $this->db->quote($codeValue);

        return $this->db->query("SELECT `value` FROM `codes` WHERE `user_id` = {$user} AND `value` = {$code} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    public function getActiveCodeInfo($codeValue)
    {
        $timeFrom = time() - self::ACTIVETIME;
        $code = $this->db->quote($codeValue);

        return $this->db->query("SELECT `license_id`, `user_id`  FROM `codes` WHERE `value` = {$code} AND `assigned_device_id` = 0 AND `time` > {$timeFrom}  LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    }

    private function getNewCode()
    {
        return rand(1, 9999);
    }

}
