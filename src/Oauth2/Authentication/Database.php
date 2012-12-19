<?php

namespace Oauth2\Authentication;

interface Database
{
    /**
     * Validate a client
     *
     * Database query:
     *
     * <code>
     * # Client ID + redirect URI
     * SELECT clients.id FROM clients LEFT JOIN client_endpoints ON
     *  client_endpoints.client_id = clients.id WHERE clients.id = $clientId AND
     *  client_endpoints.redirect_uri = $redirectUri
     *
     * # Client ID + client secret
     * SELECT clients.id FROM clients  WHERE clients.id = $clientId AND
     *  clients.secret = $clientSecret
     *
     * # Client ID + client secret + redirect URI
     * SELECT clients.id FROM clients LEFT JOIN client_endpoints ON
     *  client_endpoints.client_id = clients.id WHERE clients.id = $clientId AND
     *  clients.secret = $clientSecret AND client_endpoints.redirect_uri =
     *  $redirectUri
     * </code>
     *
     * Response:
     *
     * <code>
     * Array
     * (
     *     [client_id] => (string) The client ID
     *     [client secret] => (string) The client secret
     *     [redirect_uri] => (string) The redirect URI used in this request
     *     [name] => (string) The name of the client
     * )
     * </code>
     *
     * @param  string $clientId       The client's ID
     * @param  string $clientSecret The client's secret (default = "null")
     * @param  string $redirectUri   The client's redirect URI (default = "null")
     * @return  bool|array               Returns false if the validation fails, array on success
     */
    public function validateClient(
        $clientId,
        $clientSecret = null,
        $redirectUri = null
    );

    /**
     * Create a new OAuth session
     *
     * Database query:
     *
     * <code>
     * INSERT INTO oauth_sessions (client_id, redirect_uri, owner_type,
     *  owner_id, auth_code, access_token, stage, first_requested, last_updated)
     *  VALUES ($clientId, $redirectUri, $type, $typeId, $authCode,
     *  $accessToken, $stage, UNIX_TIMESTAMP(NOW()), UNIX_TIMESTAMP(NOW()))
     * </code>
     *
     * @param  string $clientId    The client ID
     * @param  string $redirectUri The redirect URI
     * @param  string $type        The session owner's type (default = "user")
     * @param  string $typeId      The session owner's ID (default = "null")
     * @param  string $authCode    The authorisation code (default = "null")
     * @param  string $accessToken The access token (default = "null")
     * @param  string $stage       The stage of the session (default ="request")
     * @return  int The session ID
     */
    public function newSession(
        $clientId,
        $redirectUri,
        $type = 'user',
        $typeId = null,
        $authCode = null,
        $accessToken = null,
        $refreshToken = null,
        $accessTokenExpire = null,
        $stage = 'requested'
    );

    /**
     * Update an OAuth session
     *
     * Database query:
     *
     * <code>
     * UPDATE oauth_sessions SET auth_code = $authCode, access_token =
     *  $accessToken, stage = $stage, last_updated = UNIX_TIMESTAMP(NOW()) WHERE
     *  id = $sessionId
     * </code>
     *
     * @param  string $sessionId   The session ID
     * @param  string $authCode    The authorisation code (default = "null")
     * @param  string $accessToken The access token (default = "null")
     * @param  string $stage       The stage of the session (default ="request")
     * @return  void
     */
    public function updateSession(
        $sessionId,
        $authCode = null,
        $accessToken = null,
        $refreshToken = null,
        $accessTokenExpire = null,
        $stage = 'requested'
    );

    /**
     * Delete an OAuth session
     *
     * <code>
     * DELETE FROM oauth_sessions WHERE client_id = $clientId AND owner_type =
     *  $type AND owner_id = $typeId
     * </code>
     *
     * @param  string $clientId The client ID
     * @param  string $type     The session owner's type
     * @param  string $typeId   The session owner's ID
     * @return  void
     */
    public function deleteSession(
        $clientId,
        $type,
        $typeId
    );


    /**
     * Update the refresh token
     *
     * Database query:
     *
     * <code>
     * UPDATE oauth_sessions SET access_token = $newAccessToken, refresh_token =
     *  $newRefreshToken, access_toke_expires = $accessTokenExpires, last_updated = UNIX_TIMESTAMP(NOW()) WHERE
     *  refresh_token = $currentRefreshToken
     * </code>
     *
     * @param  string $currentRefreshToken   The session's current refresh token
     * @param  string $newAccessToken        The new access token for this session
     * @param  string $newRefreshToken       The new refresh token for the session
     * @param  int    $accessTokenExpires    The UNIX timestamp of when the new token expires
     * @return bool   Whether the $currentRefreshToken was valid or not.
     */
    public function refreshToken($currentRefreshToken, $newAccessToken, $newRefreshToken, $accessTokenExpires);

