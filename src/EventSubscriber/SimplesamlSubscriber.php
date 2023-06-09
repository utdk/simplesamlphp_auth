<?php

namespace Drupal\simplesamlphp_auth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Event subscriber subscribing to KernelEvents::REQUEST.
 */
class SimplesamlSubscriber implements EventSubscriberInterface {

  /**
   * The SimpleSAML Authentication helper service.
   *
   * @var \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager
   */
  protected $simplesaml;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * A configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager $simplesaml
   *   The SimpleSAML Authentication helper service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(SimplesamlphpAuthManager $simplesaml, AccountInterface $account, ConfigFactoryInterface $config_factory, LoggerInterface $logger, RouteMatchInterface $route_match) {
    $this->simplesaml = $simplesaml;
    $this->account = $account;
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
    $this->logger = $logger;
    $this->routeMatch = $route_match;
  }

  /**
   * Logs out user if not SAML authenticated and local logins are disabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The subscribed event.
   */
  public function checkAuthStatus(RequestEvent $event) {
    if ($this->account->isAnonymous()) {
      return;
    }

    if (!$this->simplesaml->isActivated()) {
      return;
    }

    if ($this->simplesaml->isAuthenticated()) {
      return;
    }

    if ($this->config->get('allow.default_login')) {

      $allowed_uids = explode(',', $this->config->get('allow.default_login_users'));
      if (in_array($this->account->id(), $allowed_uids)) {
        return;
      }

      $allowed_roles = $this->config->get('allow.default_login_roles');
      if (array_intersect($this->account->getRoles(), $allowed_roles)) {
        return;
      }
    }

    if ($this->config->get('debug')) {
      $this->logger->debug('User %name not authorized to log in using local account.', ['%name' => $this->account->getAccountName()]);
    }
    user_logout();

    $response = new RedirectResponse('/', RedirectResponse::HTTP_FOUND);
    $event->setResponse($response);
    $event->stopPropagation();

  }

  /**
   * Redirect anonymous users to the external IdP from the Drupal login page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The subscribed event.
   */
  public function login_directly_with_external_IdP(RequestEvent $event) {

    if ($this->config->get('allow.default_login')) {
      return;
    }

    // Check if an anonymous user tries to access the Drupal login page.
    if ($this->account->isAnonymous() && $this->routeMatch->getRouteName() == 'user.login') {
      // Get the path (default: '/saml_login') from the
      // 'simplesamlphp_auth.saml_login' route.
      $saml_login_path = Url::fromRoute('simplesamlphp_auth.saml_login')->toString();

      // Redirect directly to the external IdP.
      $response = new RedirectResponse($saml_login_path, RedirectResponse::HTTP_FOUND);
      $event->setResponse($response);
      $event->stopPropagation();

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['checkAuthStatus'];
    $events[KernelEvents::REQUEST][] = ['login_directly_with_external_IdP'];
    return $events;
  }

}
