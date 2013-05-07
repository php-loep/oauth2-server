<?php

use \Mockery as m;

class Authorization_Server_test extends PHPUnit_Framework_TestCase
{
    private $client;
    private $session;
    private $scope;

    public function setUp()
    {
        $this->client = M::mock('OAuth2\Storage\ClientInterface');
        $this->session = M::mock('OAuth2\Storage\SessionInterface');
        $this->scope = M::mock('OAuth2\Storage\ScopeInterface');
    }

    private function returnDefault()
    {
        return new OAuth2\AuthServer($this->client, $this->session, $this->scope);
    }

    /**
     * @expectedException PHPUnit_Framework_Error
     */
    public function test__construct_NoStorage()
    {
        $a = new OAuth2\AuthServer;
    }

    public function test__contruct_WithStorage()
    {
        $a = $this->returnDefault();
    }

    public function test_getExceptionMessage()
    {
        $m = OAuth2\AuthServer::getExceptionMessage('access_denied');

        $reflector = new ReflectionClass($this->returnDefault());
        $exceptionMessages = $reflector->getProperty('exceptionMessages');
        $exceptionMessages->setAccessible(true);
        $v = $exceptionMessages->getValue();

        $this->assertEquals($v['access_denied'], $m);
    }

    public function test_getExceptionCode()
    {
        $this->assertEquals('access_denied', OAuth2\AuthServer::getExceptionType(2));
    }

    public function test_getExceptionHttpHeaders()
    {
        $this->assertEquals(array('HTTP/1.1 401 Unauthorized'), OAuth2\AuthServer::getExceptionHttpHeaders('access_denied'));
        $this->assertEquals(array('HTTP/1.1 500 Internal Server Error'), OAuth2\AuthServer::getExceptionHttpHeaders('server_error'));
        $this->assertEquals(array('HTTP/1.1 501 Not Implemented'), OAuth2\AuthServer::getExceptionHttpHeaders('unsupported_grant_type'));
        $this->assertEquals(array('HTTP/1.1 400 Bad Request'), OAuth2\AuthServer::getExceptionHttpHeaders('invalid_refresh'));
    }

    public function test_hasGrantType()
    {
        $a = $this->returnDefault();
        $this->assertFalse($a->hasGrantType('test'));
    }

    public function test_addGrantType()
    {
        $a = $this->returnDefault();
        $grant = M::mock('OAuth2\Grant\GrantTypeInterface');
        $grant->shouldReceive('getResponseType')->andReturn('test');
        $a->addGrantType($grant, 'test');

        $this->assertTrue($a->hasGrantType('test'));
    }

    public function test_addGrantType_noIdentifier()
    {
        $a = $this->returnDefault();
        $grant = M::mock('OAuth2\Grant\GrantTypeInterface');
        $grant->shouldReceive('getIdentifier')->andReturn('test');
        $grant->shouldReceive('getResponseType')->andReturn('test');
        $a->addGrantType($grant);

        $this->assertTrue($a->hasGrantType('test'));
    }

    public function test_getScopeDelimeter()
    {
        $a = $this->returnDefault();
        $this->assertEquals(',', $a->getScopeDelimeter());
    }

    public function test_setScopeDelimeter()
    {
        $a = $this->returnDefault();
        $a->setScopeDelimeter(';');
        $this->assertEquals(';', $a->getScopeDelimeter());
    }

    public function test_requireScopeParam()
    {
        $a = $this->returnDefault();
        $a->requireScopeParam(false);

        $reflector = new ReflectionClass($a);
        $requestProperty = $reflector->getProperty('requireScopeParam');
        $requestProperty->setAccessible(true);
        $v = $requestProperty->getValue($a);

        $this->assertFalse($v);
    }

    public function test_scopeParamRequired()
    {
        $a = $this->returnDefault();
        $a->requireScopeParam(false);

        $this->assertFalse($a->scopeParamRequired());
    }

    public function test_setDefaultScope()
    {
        $a = $this->returnDefault();
        $a->setDefaultScope('test.default');

        $reflector = new ReflectionClass($a);
        $requestProperty = $reflector->getProperty('defaultScope');
        $requestProperty->setAccessible(true);
        $v = $requestProperty->getValue($a);

        $this->assertEquals('test.default', $v);
    }

    public function test_getDefaultScope()
    {
        $a = $this->returnDefault();
        $a->setDefaultScope('test.default');
        $this->assertEquals('test.default', $a->getDefaultScope());
    }

