<?php

namespace Drupal\social_auth_reddit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_reddit\RedditAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Social Auth Reddit module routes.
 */
class RedditAuthController extends ControllerBase {
  /**
   * The network plugin manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private $networkManager;
  /**
   * The user manager.
   *
   * @var \Drupal\social_auth\SocialAuthUserManager
   */
  private $userManager;
  /**
   * The reddit authentication manager.
   *
   * @var \Drupal\social_auth_reddit\RedditAuthManager
   */
  private $redditManager;
  /**
   * Used to access GET parameters.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $request;
  /**
   * The Social Auth Data Handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  private $dataHandler;

  /**
   * RedditAuthController constructor.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Used to get an instance of social_auth_reddit network plugin.
   * @param \Drupal\social_auth\SocialAuthUserManager $user_manager
   *   Manages user login/registration.
   * @param \Drupal\social_auth_reddit\RedditAuthManager $reddit_manager
   *   Used to manage authentication methods.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   Used to access GET parameters.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   SocialAuthDataHandler object.
   */
  public function __construct(NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              RedditAuthManager $reddit_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $data_handler) {
    $this->networkManager = $network_manager;
    $this->userManager = $user_manager;
    $this->redditManager = $reddit_manager;
    $this->request = $request;
    $this->dataHandler = $data_handler;
    // Sets the plugin id.
    $this->userManager->setPluginId('social_auth_reddit');
    // Sets the session keys to nullify if user could not logged in.
    $this->userManager->setSessionKeysToNullify(['access_token', 'oauth2state']);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_reddit.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler')
    );
  }

  /**
   * Response for path 'user/login/reddit'.
   *
   * Redirects the user to Reddit for authentication.
   */
  public function redirectToReddit() {
    /* @var \Rudolf\OAuth2\Client\Provider\Reddit false $reddit */
    $reddit = $this->networkManager->createInstance('social_auth_reddit')->getSdk();

    // If reddit client could not be obtained.
    if (!$reddit) {
      drupal_set_message($this->t('Social Auth Reddit not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Destination parameter specified in url.
    $destination = $this->request->getCurrentRequest()->get('destination');
    // If destination parameter is set, save it.
    if ($destination) {
      $this->userManager->setDestination($destination);
    }

    // Reddit service was returned, inject it to $redditManager.
    $this->redditManager->setClient($reddit);

    // Generates the URL where the user will be redirected for Reddit login.
    // If the user did not have email permission granted on previous attempt,
    // we use the re-request URL requesting only the email address.
    $reddit_login_url = $this->redditManager->getRedditLoginUrl();
    $state = $this->redditManager->getState();
    $this->dataHandler->set('oauth2state', $state);
    return new TrustedRedirectResponse($reddit_login_url);
  }

  /**
   * Response for path 'user/login/reddit/callback'.
   *
   * Reddit returns the user here after user has authenticated in Reddit.
   */
  public function callback() {
    // Checks if user cancel login via Reddit.
    $error = $this->request->getCurrentRequest()->get('error');
    if ($error == 'access_denied') {
      drupal_set_message($this->t('You could not be authenticated.'), 'error');
      return $this->redirect('user.login');
    }

    /* @var \Rudolf\OAuth2\Client\Provider\Reddit|false $reddit */
    $reddit = $this->networkManager->createInstance('social_auth_reddit')->getSdk();
    // If Reddit client could not be obtained.
    if (!$reddit) {
      drupal_set_message($this->t('Social Auth Reddit not configured properly. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }
    $state = $this->dataHandler->get('oauth2state');

    // Retrieves $_GET['state'].
    $retrievedState = $this->request->getCurrentRequest()->query->get('state');
    if (empty($retrievedState) || ($retrievedState !== $state)) {
      $this->userManager->nullifySessionKeys();
      drupal_set_message($this->t('Reddit login failed. Unvalid OAuth2 state.'), 'error');
      return $this->redirect('user.login');
    }

    // Saves access token to session.
    $this->dataHandler->set('access_token', $this->redditManager->getAccessToken());
    $this->redditManager->setClient($reddit)->authenticate();
    // Gets user's info from Reddit API.
    if (!$reddit_profile = $this->redditManager->getUserInfo()) {
      drupal_set_message($this->t('Reddit login failed, could not load Reddit profile. Contact site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Store the data mapped with data points define is
    // social_auth_reddit settings.
    $data = [];
    if (!$this->userManager->checkIfUserExists($reddit_profile['id'])) {
      $api_calls = explode(PHP_EOL, $this->redditManager->getApiCalls());
      // Iterate through api calls define in settings and try to retrieve them.
      foreach ($api_calls as $api_call) {
        $call = $this->redditManager->getExtraDetails($api_call);
        array_push($data, $call);
      }
    }

    // If user information could be retrieved.
    return $this->userManager->authenticateUser($reddit_profile['name'], '', $reddit_profile['id'], $this->redditManager->getAccessToken(), $reddit_profile['icon_img'], json_encode($data));
  }

}
