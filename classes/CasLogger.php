<?php

class CasLogger
{
    public static function log($level, $message, $context = false)
    {
        $logMessage = $message;
        if ($context) {
            $logMessage .= ' (in ' . $context . ')';
        }
        if ($level == 'error') {
            eZDebug::writeError($message, $context);
            eZLog::write("[error] $logMessage", 'caslogin.log');
        }
        if ($level == 'warning') {
            eZDebug::writeWarning($message, $context);
            eZLog::write("[warning] $logMessage", 'caslogin.log');
        }
        if ($level == 'notice') {
            eZDebug::writeNotice($message, $context);
            eZLog::write("[notice] $logMessage", 'caslogin.log');
        }
        if ($level == 'debug') {
            eZDebug::writeDebug($message, $context);
            eZLog::write("[debug] $logMessage", 'caslogin.log');
        }
    }

    public static function error($message, $context = false)
    {
        self::log('error', $message, $context);
    }
}