    public function test_requireStateParam()
    {
        $a = $this->returnDefault();
        $a->requireStateParam(true);

        $reflector = new ReflectionClass($a);
        $requestProperty = $reflector->getProperty('requireStateParam');
        $requestProperty->setAccessible(true);
        $v = $requestProperty->getValue($a);

        $this->assertTrue($v);
    }

    public function test_getExpiresIn()
    {
        $a = $this->returnDefault();
        $a->setExpiresIn(7200);
        $this->assertEquals(7200, $a->getExpiresIn());
    }

    public function test_setExpiresIn()
    {
        $a = $this->returnDefault();
        $a->setScopeDelimeter(';');
        $this->assertEquals(';', $a->getScopeDelimeter());
    }

    public function test_setRequest()
    {
        $a = $this->returnDefault();
        $request = new OAuth2\Util\Request();
        $a->setRequest($request);

        $reflector = new ReflectionClass($a);
        $requestProperty = $reflector->getProperty('request');
        $requestProperty->setAccessible(true);
        $v = $requestProperty->getValue($a);

        $this->assertTrue($v instanceof OAuth2\Util\RequestInterface);
    }

    public function test_getRequest()
    {
        $a = $this->returnDefault();
        $request = new OAuth2\Util\Request();
        $a->setRequest($request);
        $v = $a->getRequest();

        $this->assertTrue($v instanceof OAuth2\Util\RequestInterface);
    }

    public function test_getStorage()
    {
        $a = $this->returnDefault();
        $this->assertTrue($a->getStorage('session') instanceof OAuth2\Storage\SessionInterface);
    }

    public function test_getGrantType()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $reflector = new ReflectionClass($a);
        $method = $reflector->getMethod('getGrantType');
        $method->setAccessible(true);

        $result = $method->invoke($a, 'authorization_code');

