<?php

namespace CS\Devices;

/**
 * Description of DeviceOptions
 *
 * @author root
 */
class DeviceOptions
{

    public static function isLockActive($os, $osVersion)
    {
        if ($os === 'android') {
            return true;
        } else if ($os === 'ios' && compareOSVersion('ios', '7.1', $osVersion, '<')) {
            return true;
        }

        return false;
    }

    public static function isBlockSMSActive($os, $osVersion)
    {
        if ($os == 'android') {
            return compareOSVersion('android', '4.4', $osVersion, '<');
        } else if ($os == 'ios') {
            return true;
        }

        return false;
    }
    
    public static function compareOSVersion($os, $compVersion, $osVersion, $operator)
    {
        if ($os == 'android') {
            $parts = explode('_', $osVersion);
            if (count($parts) != 2) {
                return false;
            }
            $osVersion = $parts[1];
        }

        return version_compare($osVersion, $compVersion, $operator);
    }
    
    public static function isRebootDeviceActive($os)
    {
        if ($os === 'android') {
            return true;
        }
        
        return false;
    }
    
    public static function isRebootApplicationActive($os)
    {
        if ($os === 'android') {
            return true;
        }

        return false;
    }

}
