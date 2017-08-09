<?php
namespace Pam\Db;


use Pam\Client;

class Helper
{
    public static function initClientDbConnection($checkSession = true)
    {
        global $db;
        global $hostname_db;
        global $database_db;

        if ($checkSession && (session_status() != PHP_SESSION_ACTIVE || !isset($_SESSION['USER']))) {
            return;
        }

        $dbUserLogin = Client::get()->getId();
        $dbUserPassword = \Pam\Encryption\Helper::decrypt(Client::get()->getPassword());
        if (!defined('WITHOUT_CONNECTS_TO_DB')) {
            $db = mysqli_connect($hostname_db, $dbUserLogin, $dbUserPassword, $database_db) or die("Error: " . mysqli_error($db));
        }
    }

    
}