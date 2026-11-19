<?php

namespace HamCQ\QQAuth\Api\Controllers;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\ArrayAccessorTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use HamCQ\QQAuth\Api\Controllers\QQResourceController;
use HamCQ\QQAuth\QQIdentity;
use GuzzleHttp\Exception\BadResponseException;

class QQController extends AbstractProvider
{
  use ArrayAccessorTrait;

  protected $clientId;
  protected $secret;
  protected $redirect_uri;

  public $openid;
 

  const BASE_AUTH_URL = 'https://graph.qq.com';

  /**
   * Get authorization url to begin OAuth flow
   *
   * @return string
   */
  public function getBaseAuthorizationUrl()
  {
    return self::BASE_AUTH_URL . '/oauth2.0/authorize';
  }

  protected function getAuthorizationParameters(array $options)
  {
    $options['client_id'] = $this->clientId;

    if (!isset($options['redirect_uri'])) {
      $options['redirect_uri'] = $this->redirect_uri;
    }

    $options += [
      'response_type' => 'code'
    ];

    if (empty($options['scope'])) {
      $options['scope'] = 'get_user_info';
    }

    if (empty($options['state'])) {
      $options['state'] = $this->getRandomState();
    }

    // Store the state as it may need to be accessed later on.
    $this->state = $options['state'];

    return $options;
  }

  /**
   * Get access token url to retrieve token
   * @param array $params
   * @return string
   */
  public function getBaseAccessTokenUrl(array $params)
  {
    return self::BASE_AUTH_URL . '/oauth2.0/token';
  }

  public function getAccessToken($grant, array $options = [])
  {
    $grant = $this->verifyGrant($grant);
    $params = [
      'client_id'     => $this->clientId,
      'client_secret' => $this->secret,
      'redirect_uri'  => $this->redirect_uri,
      'grant_type' => 'authorization_code',
      'fmt' => 'json',
    ];

    $params   = $grant->prepareRequestParameters($params, $options);
    $request  = $this->getAccessTokenRequest($params);
    $response = $this->getParsedResponse($request);
    $prepared = $this->prepareAccessTokenResponse($response);

    return $this->createAccessToken($prepared, $grant);
  }

  protected function createAccessToken(array $response, AbstractGrant $grant)
  {
        return new AccessToken($response);
  }

  protected function getOpenidUrl(AccessToken $token, bool $withUnionId = true)
  {
    $params = ['access_token' => $token->getToken(), 'fmt' => 'json'];
    if ($withUnionId) $params['unionid'] = 1;
    return self::BASE_AUTH_URL . '/oauth2.0/me?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  }

  public function fetchOpenId(AccessToken $token)
  {
    try {
      $request = $this->getAuthenticatedRequest(self::METHOD_GET, $this->getOpenidUrl($token), $token);
      $identity = $this->getSpecificResponse($request);
    } catch (IdentityProviderException $error) {
      if ($error->getCode() !== 100048) throw $error;
      // UnionID permission is optional; keep legacy OpenID login available.
      $request = $this->getAuthenticatedRequest(self::METHOD_GET, $this->getOpenidUrl($token, false), $token);
      $identity = $this->getSpecificResponse($request);
    }
    if (!isset($identity['client_id']) || !is_scalar($identity['client_id']) || (string) $identity['client_id'] !== (string) $this->clientId) {
      throw new \UnexpectedValueException('QQ identity response belongs to a different application');
    }
    $identity['openid'] = QQIdentity::openId($identity['openid'] ?? null);
    $identity['unionid'] = QQIdentity::unionId($identity['unionid'] ?? null);
    return $identity;
  }

  protected function getSpecificResponse(RequestInterface $request)
  {
    try {
      $response = $this->getResponse($request);
    } catch (BadResponseException $error) {
      $response = $error->getResponse();
    }
    $parsed   = $this->parseSpecificResponse($response);
    $this->checkResponse($response, $parsed);
    if ($response->getStatusCode() >= 400) {
      throw new IdentityProviderException('QQ API HTTP request failed', $response->getStatusCode(), []);
    }
    return $parsed;
  }

  protected function parseSpecificResponse(ResponseInterface $response)
  {
    $content = (string) $response->getBody();
    $parsed = json_decode($content, true);
    if (!is_array($parsed) && preg_match('/\A\s*callback\s*\(\s*(\{.*\})\s*\)\s*;?\s*\z/s', $content, $matches)) {
      $parsed = json_decode($matches[1], true);
    }
    if (!is_array($parsed)) throw new \UnexpectedValueException('Invalid QQ API response');
    return $parsed;
  }

  /**
   * Get provider url to fetch user details
   *
   * @param AccessToken $token
   * @return string
   * @throws IdentityProviderException
   */
  public function getResourceOwnerDetailsUrl(AccessToken $token, string $openId = "")
  {
    $access_token = $token->getToken();
    $url = sprintf("%s/user/get_user_info?access_token=%s&oauth_consumer_key=%s&openid=%s", 
      self::BASE_AUTH_URL, $access_token, $this->clientId, $openId);

    $request = $this->getAuthenticatedRequest(self::METHOD_GET, $url, $access_token);
    return  $this->getSpecificResponse($request);
  }

  protected function getDefaultScopes()
  {
      return ['get_user_info'];
  }

  protected function checkResponse(ResponseInterface $response, $data)
  {
    if (isset($data['error']) || (isset($data['ret']) && (int) $data['ret'] !== 0)) {
      $code = (int) ($data['error'] ?? $data['ret']);
      throw new IdentityProviderException('QQ API request failed', $code, ['error' => $code]);
    }
  }

  protected function createResourceOwner(array $response, AccessToken $token)
  {
      return new QQResourceController($response);
  }
  
}
