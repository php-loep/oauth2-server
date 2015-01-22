<?php
/**
 * OAuth 2.0 MAC Token Interface
 *
 * @package     league/oauth2-server
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Storage;

use League\OAuth2\Server\Entity\AccessTokenInterface as AccessTokenEntityInterface;
use League\OAuth2\Server\Entity\MacKeyInterface;

/**
 * MacTokenInterface
 */
interface MacTokenInterface extends StorageInterface
{
    /**
     * Create a MAC key linked to an access token
     * @param string                                            $macKey
     * @param \League\OAuth2\Server\Entity\AccessTokenInterface $accessToken
     */
    public function create($macKey, AccessTokenEntityInterface $accessToken);

    /**
     * Get a MAC key by access token
     * @param string $accessToken
     * @return string
     */
    public function getByAccessToken($accessToken);
}