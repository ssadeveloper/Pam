<?php
namespace Pam\Email;

use \PHPMailer;

global $PAM_google_email_username, $PAM_google_email_password;

GoogleSMTP::setUsername($PAM_google_email_username);
GoogleSMTP::setPassword($PAM_google_email_password);

/**
 * Class GoogleSMTP for sending emails via Google SMTP with authorization
 * @package Pam
 */
class GoogleSMTP
{
    /**
     * @var PHPMailer
     */
    private static $phpMailer;
    /**
     * @var GoogleSMTP
     */
    private static $instance;

    private static $username;
    private static $password;

    /**
     * @return mixed
     */
    public static function getUsername()
    {
        return self::$username;
    }

    /**
     * @param mixed $username
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * @return mixed
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * @param mixed $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    private function __construct()
    {}

    private function __clone()
    {}

    /**
     * @return GoogleSMTP
     */
    public static function instance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    private function preparePhpMailer() {
        static::$phpMailer = new PHPMailer;
        static::$phpMailer->isSMTP();

//Enable SMTP debugging
// 0 = off (for production use)
// 1 = client messages
// 2 = client and server messages
        static::$phpMailer->SMTPDebug = 0;

        static::$phpMailer->Debugoutput = 'html';
        static::$phpMailer->Host = 'smtp.gmail.com';
// use
// static::$phpMailer->Host = gethostbyname('smtp.gmail.com');
// if your network does not support SMTP over IPv6

//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
        static::$phpMailer->Port = 587;

//Set the encryption system to use - ssl (deprecated) or tls
        static::$phpMailer->SMTPSecure = 'tls';
        static::$phpMailer->SMTPAuth = true;

//Username to use for SMTP authentication - use full email address for gmail
        static::$phpMailer->Username = static::getUsername();

//Password to use for SMTP authentication
        static::$phpMailer->Password = static::getPassword();
    }

    /**
     * Sends email via Google SMTP with auth
     *
     * @param $from
     * @param $to
     * @param $subject
     * @param $message
     * @param array $headers
     * @return bool
     * @throws \phpmailerException
     */
    public function mail($from, $to, $subject, $message,array  $headers = array()) {
        $this->preparePhpMailer();
        $from = trim($from, ' >');
        if (strpos($from, '<') !== false) {
            $from = explode('<', $from);
            $fromName = $from[0];
            $fromAddress = $from[1];
        } else {
            $fromAddress = $from;
            $fromName = '';
        }

        $to = trim($to, ' >');
        if (strpos($to, '<') !== false) {
            $to = explode('<', $to);
            $toName = $to[0];
            $toAddress = $to[1];
        } else {
            $toAddress = $to;
            $toName = '';
        }
        
        static::$phpMailer->setFrom($fromAddress, $fromName);
        static::$phpMailer->addAddress($toAddress, $toName);
        static::$phpMailer->Subject = $subject;
        //Read an HTML message body from an external file, convert referenced images to embedded,
//convert HTML into a basic plain-text alternative body
        static::$phpMailer->msgHTML($message);

        foreach($headers as $header) {
            static::$phpMailer->addCustomHeader($header);
        }

        //send the message, check for errors
        if (!static::$phpMailer->send()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns last error for PHPMailer if it is set, NULL otherwise
     *
     * @return null|string
     */
    public function getLastError() {
        if (static::$phpMailer && static::$phpMailer->ErrorInfo) {
            return static::$phpMailer->ErrorInfo;
        }

        return null;
    }
}