    /**
     * Validate that an authorisation code is valid
     *
     * Database query:
     *
     * <code>
     * SELECT id FROM oauth_sessions WHERE client_id = $clientID AND
     *  redirect_uri = $redirectUri AND auth_code = $authCode
     * </code>
     *
     * Response:
     *
     * <code>
     * Array
     * (
     *     [id] => (int) The session ID
     *     [client_id] => (string) The client ID
     *     [redirect_uri] => (string) The redirect URI
     *     [owner_type] => (string) The session owner type
     *     [owner_id] => (string) The session owner's ID
     *     [auth_code] => (string) The authorisation code
     *     [stage] => (string) The session's stage
     *     [first_requested] => (int) Unix timestamp of the time the session was
     *      first generated
     *     [last_updated] => (int) Unix timestamp of the time the session was
     *      last updated
     * )
     * </code>
     *
     * @param  string     $clientId    The client ID
     * @param  string     $redirectUri The redirect URI
     * @param  string     $authCode    The authorisation code
     * @return  int|bool   Returns the session ID if the auth code
     *  is valid otherwise returns false
     */
    public function validateAuthCode(
        $clientId,
        $redirectUri,
        $authCode
    );

    /**
     * Return the session ID for a given session owner and client combination
     *
     * Database query:
     *
     * <code>
     * SELECT id FROM oauth_sessions WHERE client_id = $clientId
     *  AND owner_type = $type AND owner_id = $typeId
     * </code>
     *
     * @param  string      $type     The session owner's type
     * @param  string      $typeId   The session owner's ID
     * @param  string      $clientId The client ID
     * @return string|null           Return the session ID as an integer if
     *  found otherwise returns false
     */
    public function hasSession(
        $type,
        $typeId,
        $clientId
    );

    /**
     * Return the access token for a given session
     *
     * Database query:
     *
     * <code>
     * SELECT access_token FROM oauth_sessions WHERE id = $sessionId
     * </code>
     *
     * @param  int         $sessionId The OAuth session ID
     * @return string|null            Returns the access token as a string if
     *  found otherwise returns null
     */
    public function getAccessToken($sessionId);

    /**
     * Removes an authorisation code associated with a session
     *
     * Database query:
     *
     * <code>
     * UPDATE oauth_sessions SET auth_code = NULL WHERE id = $sessionId
     * </code>
     *
     * @param  int    $sessionId The OAuth session ID
     * @return void
     */
    public function removeAuthCode($sessionId);

    /**
     * Sets a sessions access token
     *
     * Database query:
     *
     * <code>
     * UPDATE oauth_sessions SET access_token = $accessToken WHERE id =
     *  $sessionId
     * </code>
     *
     * @param int    $sessionId   The OAuth session ID
     * @param string $accessToken The access token
     * @return void
     */
    public function setAccessToken(
        $sessionId,
        $accessToken
    );

    /**
     * Associates a session with a scope
     *
     * Database query:
     *
     * <code>
     * INSERT INTO oauth_session_scopes (session_id, scope) VALUE ($sessionId,
     *  $scope)
     * </code>
     *
     * @param int    $sessionId The session ID
     * @param string $scope     The scope
     * @return void
     */
    public function addSessionScope(
        $sessionId,
        $scope
    );

    /**
     * Return information about a scope
     *
     * Database query:
     *
     * <code>
     * SELECT * FROM scopes WHERE scope = $scope
     * </code>
     *
     * Response:
     *
     * <code>
     * Array
     * (
     *     [id] => (int) The scope's ID
     *     [scope] => (string) The scope itself
     *     [name] => (string) The scope's name
     *     [description] => (string) The scope's description
     * )
     * </code>
     *
     * @param  string $scope The scope
     * @return array
     */
    public function getScope($scope);

    /**
     * Associate a session's scopes with an access token
     *
     * Database query:
     *
     * <code>
     * UPDATE oauth_session_scopes SET access_token = $accessToken WHERE
     *  session_id = $sessionId
     * </code>
     *
     * @param  int    $sessionId   The session ID
     * @param  string $accessToken The access token
     * @return void
     */
    public function updateSessionScopeAccessToken(
        $sessionId,
        $accessToken
    );

    /**
     * Return the scopes associated with an access token
     *
     * Database query:
     *
     * <code>
     * SELECT scopes.scope, scopes.name, scopes.description FROM
     * oauth_session_scopes JOIN scopes ON oauth_session_scopes.scope =
     *  scopes.scope WHERE access_token = $accessToken
     * </code>
     *
     * Response:
     *
     * <code>
     * Array
     * (
     *     [0] => Array
     *         (
     *             [scope] => (string) The scope
     *             [name] => (string) The scope's name
     *             [description] => (string) The scope's description
     *         )
     * )
     * </code>
     *
     * @param  string $accessToken The access token
     * @return array
     */
    public function accessTokenScopes($accessToken);
}
