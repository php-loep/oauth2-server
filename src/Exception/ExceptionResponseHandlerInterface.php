<?php declare(strict_types=1);

namespace League\OAuth2\Server\Exception;

use Psr\Http\Message\ResponseInterface;

interface ExceptionResponseHandlerInterface
{
    /**
     * Generate a HTTP response from a OAuthServerException
     *
     * @param OAuthServerException  $exception
     * @param ResponseInterface     $response
     * @param bool                  $useFragment True if errors should be in the URI fragment instead of query string
     * @param int                   $jsonOptions options passed to json_encode
     *
     * @return ResponseInterface
     */
    public function generateHttpResponse(
        OAuthServerException $exception,
        ResponseInterface $response,
        $useFragment = false,
        $jsonOptions = 0
    );
}
