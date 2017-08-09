<?php
namespace Pam;

class Client
{
    /**
     * @var Client
     */
    private static $instance;

    private $client;

    private function __construct($client)
    {
        $this->client = $client;
    }

    public static function isClientInitiated()
    {
        return (bool) static::$instance;
    }

    public static function get()
    {
        return static::init();
    }

    public static function init($clientId = null)
    {
        if (!static::$instance) {
            $client = static::fetchClient($clientId);
            static::$instance = new static($client);
        }
        return static::$instance;
    }

    public static function changeClient($clientId)
    {
        $client = static::fetchClient($clientId);
        static::$instance = new static($client);
        return static::$instance;
    }

    private static function fetchClient($clientId = null)
    {
        global $db;
        if (!$clientId) {
            $clientId = isset($_SESSION['USER']) ? $_SESSION['USER']['clientId']: null;
        }
        if ($clientId) {
            $clientId = mysqli_real_escape_string($db, $clientId);
            $result = mysqli_query($db, "SELECT * FROM client where id = '{$clientId}'");
            $client = mysqli_fetch_assoc($result);
            if (null === $client) {
                throw new \Exception("Client with ID '{$clientId}' does not exist");
            }
            return $client;
        } else {
            throw new \Exception('Unable to retrieve client by logged in user - session is not started yet');
        }
    }

    public function getId()
    {
        return $this->client['id'];
    }
    
    public function getName()
    {
        return $this->client['name'];
    }

    public function getPassword()
    {
        return $this->client['password'];
    }

    public function getTimeZone()
    {
        return new \DateTimeZone($this->client['timeZone']);
    }

    public function date($format, $timestamp=null)
    {
        $date = new \DateTime(null, $this->getTimeZone());
        if ($timestamp) {
            $date->setTimestamp($timestamp);
        }
        return $date->format($format);
    }
}