<?php

namespace Drupal\social_auth_reddit;

use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\Core\Config\ConfigFactory;

/**
 * Contains all the logic for Reddit login integration.
 */
class RedditAuthManager extends OAuth2Manager {

  /**
   * The Reddit client object.
   *
   * @var \Rudolf\OAuth2\Client\Provider\Reddit
   */
  protected $client;
  /**
   * The Reddit user.
   *
   * @var \Rudolf\OAuth2\Client\Grant\InstalledClient
   */
  protected $user;
  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;
  /**
   * Social Auth Reddit Settings.
   *
   * @var array
   */
  protected $settings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Used for accessing configuration object factory.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->settings = $configFactory->getEditable('social_auth_reddit.settings');
  }

  /**
   * Authenticates the users by using the access token.
   */
  public function authenticate() {
    $this->setAccessToken($this->client->getAccessToken('authorization_code',
      ['code' => $_GET['code']]));
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @return \Rudolf\OAuth2\Client\Grant\InstalledClient
   *   User info returned by the Reddit.
   */
  public function getUserInfo() {
    $this->user = $this->client->getResourceOwner($this->getAccessToken());
    return $this->user;
  }

  /**
   * Gets the data by using the access token returned.
   *
   * @param string $url
   *   The API call url.
   *
   * @return string
   *   Data returned by API call.
   */
  public function getExtraDetails($url) {
    if ($url) {
      $httpRequest = $this->client->getAuthenticatedRequest('GET', $url, $this->getAccessToken(), []);
      $data = $this->client->getResponse($httpRequest);
      return json_decode($data->getBody(), TRUE);
    }
    return FALSE;
  }

  /**
   * Returns the Reddit login URL where user will be redirected.
   *
   * @return string
   *   Absolute Reddit login URL where user will be redirected.
   */
  public function getRedditLoginUrl() {

    $login_url = $this->client->getAuthorizationUrl();
    // Generate and return the URL where we should redirect the user.
    return $login_url;
  }

  /**
   * Returns the Reddit login URL where user will be redirected.
   *
   * @return string
   *   Absolute Reddit login URL where user will be redirected
   */
  public function getState() {
    $state = $this->client->getState();
    // Generate and return the URL where we should redirect the user.
    return $state;
  }

  /**
   * Gets the API calls to collect data.
   *
   * @return string
   *   Comma-separated API calls.
   */
  public function getApiCalls() {
    return $this->settings->get('api_calls');
  }

}
