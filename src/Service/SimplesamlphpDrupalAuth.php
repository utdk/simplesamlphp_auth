<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth.
 */

namespace Drupal\simplesamlphp_auth\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Psr\Log\LoggerInterface;
use Drupal\externalauth\ExternalAuthInterface;

class SimplesamlphpDrupalAuth {

  /**
   * SimpleSAMLphp Authentication helper
   *
   * @var SimplesamlphpAuthManager
   */
  protected $simplesaml_auth;

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The External Authentication service.
   *
   * @var \Drupal\externalauth\ExternalAuth
   */
  protected $externalauth;

  /**
   * @param SimplesamlphpAuthManager $simplesaml_auth
   * @param ConfigFactoryInterface $config_factory
   * @param EntityManagerInterface $entityManager
   * @param LoggerInterface $logger
   * @param ExternalAuthInterface $externalauth
   */
  public function __construct(SimplesamlphpAuthManager $simplesaml_auth, ConfigFactoryInterface $config_factory, EntityManagerInterface $entityManager, LoggerInterface $logger, ExternalAuthInterface $externalauth) {
    $this->simplesaml_auth = $simplesaml_auth;
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
    $this->entityManager = $entityManager;
    $this->logger = $logger;
    $this->externalauth = $externalauth;
  }

  /**
   * Logs in and optionally registers a Drupal user based on the authname provided.
   *
   * @param $authname
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  public function externalLoginRegister($authname) {
    $account = $this->externalauth->login($authname, 'simplesamlphp_auth');
    if (!$account) {
      $account = $this->externalRegister($authname);
    }

    // Determine if roles should be evaluated upon login.
    if ($this->config->get('role.eval_every_time')) {
      $this->roleMatchAdd($account);
    }

    return $account;
  }

  /**
   * Registers a user locally as one authenticated by the SimpleSAML IdP.
   *
   * @param $authname
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Exception
   */
  public function externalRegister($authname) {

    // First we check the admin settings for simpleSAMLphp and find out if we
    // are allowed to register users.
    if (!$this->config->get('register_users')) {

      // We are not allowed to register new users on the site through simpleSAML.
      // We let the user know about this and redirect to the user/login page.
      drupal_set_message(t("We are sorry. While you have successfully authenticated, you are not yet entitled to access this site. Please ask the site administrator to provision access for you."));
      $this->simplesaml_auth->logout(base_path());

      return FALSE;
    }

    // It's possible that a user with their username set to this authname
    // already exists in the Drupal database, but is not permitted to login to
    // Drupal via SAML. If so, log out of SAML and redirect to the front page.
    if ($this->entityManager->getStorage('user')->loadByProperties(array('name' => $authname))) {
      drupal_set_message(t('We are sorry, your user account is not SAML enabled.'));
      $this->simplesaml_auth->logout(base_path());

      return FALSE;
    }

    // Create the new user.
    $account = $this->externalauth->register($authname, 'simplesamlphp_auth');
    $this->synchronizeUserAttributes($account, TRUE);
    return $this->externalauth->userLoginFinalize($account);
  }

  /**
   * Synchronizes user data if enabled.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param bool $force
   *   Define whether to force syncing of the user attributes, regardless of
   *   SimpleSAMLphp settings.
   */
  public function synchronizeUserAttributes(AccountInterface $account, $force = FALSE) {
    $sync_mail = $force || $this->config->get('sync.mail');
    $sync_user_name = $force || $this->config->get('sync.user_name');

    try {
      if ($sync_user_name) {
        $name = $this->simplesaml_auth->getDefaultName();
        $account->setUsername($name);
      }

      if ($sync_mail) {
        $mail = $this->simplesaml_auth->getDefaultEmail();
        $account->setEmail($mail);
      }
    } catch (Exception $e) {
      drupal_set_message(t('Your user name was not provided by your identity provider (IDP).'), "error");
      $this->logger->critical($e->getMessage());
    }

    if ($sync_mail || $sync_user_name) {
      $account->save();
    }
  }

  /**
   * Adds roles to user accounts.
   *
   * @param UserInterface $account
   */
  public function roleMatchAdd(UserInterface $account) {
    // Get matching roles based on retrieved SimpleSAMLphp attributes
    $matching_roles = $this->getMatchingRoles();

    if ($matching_roles) {
      foreach ($matching_roles as $role_id) {
        $this->logger->notice('Adding role %role to user %name', array(
          '%role' => $role_id,
          '%name' => $account->getAccountName(),
        ));
        $account->addRole($role_id);
      }
      $account->save();
    }
  }

  /**
   * Get matching user roles to assign to user, based on retrieved
   * SimpleSAMLphp attributes.
   *
   * @return array
   */
  public function getMatchingRoles() {
    $roles = array();
    // Obtain the role map stored. The role map is a concatenated string of
    // rules which, when SimpleSAML attributes on the user match, will add
    // roles to the user.
    // The full role map string, when mapped to the variables below, presents
    // itself thus:
    // $role_id:$key,$op,$value;$key,$op,$value;$key,$op,$value|$role_id:$key,$op,$value... etc
    if ($rolemap = $this->config->get('role.population')) {

      foreach (explode('|', $rolemap) as $rolerule) {
        list($role_id, $role_eval) = explode(':', $rolerule, 2);

        foreach (explode(';', $role_eval) as $role_eval_part) {
          if ($this->evalRoleRule($role_eval_part)) {
            $roles[$role_id] = $role_id;
          }
        }
      }
    }

    $attributes = $this->simplesaml_auth->getAttributes();
    \Drupal::modulehandler()->alter('simplesamlphp_auth_user_roles', $roles, $attributes);
    return $roles;
  }

  /**
   * Determines whether a role should be added to an account.
   *
   * @param $role_eval_part
   * @return bool
   */
  protected function evalRoleRule($role_eval_part) {
    list($key, $op, $value) = explode(',', $role_eval_part);

    $attributes = $this->simplesaml_auth->getAttributes();
    if (!array_key_exists($key, $attributes)) {
      return FALSE;
    }
    $attribute = $attributes[$key];

    // A '=' requires the $value exactly matches the $attribute, A '@='
    // requires the portion after a '@' in the $attribute to match the
    // $value and a '~=' allows the value to match any part of any
    // element in the $attribute array.
    switch ($op) {
      case '=':
        return in_array($value, $attribute);

      case '@=':
        list($before, $after) = explode('@', array_shift($attribute));
        return ($after == $value);

      case '~=':
        return array_filter($attribute, function($subattr) use ($value) {
          return strpos($subattr, $value) !== FALSE;
        });
    }
  }

}
