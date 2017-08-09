<?php
namespace Pam\Encryption;

use Aws\Kms\KmsClient;

global $AWSconfig;
if (!$AWSconfig) {
    $AWSconfig = require __DIR__ . '/../../../connections/aws.config.php';
}

class Helper
{
    const PRIVATE_KEY_NAME = 'pamkey.pem';
    const PUBLIC_KEY_NAME = 'pamkey.pub';

    /**
     * Encrypts string using AWS KMS
     * @param $string
     * @param array $encryptionContext Array of key-value pairs
     * @return string
     * @throws \Exception
     */
    public static function encrypt($string, $encryptionContext = array('purpose' => 'password'))
    {
        /*global $AWSconfig;
        
        $keyId = $AWSconfig['kms']['KeyId'];
        $clientKms = new KmsClient($AWSconfig['default']);
        $encrypted = $clientKms->encrypt([
            'KeyId' => $keyId, 
            'EncryptionContext' => $encryptionContext,
            'Plaintext' => $string,
        ]);

        return base64_encode($encrypted->get('CiphertextBlob'));*/

        $publicKeyFile = 'file://' . static::getKeysPath() . static::PUBLIC_KEY_NAME;
        if (false === openssl_public_encrypt($string, $encryptedString, $publicKeyFile)) {
            throw new \Exception('Unable to encrypt string');
        }
        return base64_encode($encryptedString);
    }

    /**
     * Decrypts string using AWS KMS
     * @param string $encryptedString
     * @param array $encryptionContext Should be the same as for encrypt
     * @return mixed
     * @throws \Exception
     */
    public static function decrypt($encryptedString, $encryptionContext = array('purpose' => 'password'))
    {
        /*global $AWSconfig;

        $clientKms = new KmsClient($AWSconfig['default']);
        $result = $clientKms->decrypt([
            'EncryptionContext' => $encryptionContext,
            'CiphertextBlob' => base64_decode($encryptedString)
        ]);
        return $result->get('Plaintext');*/

        $privateKeyFile = 'file://' . static::getKeysPath() . static::PRIVATE_KEY_NAME;
        if (false === openssl_private_decrypt(base64_decode($encryptedString), $string, $privateKeyFile)) {
            throw new \Exception('Unable to decrypt string');
        }
        return $string;
    }

    private static function getKeysPath()
    {
        return __DIR__ . '/../../../keys/';
    }

    /**
     * Generates pseudo random string of the specified length, result string contains characters from base64 set
     * @param $length
     * @return mixed
     */
    public static function generateRandomString($length)
    {
        $bytes = openssl_random_pseudo_bytes($length);
        return substr(base64_encode($bytes), 0, $length);
    }
}