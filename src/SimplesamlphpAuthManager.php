<?php

namespace Drupal\simplesamlphp_auth;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use SimpleSAML_Auth_Simple;
use SimpleSAML_Configuration;


class SimplesamlphpAuthManager {

  /**
   * The connection object used for this data.
   *
   * @var \Drupal\Core\Database\Connection $connection
   */
  protected $connection;

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  protected $simplesamlConfig;

  public $instance;

  /**
   * @param ConfigFactoryInterface $config_factory
   * @param Connection $connection
   * @param LoggerInterface $logger
   * @throws \Exception
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection, LoggerInterface $logger) {
    $this->connection = $connection;
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
    $this->logger = $logger;

  }

  public function load() {
    $auth_source = $this->config->get('auth_source');
    $this->instance = new SimpleSAML_Auth_Simple($auth_source);
    $this->simplesamlConfig = \SimpleSAML_Configuration::getInstance();
  }

  public function getStorage() {
    return $this->simplesamlConfig->getValue('store.type');
  }

  public function externalAuthenticate() {
    $this->instance->requireAuth();
  }

  public function isAuthenticated() {
    return $this->instance->isAuthenticated();
  }

  public function externalLoad($authname) {
    $uid = $this->connection->query("SELECT uid FROM {simplesamlphp_auth_authmap} WHERE authname = :authname", array(':authname' => $authname))
      ->fetchField();

    if ($uid) {
      return entity_load('user', $uid);
    }
    else {
      return $this->externalRegister($authname);
    }
  }

  public function externalRegister($name) {

    // First we check the admin settings for simpleSAMLphp and find out if we
    // are allowed to register users.
    if (!$this->config->get('register_users')) {

      // We are not allowed to register new users on the site through simpleSAML.
      // We let the user know about this and redirect to the user/login page.
      drupal_set_message(t("We are sorry. While you have successfully authenticated, you are not yet entitled to access this site. Please ask the site administrator to provision access for you."));
      $this->instance->logout(base_path());

      return FALSE;
    }

    // It's possible that a user with their username set to this authname
    // already exists in the Drupal database, but is not permitted to login to
    // Drupal via SAML. If so, log out of SAML and redirect to the front page.
    if (user_load_by_name($name)) {
      drupal_set_message(t('We are sorry, your user account is not SAML enabled.'));
      $this->instance->logout(base_path());

      return FALSE;
    }

    // Create the new user.
    $account = entity_create('user', array(
      'name' => $name,
      'pass' => user_password(),
      'init' => $name,
      'status' => 1,
      'access' => REQUEST_TIME
    ));
    $account->enforceIsNew();
    $account->save();

    $this->connection->merge('simplesamlphp_auth_authmap')
      ->keys(array(
        'uid' => $account->id(),
      ))
      ->fields(array('authname' => $name))
      ->execute();

    return $account;
  }

  public function externalLogin(UserInterface $account) {

    // Determine if roles should be evaluated upon login.
    if ($this->config->get('role.eval_every_time')) {
      $this->roleMatchAdd($account);
    }

    // Finalizing the login, calls hook_user op login.
    user_login_finalize($account);

  }

  public function roleMatchAdd(UserInterface $account) {

    // Obtain the role map stored. The role map is a concatenated string of
    // rules which, when SimpleSAML attributes on the user match, will add
    // roles to the user.
    // The full role map string, when mapped to the variables below, presents
    // itself thus:
    // $role_id:$key,$op,$value;$key,$op,$value;$key,$op,$value|$role_id:$key,$op,$value... etc
    if ($rolemap = $this->config->get('role.population')) {

      foreach (explode('|', $rolemap) as $rolerule) {
        list($role_id, $role_eval) = explode(':', $rolerule);

        foreach (explode(';', $role_eval) as $role_eval_part) {
          if ($this->evalRoleRule($role_eval_part)) {
            $account->addRole($role_id);
          }
        }
      }
      $account->save();
    }
  }

  protected function evalRoleRule($role_eval_part) {
    list($key, $op, $value) = explode(',', $role_eval_part);

    $attributes = $this->getAttributes();
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

  /**
   * @return string
   * @throws \Exception
   */
  public function getAuthname() {
    return $this->getAttribute($this->config->get('unique_id'));
  }

  /**
   * Gets the name attribute.
   *
   * @return
   *   The name attribute.
   */
  public function getDefaultName() {
    return $this->getAttribute($this->config->get('user_name'));
  }

  /**
   * Gets the mail attribute.
   *
   * @return
   *   The mail attribute.
   */
  public function getDefaultEmail() {
    return $this->getAttribute($this->config->get('mail_attr'));
  }

  protected function getAttributes() {
    return $this->instance->getAttributes();
  }

  protected function getAttribute($attribute) {
    $attributes = $this->getAttributes();

    if (isset($attributes)) {
      if (empty($attributes[$attribute][0])) {
        throw new \Exception(t('Error in simplesamlphp_auth.module: no valid %attribute attribute set.',
          array(
            '%attribute' => $attribute,
          )
        ));
      }

      return $attributes[$attribute][0];
    }

    return FALSE;
  }

  /**
   * Checks if SimpleSAMLphp_auth is enabled.
   *
   * @return bool
   */
  public function isActivated() {
    return $this->config->get('activate');
  }

}
