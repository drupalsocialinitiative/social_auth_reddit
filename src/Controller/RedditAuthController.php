<?php

namespace Drupal\social_auth_reddit\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\Controller\SocialAuthOAuth2ControllerBase;
use Drupal\social_auth\SocialAuthDataHandler;
use Drupal\social_auth\SocialAuthUserManager;
use Drupal\social_auth_reddit\RedditAuthManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Social Auth Reddit routes.
 */
class RedditAuthController extends SocialAuthOAuth2ControllerBase {

  /**
   * RedditAuthController constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
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
  public function __construct(MessengerInterface $messenger,
                              NetworkManager $network_manager,
                              SocialAuthUserManager $user_manager,
                              RedditAuthManager $reddit_manager,
                              RequestStack $request,
                              SocialAuthDataHandler $data_handler) {

    parent::__construct('Social Auth Reddit', 'social_auth_reddit', $messenger, $network_manager, $user_manager, $reddit_manager, $request, $data_handler);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('plugin.network.manager'),
      $container->get('social_auth.user_manager'),
      $container->get('social_auth_reddit.manager'),
      $container->get('request_stack'),
      $container->get('social_auth.data_handler')
    );
  }

  /**
   * Response for path 'user/login/reddit/callback'.
   *
   * Reddit returns the user here after user has authenticated.
   */
  public function callback() {
    // Checks if authentication failed.
    if ($this->request->getCurrentRequest()->query->has('error')) {
      $this->messenger->addError('You could not be authenticated.');

      return $this->redirect('user.login');
    }

    /* @var array|null $profile */
    $profile = $this->processCallback();

    // If authentication was successful.
    if ($profile !== NULL) {

      // Gets (or not) extra initial data.
      $data = $this->userManager->checkIfUserExists($profile['id']) ? NULL : $this->providerManager->getExtraDetails();

      // If user information could be retrieved.
      return $this->userManager->authenticateUser($profile['name'], '', $profile['id'], $this->providerManager->getAccessToken(), $profile['icon_img'], $data);
    }

    return $this->redirect('user.login');
  }

}
