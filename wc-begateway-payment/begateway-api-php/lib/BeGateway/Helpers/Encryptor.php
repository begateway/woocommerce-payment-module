<?php
namespace BeGateway\Helpers;

class Encryptor {

    const PREFIX_CSE = 'begatewaycsejs_1_0_0';

    /**
    * Encrypts input string using public key
    *
    * @param string $message
    * @param string $public_key
    * @return string|false
    */
    public static function encrypt($message, $public_key) {
        $encrypted = '';
        if (openssl_public_encrypt(
            $message, 
            $encrypted, 
            self::_prepare_public_key($public_key),
            OPENSSL_PKCS1_PADDING)) 
        {
            return self::_add_encryption_method_prefix(base64_encode($encrypted));
        } else {
            return false;
        }
    }

    /**
    * Verify singature using public key
    *
    * @param string $message
    * @param string $signature
    * @param string $public_key
    * @return string
    */

    public static function verify($message, $signature, $public_key) {

    }

    /**
    * Prepare public key from base64 encoded one
    *
    * @param string $public_key
    * @return OpenSSLAsymmetricKey|false 
    */

    private static function _prepare_public_key($public_key) {
        $pkey = str_replace(array("\r\n", "\n"), '', $public_key);
        $pkey = chunk_split($pkey, 64);
        $pkey = "-----BEGIN PUBLIC KEY-----\n" . $pkey . "-----END PUBLIC KEY-----";
        return openssl_pkey_get_public($pkey);
    }

    /**
    * Add encryption prefix to message
    *
    * @param string $message
    * @return string
    */

    private static function _add_encryption_method_prefix($message) {
        return implode('', ['$', self::PREFIX_CSE, '$', $message]);
    }
}
