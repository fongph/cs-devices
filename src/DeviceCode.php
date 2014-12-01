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
     * @var \PDO
     */
    private $db;

    const GENERATION_LIMIT = 100;
    const ACTIVETIME = 900; // 15 min

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getUserCode($userId, $licenseId = null)
    {
        $timeFrom = time() - self::ACTIVETIME;
        $user = $this->db->quote($userId);

        if ($licenseId == null) {
            return $this->db->query("SELECT `value` FROM `codes` WHERE 
                                            `user_id` = {$user} AND
                                            `license_id` IS NULL AND 
                                            `time` > {$timeFrom} 
                                        LIMIT 1")->fetchColumn();
        }

        $license = $this->db->quote($licenseId);

        return $this->db->query("SELECT `value` FROM `codes` WHERE
                                        `user_id` = {$user} AND
                                        `license_id` = {$license} AND
                                        `time` > {$timeFrom}
                                    LIMIT 1")->fetchColumn();
    }

    private function getAllActiveCodes()
    {
        $timeFrom = time() - self::ACTIVETIME;

        return $this->db->query("SELECT `value` FROM `codes` WHERE `time` > {$timeFrom}")->fetchALL(\PDO::FETCH_COLUMN);
    }

    private function generateUniqueCode()
    {
        $codes = $this->getAllActiveCodes();

        for ($i = 0; $i < self::GENERATION_LIMIT; $i++) {
            $code = $this->getNewCode();
            if (!in_array($code, $codes)) {
                return $code;
            }
        }

        throw new DeviceCodeGenerationException("Device code generation limit reached!");
    }

    public function createCode($userId, $licenseId = null)
    {
        if (($code = $this->getUserCode($userId, $licenseId)) !== false) {
            return $code;
        }

        $code = $this->generateUniqueCode();

        $time = time();
        $user = $this->db->quote($userId);
        $license = ($licenseId == null) ? 'NULL' : $this->db->quote($licenseId);

        $this->db->exec("INSERT INTO `codes` SET
                            `value` = {$code},
                            `user_id` = {$user},
                            `license_id` = {$license},
                            `time` = {$time}");

        return $code;
    }

    public function getUserCodeInfo($userId, $codeValue)
    {
        $user = $this->db->quote($userId);
        $code = $this->db->quote($codeValue);
        $timeFrom = time() - self::ACTIVETIME;

        return $this->db->query("SELECT
                                        `license_id`,
                                        IF(`assigned_device_id` > 0, 1, 0) as `assigned`,
                                        IF(`time` < {$timeFrom}, 1, 0) as `expired`,
                                    FROM `codes` 
                                    WHERE
                                        `user_id` = {$user} AND
                                        `value` = {$code}
                                    LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
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
