<?php
/**
 * Public/private key encryption.
 *
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

trait CryptTrait
{
    /**
     * @var string
     */
    protected $encryptionKey;

    /**
     * Encrypt data with a private key.
     *
     * @param string|Key $unencryptedData
     *
     * @throws \LogicException
     *
     * @return string
     */
    protected function encrypt($unencryptedData)
    {
        try {
            if($this->encryptionKey instanceof Key) {
                return Crypto::encrypt($unencryptedData, $this->encryptionKey);
            } else {
                return Crypto::encryptWithPassword($unencryptedData, $this->encryptionKey);
            }
        } catch (\Exception $e) {
            throw new \LogicException($e->getMessage());
        }
    }

    /**
     * Decrypt data with a public key.
     *
     * @param string $encryptedData
     *
     * @throws \LogicException
     *
     * @return string
     */
    protected function decrypt($encryptedData)
    {
        try {
            if($this->encryptionKey instanceof Key) {
                return Crypto::decrypt($encryptedData, $this->encryptionKey);
            } else {
                return Crypto::decryptWithPassword($encryptedData, $this->encryptionKey);
            }
        } catch (\Exception $e) {
            throw new \LogicException($e->getMessage());
        }
    }

    /**
     * Set the encryption key
     *
     * @param string|Key $key
     */
    public function setEncryptionKey($key = null)
    {
        $this->encryptionKey = $key;
    }
}
