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

    const CODE_PREFIX = 'c';
    const USER_PREFIX = 'u';
    const CODE_LICENSE_PREFIX = 'l';
    const GENERATION_LIMIT = 100;
    const LIFETIME = 900;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function existUser($id)
    {
        return $this->redis->exists(self::USER_PREFIX . $id);
    }

    public function existCode($value)
    {
        return $this->redis->exists(self::CODE_PREFIX . $value);
    }

    public function existCodeLicense($code)
    {
        return $this->redis->exists(self::CODE_LICENSE_PREFIX . $code);
    }

    public function getCodeLicense($code)
    {
        return $this->redis->get(self::CODE_LICENSE_PREFIX . $code);
    }

    public function getUserCode($id)
    {
        return $this->redis->get(self::USER_PREFIX . $id);
    }

    public function getCodeUser($value)
    {
        return $this->redis->get(self::CODE_PREFIX . $value);
    }

    public function createCode($userId, $licenseId = null)
    {
        if (($code = $this->getUserCode($userId)) !== null) {
            return $code;
        }

        for ($i = 1; $i <= self::GENERATION_LIMIT; $i++) {
            $code = $this->getNewCode();
            if (!$this->existCode($code)) {
                break;
            } else if ($i == self::GENERATION_LIMIT) {
                throw new DeviceCodeGenerationException("Device code generation limit reached!");
            }
        }

        $this->redis->set(self::CODE_PREFIX . $code, $userId);
        $this->redis->expire(self::CODE_PREFIX . $code, self::LIFETIME);

        $this->redis->set(self::USER_PREFIX . $userId, $code);
        $this->redis->expire(self::USER_PREFIX . $userId, self::LIFETIME);

        if ($licenseId !== null) {
            $this->redis->set(self::CODE_LICENSE_PREFIX . $code, $licenseId);
            $this->redis->expire(self::CODE_LICENSE_PREFIX . $code, self::LIFETIME);
        }
        return $code;
    }

    private function getNewCode()
    {
        return rand(0, 9999);
    }

    public function removeCode($value)
    {
        if ($this->redis->exists(self::CODE_PREFIX . $value)) {
            if (($user = $this->redis->get(self::CODE_PREFIX . $value)) !== null) {
                $this->redis->del(self::USER_PREFIX . $user);
            }
            $this->redis->del(self::CODE_PREFIX . $value);
            return true;
        }

        return false;
    }

    public function removeUser($id)
    {
        if ($this->redis->exists(self::USER_PREFIX . $id)) {
            if (($code = $this->redis->get(self::USER_PREFIX . $id)) !== null) {
                $this->redis->del(self::CODE_PREFIX . $code);
            }
            $this->redis->del(self::USER_PREFIX . $id);
            return true;
        }

        return false;
    }

}
