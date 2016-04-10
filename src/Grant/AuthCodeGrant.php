<?php

namespace League\OAuth2\Server\Grant;

use DateInterval;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\ResponseTypes\RedirectResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use League\OAuth2\Server\TemplateRenderer\RendererInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthCodeGrant extends AbstractAuthorizeGrant
{
    /**
     * @var \DateInterval
     */
    private $authCodeTTL;

    /**
     * @param \League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface     $authCodeRepository
     * @param \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface $refreshTokenRepository
     * @param \League\OAuth2\Server\Repositories\UserRepositoryInterface         $userRepository
     * @param \DateInterval                                                      $authCodeTTL
     * @param \League\OAuth2\Server\TemplateRenderer\RendererInterface|null      $templateRenderer
     */
    public function __construct(
        AuthCodeRepositoryInterface $authCodeRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        UserRepositoryInterface $userRepository,
        \DateInterval $authCodeTTL,
        RendererInterface $templateRenderer = null
    ) {
        $this->setAuthCodeRepository($authCodeRepository);
        $this->setRefreshTokenRepository($refreshTokenRepository);
        $this->setUserRepository($userRepository);
        $this->authCodeTTL = $authCodeTTL;
        $this->refreshTokenTTL = new \DateInterval('P1M');
        $this->templateRenderer = $templateRenderer;
    }

    /**
     * Respond to an access token request.
     *
     * @param \Psr\Http\Message\ServerRequestInterface                  $request
     * @param \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface $responseType
     * @param \DateInterval                                             $accessTokenTTL
     *
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     *
     * @return \League\OAuth2\Server\ResponseTypes\ResponseTypeInterface
     */
    protected function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ) {
        // The redirect URI is required in this request
        $redirectUri = $this->getRequestParameter('redirect_uri', $request, null);
        if (is_null($redirectUri)) {
            throw OAuthServerException::invalidRequest('redirect_uri');
        }

        // Validate request
        $client = $this->validateClient($request);
        $encryptedAuthCode = $this->getRequestParameter('code', $request, null);

        if ($encryptedAuthCode === null) {
            throw OAuthServerException::invalidRequest('code');
        }

        // Validate the authorization code
        try {
            $authCodePayload = json_decode($this->decrypt($encryptedAuthCode));
            if (time() > $authCodePayload->expire_time) {
                throw OAuthServerException::invalidRequest('code', 'Authorization code has expired');
            }

            if ($this->authCodeRepository->isAuthCodeRevoked($authCodePayload->auth_code_id) === true) {
                throw OAuthServerException::invalidRequest('code', 'Authorization code has been revoked');
            }

            if ($authCodePayload->client_id !== $client->getIdentifier()) {
                throw OAuthServerException::invalidRequest('code', 'Authorization code was not issued to this client');
            }

            if ($authCodePayload->redirect_uri !== $redirectUri) {
                throw OAuthServerException::invalidRequest('redirect_uri', 'Invalid redirect URI');
            }

            $scopes = [];
            foreach ($authCodePayload->scopes as $scopeId) {
                $scope = $this->scopeRepository->getScopeEntityByIdentifier($scopeId);

                if (!$scope) {
                    // @codeCoverageIgnoreStart
                    throw OAuthServerException::invalidScope($scopeId);
                    // @codeCoverageIgnoreEnd
                }

                $scopes[] = $scope;
            }
        } catch (\LogicException  $e) {
            throw OAuthServerException::invalidRequest('code', 'Cannot decrypt the authorization code');
        }

        // Issue and persist access + refresh tokens
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $authCodePayload->user_id, $scopes);
        $refreshToken = $this->issueRefreshToken($accessToken);

        // Inject tokens into response type
        $responseType->setAccessToken($accessToken);
        $responseType->setRefreshToken($refreshToken);

        return $responseType;
    }

    /**
     * {@inheritdoc}
     */
    public function respondToRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ) {
        if (
            array_key_exists('response_type', $request->getQueryParams())
            && $request->getQueryParams()['response_type'] === 'code'
        ) {
            return $this->respondToAuthorizationRequest($request);
        }

        return $this->respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }

    /**
     * Return the grant identifier that can be used in matching up requests.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return 'authorization_code';
    }

    /**
     * {@inheritdoc}
     */
    public function canRespondToAuthorizationRequest(ServerRequestInterface $request)
    {
        return (
            array_key_exists('response_type', $request->getQueryParams())
            && $request->getQueryParams()['response_type'] === 'code'
            && isset($request->getQueryParams()['client_id'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthorizationRequest(ServerRequestInterface $request)
    {
        $clientId = $this->getQueryStringParameter(
            'client_id',
            $request,
            $this->getServerParameter('PHP_AUTH_USER', $request)
        );
        if (is_null($clientId)) {
            throw OAuthServerException::invalidRequest('client_id');
        }

        $client = $this->clientRepository->getClientEntity(
            $clientId,
            $this->getIdentifier()
        );

        if ($client instanceof ClientEntityInterface === false) {
            $this->getEmitter()->emit(new RequestEvent('client.authentication.failed', $request));
            throw OAuthServerException::invalidClient();
        }

        $redirectUri = $this->getQueryStringParameter('redirect_uri', $request);
        if ($redirectUri !== null) {
            if (
                is_string($client->getRedirectUri())
                && (strcmp($client->getRedirectUri(), $redirectUri) !== 0)
            ) {
                $this->getEmitter()->emit(new RequestEvent('client.authentication.failed', $request));
                throw OAuthServerException::invalidClient();
            } elseif (
                is_array($client->getRedirectUri())
                && in_array($redirectUri, $client->getRedirectUri()) === false
            ) {
                $this->getEmitter()->emit(new RequestEvent('client.authentication.failed', $request));
                throw OAuthServerException::invalidClient();
            }
        }

        $scopes = $this->validateScopes(
            $this->getQueryStringParameter('scope', $request),
            $client,
            $client->getRedirectUri()
        );

        $stateParameter = $this->getQueryStringParameter('state', $request);

        $authorizationRequest = new AuthorizationRequest();
        $authorizationRequest->setGrantTypeId($this->getIdentifier());
        $authorizationRequest->setClient($client);
        $authorizationRequest->setRedirectUri($redirectUri);
        $authorizationRequest->setState($stateParameter);
        $authorizationRequest->setScopes($scopes);

        return $authorizationRequest;
    }

    /**
     * {@inheritdoc}
     */
    public function completeAuthorizationRequest(AuthorizationRequest $authorizationRequest)
    {
        if ($authorizationRequest->getUser() instanceof UserEntityInterface === false) {
            throw new \LogicException('An instance of UserEntityInterface should be set on the AuthorizationRequest');
        }

        // The user approved the client, redirect them back with an auth code
        if ($authorizationRequest->isAuthorizationApproved() === true) {
            $authCode = $this->issueAuthCode(
                $this->authCodeTTL,
                $authorizationRequest->getClient(),
                $authorizationRequest->getUser()->getIdentifier(),
                $authorizationRequest->getRedirectUri(),
                $authorizationRequest->getScopes()
            );

            $redirectPayload['code'] = $this->encrypt(
                json_encode(
                    [
                        'client_id'    => $authCode->getClient()->getIdentifier(),
                        'redirect_uri' => $authCode->getRedirectUri(),
                        'auth_code_id' => $authCode->getIdentifier(),
                        'scopes'       => $authCode->getScopes(),
                        'user_id'      => $authCode->getUserIdentifier(),
                        'expire_time'  => (new \DateTime())->add($this->authCodeTTL)->format('U'),
                    ]
                )
            );

            $finalRedirectUri = ($authorizationRequest->getRedirectUri() === null)
                ? is_array($authorizationRequest->getClient()->getRedirectUri())
                    ? $authorizationRequest->getClient()->getRedirectUri()[0]
                    : $authorizationRequest->getClient()->getRedirectUri()
                : $authorizationRequest->getRedirectUri();

            $response = new RedirectResponse();
            $response->setRedirectUri(
                $this->makeRedirectUri(
                    $finalRedirectUri,
                    $redirectPayload
                )
            );

            return $response;
        }

        // The user denied the client, redirect them back with an error
        throw OAuthServerException::accessDenied(
            'The user denied the request',
            (string) $authorizationRequest->getRedirectUri()
        );
    }
}
