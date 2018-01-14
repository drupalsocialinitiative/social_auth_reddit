<?php

namespace Drupal\social_auth_reddit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\social_auth\Form\SocialAuthSettingsForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for Social Auth Reddit.
 */
class RedditAuthSettingsForm extends SocialAuthSettingsForm {
  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   Used to check if route exists.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   Used to check if path is valid and exists.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   Holds information about the current request.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RouteProviderInterface $route_provider, PathValidatorInterface $path_validator, RequestContext $request_context) {
    parent::__construct($config_factory, $route_provider, $path_validator);
    $this->requestContext = $request_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this class.
    return new static(
    // Load the services required to construct this class.
      $container->get('config.factory'),
      $container->get('router.route_provider'),
      $container->get('path.validator'),
      $container->get('router.request_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'social_auth_reddit_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array_merge(
      parent::getEditableConfigNames(),
      ['social_auth_reddit.settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('social_auth_reddit.settings');
    $form['reddit_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Reddit Client settings'),
      '#open' => TRUE,
      '#description' => $this->t('You need to first create a Reddit App at <a href="@reddit-dev">@reddit-dev</a> by clicking "are you a developer? create an app..."', ['@reddit-dev' => 'https://www.reddit.com/prefs/apps']),
    ];
    $form['reddit_settings']['client_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#description' => $this->t('Copy the Client ID here.'),
    ];
    $form['reddit_settings']['client_secret'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
      '#description' => $this->t('Copy the Client Secret here.'),
    ];
    $form['reddit_settings']['authorized_redirect_url'] = [
      '#type' => 'textfield',
      '#disabled' => TRUE,
      '#title' => $this->t('Authorized redirect URIs'),
      '#description' => $this->t('Copy this value to <em>Authorized redirect URIs</em> field of your Reddit App settings.'),
      '#default_value' => $GLOBALS['base_url'] . '/user/login/reddit/callback',
    ];
    $form['reddit_settings']['scopes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scopes for API call'),
      '#default_value' => $config->get('scopes'),
      '#description' => $this->t('Define additional requested scopes to make API calls. A full list of valid scopes and their description is available in the <a href="https://www.drupal.org/node/2935597">Social Auth Reddit guide</a>.'),
    ];
    $form['reddit_settings']['api_calls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('API calls to be made to collect data'),
      '#default_value' => $config->get('api_calls'),
      '#description' => $this->t('Define the API calls which will retrieve data from provider.'),
    ];
    $form['reddit_settings']['user_agent_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Agent String'),
      '#default_value' => $config->get('user_agent_string'),
      '#description' => $this->t("Enter the user agent string to be used. The format is <code> drupal:social_auth_reddit:{app_version} (by /u/{user})</code> , where you need to replace {app_version} and {user} by your Reddit App version and the app creator's Reddit username respectively."),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Convert the string of space-separated scopes into an array.
    $scopes = explode(" ", $form_state->getValue('scopes'));

    // Define the list of valid scopes.
    $valid_scopes = ['', 'creddits', 'modcontributors', 'modmail', 'modconfig', 'subscribe', 'structuredstyles', 'vote', 'wiki', 'mysubreddits', 'submit', 'modlog', 'modposts', 'modflair', 'save', 'modothers', 'read', 'privatemessages', 'report', 'identity', 'livemanage', 'account', 'modtraffic', 'wikiread', 'edit', 'modwiki', 'modself', 'history', 'flair'];

    // Check if input contains any invalid scopes.
    for ($i = 0; $i < count($scopes); $i++) {
      if (!in_array($scopes[$i], $valid_scopes, TRUE)) {
        $contains_invalid_scope = TRUE;
      }
    }
    if (isset($contains_invalid_scope)) {
      $form_state->setErrorByName('scope', t('You have entered an invalid scope. Please check and try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->config('social_auth_reddit.settings')
      ->set('client_id', trim($values['client_id']))
      ->set('client_secret', trim($values['client_secret']))
      ->set('scopes', $values['scopes'])
      ->set('api_calls', $values['api_calls'])
      ->set('user_agent_string', $values['user_agent_string'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
