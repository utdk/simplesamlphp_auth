<?php

namespace Drupal\simplesamlphp_auth\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Event subscriber subscribing to ExternalAuthEvents.
 */
class SimplesamlExternalauthSubscriber implements EventSubscriberInterface {

  /**
   * The SimpleSAML Authentication helper service.
   *
   * @var \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager
   */
  protected $simplesaml;

  /**
   * The SimpleSAML Drupal Authentication service.
   *
   * @var \Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth
   */
  public $simplesamlDrupalauth;

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
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager $simplesaml
   *   The SimpleSAML Authentication helper service.
   * @param \Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth $simplesaml_drupalauth
   *   The SimpleSAML Drupal Authentication service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(SimplesamlphpAuthManager $simplesaml, SimplesamlphpDrupalAuth $simplesaml_drupalauth, ConfigFactoryInterface $config_factory, LoggerInterface $logger, ModuleHandlerInterface $module_handler) {
    $this->simplesaml = $simplesaml;
    $this->simplesamlDrupalauth = $simplesaml_drupalauth;
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
  }

  /**
   * React on an ExternalAuth login event.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The subscribed event.
   */
  public function externalauthLogin(ExternalAuthLoginEvent $event) {
    if ($event->getProvider() == "simplesamlphp_auth") {

      if (!$this->simplesaml->isActivated()) {
        return;
      }

      if (!$this->simplesaml->isAuthenticated()) {
        return;
      }

      $account = $event->getAccount();
      $this->simplesamlDrupalauth->synchronizeUserAttributes($account);

      // Invoke a hook to let other modules alter the user account based on
      // SimpleSAMLphp attributes.
      $account_altered = FALSE;
      $attributes = $this->simplesaml->getAttributes();
      $this->moduleHandler->invokeAllWith('simplesamlphp_auth_user_attributes', function (callable $hook, string $module) use (&$attributes, &$account, &$account_altered) {
        $return_value = $hook($account, $attributes);
        if ($return_value instanceof UserInterface) {
          if ($this->config->get('debug')) {
            $this->logger->debug('Drupal user attributes have altered based on SAML attributes by %module module.', [
              '%module' => $module,
            ]);
          }
          $account_altered = TRUE;
          $account = $return_value;
        }
      });

      if ($account_altered) {
        $account->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ExternalAuthEvents::LOGIN][] = ['externalauthLogin'];
    return $events;
  }

}
