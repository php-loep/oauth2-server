<?php
/**
 * OAuth 2.0 Bearer Token Type.
 *
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */
namespace League\OAuth2\Server\ResponseTypes;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\Interfaces\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BearerTokenResponse extends AbstractResponseType
{
    /**
     * {@inheritdoc}
     */
    public function generateHttpResponse(ResponseInterface $response)
    {
        $expireDateTime = $this->accessToken->getExpiryDateTime()->getTimestamp();

        $jwtAccessToken = $this->accessToken->convertToJWT($this->privateKeyPath);

        $responseParams = [
            'token_type'   => 'Bearer',
            'expires_in'   => $expireDateTime - (new \DateTime())->getTimestamp(),
            'access_token' => (string) $jwtAccessToken,
        ];

        if ($this->refreshToken instanceof RefreshTokenEntityInterface) {
            $refreshToken = $this->encrypt(
                json_encode(
                    [
                        'client_id'        => $this->accessToken->getClient()->getIdentifier(),
                        'refresh_token_id' => $this->refreshToken->getIdentifier(),
                        'access_token_id'  => $this->accessToken->getIdentifier(),
                        'scopes'           => $this->accessToken->getScopes(),
                        'user_id'          => $this->accessToken->getUserIdentifier(),
                        'expire_time'      => $expireDateTime,
                    ]
                )
            );

            $responseParams['refresh_token'] = $refreshToken;
        }

        $response = $response
            ->withStatus(200)
            ->withHeader('pragma', 'no-cache')
            ->withHeader('cache-control', 'no-store')
            ->withHeader('content-type', 'application/json; charset=UTF-8');

        $response->getBody()->write(json_encode($responseParams));

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAccessToken(ServerRequestInterface $request)
    {
        if ($request->hasHeader('authorization') === false) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $header = $request->getHeader('authorization');
        $jwt = trim(preg_replace('/^(?:\s+)?Bearer\s/', '', $header[0]));

        try {
            // Attempt to parse and validate the JWT
            $token = (new Parser())->parse($jwt);
            if ($token->verify(new Sha256(), $this->publicKeyPath) === false) {
                throw OAuthServerException::accessDenied('Access token could not be verified');
            }

            // Check if token has been revoked
            if ($this->accessTokenRepository->isAccessTokenRevoked($token->getClaim('jti'))) {
                throw OAuthServerException::accessDenied('Access token has been revoked');
            }

            // Return the request with additional attributes
            return $request
                ->withAttribute('oauth_access_token_id', $token->getClaim('jti'))
                ->withAttribute('oauth_client_id', $token->getClaim('aud'))
                ->withAttribute('oauth_user_id', $token->getClaim('sub'))
                ->withAttribute('oauth_scopes', $token->getClaim('scopes'));
        } catch (\InvalidArgumentException $exception) {
            // JWT couldn't be parsed so return the request as is
            throw OAuthServerException::accessDenied($exception->getMessage());
        }
    }
}