        $this->assertTrue($result instanceof OAuth2\Grant\GrantTypeInterface);
    }

    /**
     * @expectedException        OAuth2\Exception\InvalidGrantTypeException
     * @expectedExceptionCode    9
     */
    public function test_getGrantType_fail()
    {
        $a = $this->returnDefault();
        $a->getGrantType('blah');
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    0
     */
    public function test_issueAccessToken_missingGrantType()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken();
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    7
     */
    public function test_issueAccessToken_badGrantType()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array('grant_type' => 'foo'));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    0
     */
    public function test_issueAccessToken_missingClientId()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code'
        ));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    0
     */
    public function test_issueAccessToken_missingClientSecret()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234
        ));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    0
     */
    public function test_issueAccessToken_missingRedirectUri()
    {
        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234,
            'client_secret' =>  5678
        ));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    8
     */
    public function test_issueAccessToken_badClient()
    {
        $this->client->shouldReceive('getClient')->andReturn(false);

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect'
        ));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    0
     */
    public function test_issueAccessToken_missingCode()
    {
        $this->client->shouldReceive('getClient')->andReturn(array());

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect'
        ));
    }

    /**
     * @expectedException        OAuth2\Exception\ClientException
     * @expectedExceptionCode    9
     */
    public function test_issueAccessToken_badCode()
    {
        $this->client->shouldReceive('getClient')->andReturn(array());
        $this->session->shouldReceive('validateAuthCode')->andReturn(false);

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'code'  =>  'foobar'
        ));
    }

    public function test_issueAccessToken_passedInput()
    {
        $this->client->shouldReceive('getClient')->andReturn(array(
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'name'  =>  'Example Client'
        ));

        $this->session->shouldReceive('validateAuthCode')->andReturn(array(
            'id'    =>  1,
            'scope_ids' =>  '1'
        ));
        $this->session->shouldReceive('updateSession')->andReturn(null);
        $this->session->shouldReceive('removeAuthCode')->andReturn(null);
        $this->session->shouldReceive('associateAccessToken')->andReturn(1);
        $this->session->shouldReceive('associateScope')->andReturn(null);

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $v = $a->issueAccessToken(array(
            'grant_type'    =>  'authorization_code',
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'code'  =>  'foobar'
        ));

        $this->assertArrayHasKey('access_token', $v);
        $this->assertArrayHasKey('token_type', $v);
        $this->assertArrayHasKey('expires', $v);
        $this->assertArrayHasKey('expires_in', $v);

        $this->assertEquals($a->getExpiresIn(), $v['expires_in']);
        $this->assertEquals(time()+$a->getExpiresIn(), $v['expires']);
    }

    public function test_issueAccessToken()
    {
        $this->client->shouldReceive('getClient')->andReturn(array(
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'name'  =>  'Example Client'
        ));

        $this->session->shouldReceive('validateAuthCode')->andReturn(1);
        $this->session->shouldReceive('updateSession')->andReturn(null);
        $this->session->shouldReceive('removeAuthCode')->andReturn(null);
        $this->session->shouldReceive('associateAccessToken')->andReturn(1);

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $_POST['grant_type'] = 'authorization_code';
        $_POST['client_id'] = 1234;
        $_POST['client_secret'] = 5678;
        $_POST['redirect_uri'] = 'http://foo/redirect';
        $_POST['code'] = 'foobar';

        $request = new OAuth2\Util\Request(array(), $_POST);
        $a->setRequest($request);

        $v = $a->issueAccessToken();

        $this->assertArrayHasKey('access_token', $v);
        $this->assertArrayHasKey('token_type', $v);
        $this->assertArrayHasKey('expires', $v);
        $this->assertArrayHasKey('expires_in', $v);

        $this->assertEquals($a->getExpiresIn(), $v['expires_in']);
        $this->assertEquals(time()+$a->getExpiresIn(), $v['expires']);
    }

    public function test_issueAccessToken_customExpiresIn()
    {
        $this->client->shouldReceive('getClient')->andReturn(array(
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'name'  =>  'Example Client'
        ));

        $this->session->shouldReceive('validateAuthCode')->andReturn(1);
        $this->session->shouldReceive('updateSession')->andReturn(null);
        $this->session->shouldReceive('removeAuthCode')->andReturn(null);
        $this->session->shouldReceive('associateAccessToken')->andReturn(1);

        $a = $this->returnDefault();
        $grant = new OAuth2\Grant\AuthCode($a);
        $grant->setExpiresIn(30);
        $a->addGrantType($grant);

        $_POST['grant_type'] = 'authorization_code';
        $_POST['client_id'] = 1234;
        $_POST['client_secret'] = 5678;
        $_POST['redirect_uri'] = 'http://foo/redirect';
        $_POST['code'] = 'foobar';

        $request = new OAuth2\Util\Request(array(), $_POST);
        $a->setRequest($request);

        $v = $a->issueAccessToken();

        $this->assertArrayHasKey('access_token', $v);
        $this->assertArrayHasKey('token_type', $v);
        $this->assertArrayHasKey('expires', $v);
        $this->assertArrayHasKey('expires_in', $v);

        $this->assertNotEquals($a->getExpiresIn(), $v['expires_in']);
        $this->assertNotEquals(time()+$a->getExpiresIn(), $v['expires']);
        $this->assertEquals(30, $v['expires_in']);
        $this->assertEquals(time()+30, $v['expires']);
    }

    public function test_issueAccessToken_HTTP_auth()
    {
        $this->client->shouldReceive('getClient')->andReturn(array(
            'client_id' =>  1234,
            'client_secret' =>  5678,
            'redirect_uri'  =>  'http://foo/redirect',
            'name'  =>  'Example Client'
        ));

        $this->session->shouldReceive('validateAuthCode')->andReturn(1);
        $this->session->shouldReceive('updateSession')->andReturn(null);
        $this->session->shouldReceive('removeAuthCode')->andReturn(null);
        $this->session->shouldReceive('associateAccessToken')->andReturn(1);

        $a = $this->returnDefault();
        $a->addGrantType(new OAuth2\Grant\AuthCode($a));

        $_POST['grant_type'] = 'authorization_code';
        $_SERVER['PHP_AUTH_USER'] = 1234;
        $_SERVER['PHP_AUTH_PW'] = 5678;
        $_POST['redirect_uri'] = 'http://foo/redirect';
        $_POST['code'] = 'foobar';

        $request = new OAuth2\Util\Request(array(), $_POST, array(), array(), $_SERVER);
        $a->setRequest($request);

        $v = $a->issueAccessToken();

        $this->assertArrayHasKey('access_token', $v);
        $this->assertArrayHasKey('token_type', $v);
        $this->assertArrayHasKey('expires', $v);
        $this->assertArrayHasKey('expires_in', $v);

        $this->assertEquals($a->getExpiresIn(), $v['expires_in']);
        $this->assertEquals(time()+$a->getExpiresIn(), $v['expires']);
    }

    public function tearDown() {
        M::close();
    }
}