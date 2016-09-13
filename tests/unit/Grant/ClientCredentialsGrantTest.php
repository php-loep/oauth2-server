<?php

namespace LeagueTests\Grant;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\ScopeEntity;
use League\OAuth2\Server\Entity\SessionEntity;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use Mockery as M;

class ClientCredentialsGrantTest extends \PHPUnit_Framework_TestCase
{
    public function testCompleteFlowMissingClientId()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRequestException');

        $_POST['grant_type'] = 'client_credentials';

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowMissingClientSecret()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidRequestException');

        $_POST = [
            'grant_type' => 'client_credentials',
            'client_id'  => 'testapp',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowInvalidClient()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidClientException');

        $_POST = [
            'grant_type'    => 'client_credentials',
            'client_id'     => 'testapp',
            'client_secret' => 'foobar',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(null);

        $server->setClientStorage($clientStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowInvalidScope()
    {
        $this->setExpectedException('League\OAuth2\Server\Exception\InvalidScopeException');

        $_POST = [
            'grant_type'    => 'client_credentials',
            'client_id'     => 'testapp',
            'client_secret' => 'foobar',
            'scope'         => 'foo',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('create');
        $sessionStorage->shouldReceive('getScopes')->andReturn([]);

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([]);

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(null);

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlowNoScopes()
    {
        $_POST = [
            'grant_type'    => 'client_credentials',
            'client_id'     => 'testapp',
            'client_secret' => 'foobar',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('create')->andreturn(123);
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([]);
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))->setId('foobar')
        );
        $sessionStorage->shouldReceive('associateScope');

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([]);
        $accessTokenStorage->shouldReceive('associateScope');

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        // $scopeStorage->shouldReceive('get')->andReturn(
        //     // (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        // );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testCompleteFlow()
    {
        $_POST = [
            'grant_type'    => 'client_credentials',
            'client_id'     => 'testapp',
            'client_secret' => 'foobar',
            'scope'         => 'foo',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andReturn(
            (new ClientEntity($server))->hydrate(['id' => 'testapp'])
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('create')->andreturn(123);
        $sessionStorage->shouldReceive('getScopes')->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))->setId('foobar')
        );
        $sessionStorage->shouldReceive('associateScope');

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');
        $accessTokenStorage->shouldReceive('create');
        $accessTokenStorage->shouldReceive('getScopes')->andReturn([
            (new ScopeEntity($server))->hydrate(['id' => 'foo']),
        ]);
        $accessTokenStorage->shouldReceive('associateScope');

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }

    public function testClientNotAuthorizedToUseGrant()
    {
        $this->setExpectedException('\League\OAuth2\Server\Exception\UnauthorizedClientException');

        $_POST = [
            'grant_type'    => 'client_credentials',
            'client_id'     => 'testapp',
            'client_secret' => 'foobar',
            'scope'         => 'foo',
        ];

        $server = new AuthorizationServer();
        $grant = new ClientCredentialsGrant();

        $clientStorage = M::mock('League\OAuth2\Server\Storage\ClientInterface');
        $clientStorage->shouldReceive('setServer');
        $clientStorage->shouldReceive('get')->andThrow(
            new \League\OAuth2\Server\Exception\UnauthorizedClientException()
        );

        $sessionStorage = M::mock('League\OAuth2\Server\Storage\SessionInterface');
        $sessionStorage->shouldReceive('setServer');
        $sessionStorage->shouldReceive('getByAccessToken')->andReturn(
            (new SessionEntity($server))->setId('foobar')
        );

        $accessTokenStorage = M::mock('League\OAuth2\Server\Storage\AccessTokenInterface');
        $accessTokenStorage->shouldReceive('setServer');

        $scopeStorage = M::mock('League\OAuth2\Server\Storage\ScopeInterface');
        $scopeStorage->shouldReceive('setServer');
        $scopeStorage->shouldReceive('get')->andReturn(
            (new ScopeEntity($server))->hydrate(['id' => 'foo'])
        );

        $server->setClientStorage($clientStorage);
        $server->setScopeStorage($scopeStorage);
        $server->setSessionStorage($sessionStorage);
        $server->setAccessTokenStorage($accessTokenStorage);

        $server->addGrantType($grant);
        $server->issueAccessToken();
    }
}
