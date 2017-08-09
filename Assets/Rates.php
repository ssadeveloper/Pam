<?php

namespace Pam\Assets;

use Pam\Client;

class Rates
{
    private static $clientsWithBasicRateCard = ['acu', 'uc'];

    public static function showBasicRateCard() {
        return in_array(Client::get()->getId(), static::$clientsWithBasicRateCard);
    }
}