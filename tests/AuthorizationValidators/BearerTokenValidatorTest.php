<?php

namespace LeagueTests\AuthorizationValidators;

use Laminas\Diactoros\ServerRequest;
use Lcobucci\JWT\Builder;
use League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

class BearerTokenValidatorTest extends TestCase
{
    public function testThrowExceptionWhenAccessTokenIsNotSigned()
    {
        $accessTokenRepositoryMock = $this->getMockBuilder(AccessTokenRepositoryInterface::class)->getMock();

        $bearerTokenValidator = new BearerTokenValidator($accessTokenRepositoryMock);
        $bearerTokenValidator->setPublicKey(new CryptKey('file://' . __DIR__ . '/../Stubs/public.key'));

        $unsignedJwt = (new Builder())
            ->permittedFor('client-id')
            ->identifiedBy('token-id', true)
            ->issuedAt(\time())
            ->canOnlyBeUsedAfter(\time())
            ->expiresAt(\time())
            ->relatedTo('user-id')
            ->withClaim('scopes', 'scope1 scope2 scope3 scope4')
            ->getToken();

        $request = (new ServerRequest())->withHeader('authorization', \sprintf('Bearer %s', $unsignedJwt));

        $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
        $this->expectExceptionCode(9);

        $bearerTokenValidator->validateAuthorization($request);
    }
}
