<?php
namespace OAuth2ServerExamples\Repositories;

use League\OAuth2\Server\Entities\ClientEntity;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function getClientEntity($clientIdentifier, $grantType, $clientSecret = null, $redirectUri = null)
    {
        $clients = [
            'myawesomeapp' => [
                'secret'       => password_hash('abc123', PASSWORD_BCRYPT),
                'name'         => 'My Awesome App',
                'redirect_uri' => ''
            ]
        ];

        // Check if client is registered
        if (array_key_exists($clientIdentifier, $clients) === false) {
            return null;
        }

        // Check if client secret is valid
        if ($clientSecret !== null && password_verify($clientSecret, $clients[$clientIdentifier]['secret']) === false) {
            return null;
        }

        // Check if redirect URI is valid
        if ($redirectUri !== null && $redirectUri !== $clients[$clientIdentifier]['redirectUri']) {
            return null;
        }

        $client = new ClientEntity();
        $client->setIdentifier($clientIdentifier);
        $client->setName($clients[$clientIdentifier]['name']);

        return $client;
    }
}
