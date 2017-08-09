<?php

namespace Pam\Utils;

use Pam\Client;

class Date
{
    const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    const DATE_FORMAT = 'Y-m-d';

    //Date format: 1 February 2017
    const DISPLAY_DATE_FORMAT = 'j F Y';
    //Time format: 2:15 PM
    const DISPLAY_TIME_FORMAT = 'g:i A';

    const UTC_TIME_ZONE = 'UTC';
    const SYDNEY_TIME_ZONE = 'Australia/Sydney';
    const TORONTO_TIME_ZONE = 'America/Toronto';

    /**
     * Returns datetime converted from UTC timezone to client timezone
     * @param string $utcDateTime
     * @return string
     */
    public static function getClientDateTime($utcDateTime)
    {
        return static::convertDateTime($utcDateTime, static::UTC_TIME_ZONE, Client::get()->getTimeZone());
    }

    /**
     * Returns DateTime object converted from UTC timezone to client timezone
     * @param \DateTime $utcDateTimeObject
     * @return bool|\DateTime
     */
    public static function getClientDateTimeObject($utcDateTimeObject)
    {
        return static::convertDateTimeObject($utcDateTimeObject, Client::get()->getTimeZone());
    }

    /**
     * Returns date converted from UTC timezone to client timezone
     * @param string $utcDate
     * @return string
     */
    public static function getClientDate($utcDate)
    {
        return static::convertDate($utcDate, static::UTC_TIME_ZONE, Client::get()->getTimeZone());
    }

    /**
     * Converts datetime string from one timezone to another
     * @param string $dateTimeString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @return string
     */
    public static function convertDateTime($dateTimeString, $srcTimeZone, $dstTimeZone)
    {
        return static::convert($dateTimeString, $srcTimeZone, $dstTimeZone, static::DATE_TIME_FORMAT);
    }

    /**
     * Converts date string from one timezone to another
     * @param string $dateString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @return string
     */
    public static function convertDate($dateString, $srcTimeZone, $dstTimeZone)
    {
        return static::convert($dateString, $srcTimeZone, $dstTimeZone, static::DATE_FORMAT);
    }

    /**
     * @param string $dateTimeString
     * @param string|\DateTimeZone $srcTimeZone
     * @param string|\DateTimeZone $dstTimeZone
     * @param string $format
     * @return bool|string
     */
    private static function convert($dateTimeString, $srcTimeZone, $dstTimeZone, $format)
    {
        if (!($srcTimeZone instanceof \DateTimeZone)) {
            $srcTimeZone = new \DateTimeZone($srcTimeZone);
        }
        $dateTime = \DateTime::createFromFormat(
            $format,
            $dateTimeString,
            $srcTimeZone
        );
        $dateTime = static::convertDateTimeObject($dateTime, $dstTimeZone);
        if ($dateTime instanceof \DateTime) {
            return $dateTime->format(static::DATE_TIME_FORMAT);
        }
        return false;
    }

    /**
     * Converts datetime object tp specified timezone
     * @param \DateTime $dateTimeObject
     * @param string|\DateTimeZone $dstTimeZone
     * @return bool|\DateTime
     */
    public static function convertDateTimeObject($dateTimeObject, $dstTimeZone)
    {
        if (!($dstTimeZone instanceof \DateTimeZone)) {
            $dstTimeZone = new \DateTimeZone($dstTimeZone);
        }
        if ($dateTimeObject instanceof \DateTime) {
            $dateTimeObject->setTimezone($dstTimeZone);
            return $dateTimeObject;
        }
        return false;
    }

    /**
     * Creates new DateTime object in UTC time zone based on passed dateTime string
     * If null is passed as $dateTimeString then the DateTime object with current date and time in UTC time zone is returned
     * @param string|null $dateTimeString
     * @return bool|\DateTime
     */
    public static function getUtcDateTimeObject($dateTimeString = null)
    {
        $utcTimeZone = new \DateTimeZone(static::UTC_TIME_ZONE);
        if (is_null($dateTimeString)) {
            $dateTime = new \DateTime('now', $utcTimeZone);
        } else {
            $dateTime = \DateTime::createFromFormat(
                static::DATE_TIME_FORMAT,
                $dateTimeString,
                $utcTimeZone
            );
        }
        return $dateTime;
    }

    /**
     * Returns date string in format suitable for displaying, e.g. "25 February 2017"
     * @param \DateTime $dateTimeObject
     * @return string
     */
    public static function getDisplayDate($dateTimeObject)
    {
        $clientDateTimeObject = static::getClientDateTimeObject($dateTimeObject);
        return $clientDateTimeObject ? $clientDateTimeObject->format(static::DISPLAY_DATE_FORMAT) : '';
    }

    /**
     * Returns time string in format suitable for displaying, e.g. "2:15 PM"
     * @param \DateTime $dateTimeObject
     * @return string
     */
    public static function getDisplayTime($dateTimeObject)
    {
        $clientDateTimeObject = static::getClientDateTimeObject($dateTimeObject);
        return $clientDateTimeObject ? $clientDateTimeObject->format(static::DISPLAY_TIME_FORMAT) : '';
    }
}