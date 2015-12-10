<?php

namespace Drupal\simplesamlphp_auth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SimplesamlSubscriber implements EventSubscriberInterface {

  /**
   * @var SimplesamlphpAuthManager
   */
  protected $simplesaml;

  /**
   * @var AccountInterface
   */
  protected $account;

  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @param SimplesamlphpAuthManager $simplesaml
   * @param AccountInterface $account
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(SimplesamlphpAuthManager $simplesaml, AccountInterface $account, ConfigFactoryInterface $config_factory) {
    $this->simplesaml = $simplesaml;
    $this->account = $account;
    $this->configFactory = $config_factory;
  }

  /**
   * Logs out user if not SAML authenticated and local logins are disabled.
   *
   * @param GetResponseEvent $event
   */
  public function checkAuthStatus(GetResponseEvent $event) {
    if ($this->account->isAnonymous()) {
      return;
    }

    if (!$this->simplesaml->isActivated()) {
      return;
    }

    $this->simplesaml->load();
    if ($this->simplesaml->isAuthenticated()) {
      return;
    }

    $config = $this->configFactory->get('simplesamlphp_auth.settings');

    if ($config->get('allow.default_login')) {

      $allowed_uids = explode(',', $config->get('allow.default_login_users'));
      if (in_array($this->account->id(), $allowed_uids)) {
        return;
      }

      $allowed_roles = $config->get('allow.default_login_roles');
      if (array_intersect($this->account->getRoles(), $allowed_roles)) {
        return;
      }
    }

    \Drupal::logger('simplesamlphp_auth')->notice('User %name not authorized to log in using local account.', array('%name' => $this->account->getAccountName()));
    user_logout();

    $response = new RedirectResponse('/', RedirectResponse::HTTP_FOUND);
    $event->setResponse($response);
    $event->stopPropagation();

  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkAuthStatus');
    return $events;
  }

}
