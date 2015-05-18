<?php

namespace CS\Devices;

/**
 * Description of DeviceOptions
 *
 * @author root
 */
class DeviceOptions
{

    public static function isLockActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isBlockSMSActive($os, $osVersion)
    {
        if ($os == 'android') {
            return self::compareOSVersion('android', '4.4', $osVersion, '<');
        } else if ($os == 'ios') {
            return true;
        }

        return false;
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

    public static function isBrowserBookmarksActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isBrowserHistoryActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isCalendarActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isContactsActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isKeyloggerActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isPhotosActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isVideosActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isViberActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isSkypeActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isWhatsappActive($os)
    {
        if ($os == 'blackberry') {
            return false;
        }

        return true;
    }

    public static function isFacebookActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isVkActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isKikActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isEmailsActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isApplicationsActive($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isSmsCommandsActive($os, $osVersion)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        } else if ($os == 'android') {
            return self::compareOSVersion('android', '4.4', $osVersion, '<');
        }

        return true;
    }

    public static function isLocationsActive($os)
    {
        if ($os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isInstagramActive($os)
    {
        if ($os == 'icloud' || $os == 'blackberry') {
            return false;
        }
        return true;
    }
    
    public static function isNotesActive($os, $osVersion)
    {
        if ($os == 'icloud' || ($os == 'ios' && self::compareOSVersion('ios', '7', $osVersion, '>='))) {
            return true;
        }
        
        return false;
    }
    
    public static function isSnapchatActive($os, $osVersion)
    {
        if ($os == 'ios' && self::compareOSVersion('ios', '7', $osVersion, '>=')) {
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

    public static function isBlackListAvailable($os)
    {
        if ($os == 'icloud') {
            return false;
        } else
            return true;
    }

    public static function isSimNotificationAvailable($os)
    {
        if ($os == 'icloud') {
            return false;
        } else
            return true;
    }

    public static function isDeviceCommandsAvailable($os)
    {
        if ($os == 'icloud') {
            return false;
        } else
            return true;
    }

    public static function isDeviceBlockSiteAvailable($os)
    {
        if ($os == 'icloud') {
            return false;
        } else
            return true;
    }

    public static function isDeletedDataAvailable($os)
    {
        if ($os == 'icloud') {
            return false;
        } else
            return true;
    }

    public static function isOutgoingSmsLimitationsAvailable($os)
    {
        if ($os == 'blackberry' || $os == 'icloud') {
            return false;
        }

        return true;
    }

    public static function isOutgoingSmsLimitationsActive($os, $keyloggerEnabled)
    {
        if ($os == 'android' && !$keyloggerEnabled) {
            return false;
        }

        return self::isOutgoingSmsLimitationsAvailable($os);
    }

}
