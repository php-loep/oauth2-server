<?php

namespace League\OAuth2\Server;

use DateInterval;
use League\Event\EmitterAwareInterface;
use League\Event\EmitterAwareTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\GrantTypeInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequestFactory;

class Server implements EmitterAwareInterface
{
    use EmitterAwareTrait;

    /**
     * @var \League\OAuth2\Server\Grant\GrantTypeInterface[]
     */
    protected $enabledGrantTypes = [];

    /**
     * @var ResponseTypeInterface[]
     */
    protected $grantResponseTypes = [];

    /**
     * @var DateInterval[]
     */
    protected $grantTypeAccessTokenTTL = [];

    /**
     * @var ResponseTypeInterface
     */
    protected $defaultResponseType;

    /**
     * @var DateInterval
     */
    protected $defaultAccessTokenTTL;

    /**
     * @var string
     */
    protected $scopeDelimiterString = ' ';

    /**
     * New server instance
     */
    public function __construct()
    {
        $this->setDefaultResponseType(new BearerTokenResponse());
        $this->setDefaultAccessTokenTTL(new DateInterval('PT01H')); // default token TTL of 1 hour
    }

    /**
     * Set the default token type that grants will return
     *
     * @param ResponseTypeInterface $defaultTokenType
     */
    public function setDefaultResponseType(ResponseTypeInterface $defaultTokenType)
    {
        $this->defaultResponseType = $defaultTokenType;
    }

    /**
     * Set the delimiter string used to separate scopes in a request
     *
     * @param string $scopeDelimiterString
     */
    public function setScopeDelimiterString($scopeDelimiterString)
    {
        $this->scopeDelimiterString = $scopeDelimiterString;
    }

    /**
     * Set the default TTL of access tokens
     *
     * @param DateInterval $defaultAccessTokenTTL
     */
    public function setDefaultAccessTokenTTL(DateInterval $defaultAccessTokenTTL)
    {
        $this->defaultAccessTokenTTL = $defaultAccessTokenTTL;
    }

    /**
     * Enable a grant type on the server
     *
     * @param \League\OAuth2\Server\Grant\GrantTypeInterface $grantType
     * @param ResponseTypeInterface                             $responseType
     * @param DateInterval                                   $accessTokenTTL
     */
    public function enableGrantType(
        GrantTypeInterface $grantType,
        ResponseTypeInterface $responseType = null,
        DateInterval $accessTokenTTL = null
    ) {
        $grantType->setEmitter($this->getEmitter());
        $this->enabledGrantTypes[$grantType->getIdentifier()] = $grantType;

        // Set grant response type
        if ($responseType instanceof ResponseTypeInterface) {
            $this->grantResponseTypes[$grantType->getIdentifier()] = $responseType;
        } else {
            $this->grantResponseTypes[$grantType->getIdentifier()] = $this->defaultResponseType;
        }

        // Set grant access token TTL
        if ($accessTokenTTL instanceof DateInterval) {
            $this->grantTypeAccessTokenTTL[$grantType->getIdentifier()] = $accessTokenTTL;
        } else {
            $this->grantTypeAccessTokenTTL[$grantType->getIdentifier()] = $this->defaultAccessTokenTTL;
        }
    }

    /**
     * Return an access token response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     *
     * @return \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    public function handleTokenRequest(ServerRequestInterface $request = null, ResponseInterface $response = null)
    {
        if ($request === null) {
            $request = ServerRequestFactory::fromGlobals();
        }

        if ($response === null) {
            $response = new Response('php://memory');
        }

        $tokenResponse = null;
        foreach ($this->enabledGrantTypes as $grantType) {
            if ($grantType->canRespondToRequest($request)) {
                $tokenResponse = $grantType->respondToRequest(
                    $request,
                    $this->grantResponseTypes[$grantType->getIdentifier()],
                    $this->grantTypeAccessTokenTTL[$grantType->getIdentifier()],
                    $this->scopeDelimiterString
                );
            }
        }

        if ($tokenResponse instanceof ResponseTypeInterface) {
            return $tokenResponse->getResponse($response);
        }

        return OAuthServerException::unsupportedGrantType()->getResponse($response);
    }
}
