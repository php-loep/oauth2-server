<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace League\OAuth2\Server\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class OAuthServerException extends Exception
{
    /**
     * @var int
     */
    private $httpStatusCode;

    /**
     * @var string
     */
    private $errorType;

    /**
     * @var null|string
     */
    private $redirectUri;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var ServerRequestInterface
     */
    private $serverRequest;

    /**
     * Throw a new exception.
     *
     * @param string      $message        Error message
     * @param int         $code           Error code
     * @param string      $errorType      Error type
     * @param int         $httpStatusCode HTTP status code to send (default = 400)
     * @param null|string $redirectUri    A HTTP URI to redirect the user back to
     * @param Throwable   $previous       Previous exception
     */
    public function __construct($message, $code, $errorType, $httpStatusCode = 400, $redirectUri = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->errorType = $errorType;
        $this->redirectUri = $redirectUri;
        $this->payload = [
            'error'             => $errorType,
            'error_description' => $message,
        ];
    }

    /**
     * Returns the current payload.
     *
     * @return array
     */
    public function getPayload()
    {
        $payload = $this->payload;

        // The "message" property is deprecated and replaced by "error_description"
        // TODO: remove "message" property
        if (isset($payload['error_description']) && !isset($payload['message'])) {
            $payload['message'] = $payload['error_description'];
        }

        return $payload;
    }

    /**
     * Updates the current payload.
     *
     * @param array $payload
     */
    public function setPayload(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Set the server request that is responsible for generating the exception
     *
     * @param ServerRequestInterface $serverRequest
     */
    public function setServerRequest(ServerRequestInterface $serverRequest)
    {
        $this->serverRequest = $serverRequest;
    }

    /**
     * Unsupported grant type error.
     *
     * @return static
     */
    public static function unsupportedGrantType()
    {
        $errorMessage = 'The grant type is not supported by the authorization server.';

        return new static($errorMessage, 2, 'unsupported_grant_type', 400);
    }

    /**
     * Invalid request error.
     *
     * @param string    $errorMessage The error message
     * @param Throwable $previous     Previous exception
     *
     * @return static
     */
    public static function invalidRequest($errorMessage, Throwable $previous = null)
    {
        return new static($errorMessage, 3, 'invalid_request', 400, null, $previous);
    }

    /**
     * Invalid client error.
     *
     * @param string                 $errorMessage
     * @param ServerRequestInterface $serverRequest
     *
     * @return static
     */
    public static function invalidClient($errorMessage, ServerRequestInterface $serverRequest)
    {
        $exception = new static($errorMessage, 4, 'invalid_client', 401);

        $exception->setServerRequest($serverRequest);

        return $exception;
    }

    /**
     * Invalid scope error.
     *
     * @param string      $errorMessage The error message
     * @param null|string $redirectUri  A HTTP URI to redirect the user back to
     *
     * @return static
     */
    public static function invalidScope($errorMessage, $redirectUri = null)
    {
        return new static($errorMessage, 5, 'invalid_scope', 400, $redirectUri);
    }

    /**
     * Server error.
     *
     * @param Throwable $previous
     *
     * @return static
     *
     * @codeCoverageIgnore
     */
    public static function serverError(Throwable $previous = null)
    {
        return new static(
            'The authorization server encountered an unexpected condition which prevented it from fulfilling'
            . ' the request',
            7,
            'server_error',
            500,
            null,
            null,
            $previous
        );
    }

    /**
     * Access denied.
     *
     * @param null|string $redirectUri
     * @param Throwable   $previous
     *
     * @return static
     */
    public static function accessDenied($errorMessage, $redirectUri = null, Throwable $previous = null)
    {
        return new static(
            $errorMessage,
            9,
            'access_denied',
            401,
            $redirectUri,
            $previous
        );
    }

    /**
     * Invalid grant.
     *
     * @param string    $errorMessage
     * @param Throwable $previous
     *
     * @return static
     */
    public static function invalidGrant($errorMessage, Throwable $previous = null)
    {
        return new static(
            $errorMessage,
            10,
            'invalid_grant',
            400,
            null,
            $previous
        );
    }

    /**
     * @return string
     */
    public function getErrorType()
    {
        return $this->errorType;
    }

    /**
     * Generate a HTTP response.
     *
     * @param ResponseInterface $response
     * @param bool              $useFragment True if errors should be in the URI fragment instead of query string
     * @param int               $jsonOptions options passed to json_encode
     *
     * @return ResponseInterface
     */
    public function generateHttpResponse(ResponseInterface $response, $useFragment = false, $jsonOptions = 0)
    {
        $headers = $this->getHttpHeaders();

        $payload = $this->getPayload();

        if ($this->redirectUri !== null) {
            if ($useFragment === true) {
                $this->redirectUri .= (\strstr($this->redirectUri, '#') === false) ? '#' : '&';
            } else {
                $this->redirectUri .= (\strstr($this->redirectUri, '?') === false) ? '?' : '&';
            }

            return $response->withStatus(302)->withHeader('Location', $this->redirectUri . \http_build_query($payload));
        }

        foreach ($headers as $header => $content) {
            $response = $response->withHeader($header, $content);
        }

        $responseBody = \json_encode($payload, $jsonOptions) ?: 'JSON encoding of payload failed';

        $response->getBody()->write($responseBody);

        return $response->withStatus($this->getHttpStatusCode());
    }

    /**
     * Get all headers that have to be send with the error response.
     *
     * @return array Array with header values
     */
    public function getHttpHeaders()
    {
        $headers = [
            'Content-type' => 'application/json',
        ];

        // Add "WWW-Authenticate" header
        //
        // RFC 6749, section 5.2.:
        // "If the client attempted to authenticate via the 'Authorization'
        // request header field, the authorization server MUST
        // respond with an HTTP 401 (Unauthorized) status code and
        // include the "WWW-Authenticate" response header field
        // matching the authentication scheme used by the client.
        // @codeCoverageIgnoreStart
        if ($this->errorType === 'invalid_client' && $this->serverRequest->hasHeader('Authorization') === true) {
            $authScheme = \strpos($this->serverRequest->getHeader('Authorization')[0], 'Bearer') === 0 ? 'Bearer' : 'Basic';

            $headers['WWW-Authenticate'] = $authScheme . ' realm="OAuth"';
        }
        // @codeCoverageIgnoreEnd
        return $headers;
    }

    /**
     * Check if the exception has an associated redirect URI.
     *
     * Returns whether the exception includes a redirect, since
     * getHttpStatusCode() doesn't return a 302 when there's a
     * redirect enabled. This helps when you want to override local
     * error pages but want to let redirects through.
     *
     * @return bool
     */
    public function hasRedirect()
    {
        return $this->redirectUri !== null;
    }

    /**
     * Returns the Redirect URI used for redirecting.
     *
     * @return string|null
     */
    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    /**
     * Returns the HTTP status code to send when the exceptions is output.
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }
}
