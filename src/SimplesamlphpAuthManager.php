<?php

namespace Drupal\simplesamlphp_auth;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\UserInterface;

class SimplesamlphpAuthManager {

  protected $connection;
  protected $config;
  protected $instance;
  protected $simplesamlConfig;

  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection) {
    $this->configFactory = $config_factory;
    $this->connection = $connection;
    $this->config = $this->configFactory->get('simplesamlphp_auth.settings');

    $auth_source = $this->config->get('auth_source');
    $simplesamlphp_location = $this->config->get('install_dir');

    if (file_exists($simplesamlphp_location . '/lib/_autoload.php')) {
      require_once $simplesamlphp_location . '/lib/_autoload.php';
      try {
        $this->instance = new \SimpleSAML_Auth_Simple($auth_source);
        $this->simplesamlConfig = \SimpleSAML_Configuration::getInstance();
      } catch (Exception $e) {
        throw new \Exception('Unable to load SimpleSAML.');

      }
    }

    return FALSE;
  }

  public function getStorage() {
    return $this->simplesamlConfig->getValue('store.type');
  }

  public function hasSimplesaml() {
    if ($this->instance !== FALSE) {
      return TRUE;
    }

    return FALSE;
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
      return FALSE;
    }
  }

  public function externalLogin(UserInterface $account) {

    // See if we're supposed to re-evaluate role assignments.
//    if ($this->config->get('role.eval_every_time')) {
      // Populate roles based on configuration setting.
//      _simplesaml_auth_debug(t('User already registered [%authname] updating roles.', array('%authname' => $ext_user->name)));
//      $roles = _simplesamlphp_auth_rolepopulation(variable_get('simplesamlphp_auth_rolepopulation', ''));
//      $userinfo = array('roles' => $roles);

      // Save the updated roles and populate the user object.
//      $user = user_save($ext_user, $userinfo);
//    }
//    else {
//      // No need to evaluate roles, populate the user object.
//      $user = $ext_user;
//    }

    // Finalizing the login, calls hook_user op login.
    user_login_finalize($account);

//    _simplesaml_auth_debug(t('Registered [%authname] with uid @uid', array(
//      '%authname' => $authname,
//      '@uid' => $user->uid
//    )));

//    if ($user) {
    // Populate roles based on configuration setting.
//      $roles = _simplesamlphp_auth_rolepopulation(variable_get('simplesamlphp_auth_rolepopulation', ''));
//      $userinfo = array('roles' => $roles);
    // @todo - Fjernet rolle-delen her da den gav en bra feilmelding når roller ikke finnes.
    // Removed role-part here as it provided a good error when roles are not.
//      $user = user_save($user, $userinfo);

//      return $user;
//    }
//  }
//    else {
    // We are not allowed to register new users on the site through simpleSAML.
    // We let the user know about this and redirect to the user/login page.
//      drupal_set_message(t("We are sorry. While you have successfully authenticated, you are not yet entitled to access this site. Please ask the site administrator to provision access for you."));
//      $this->instance(base_url());
//}
//    }
  }


  public function externalRegister($name) {

    // First we check the admin settings for simpleSAMLphp and find out if we
    // are allowed to register users.
    if (!$this->config->get('register_users')) {
      // We are not allowed to register new users on the site through simpleSAML.
      // We let the user know about this and redirect to the user/login page.
      drupal_set_message(t("We are sorry. While you have successfully authenticated, you are not yet entitled to access this site. Please ask the site administrator to provision access for you."));
      $this->instance->logout(base_path());
    }

    // We are allowed to register new users.
//      _simplesaml_auth_debug(t('Register [%authname]', array('%authname' => $authname)));

    // It's possible that a user with their username set to this authname
    // already exists in the Drupal database, but is not permitted to login to
    // Drupal via SAML. If so, log out of SAML and redirect to the front page.
    $existing_user = user_load_by_name($name);
    if ($existing_user) {
//        _simplesaml_auth_debug(t('User [%authname] could not be registered because that username already exists and is not SAML enabled.', array(
//          '%authname' => $authname,
//        )));

      drupal_set_message(t('We are sorry, your user account is not SAML enabled.'));
      $this->instance->logout(base_path());

      return FALSE;
    }

    // @TODO do we need this check even?
    $account = $this->externalLoad($name);
    if (!$account) {
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

      // Terminate if an error occurred during $account->save().
      if (!$account) {
        drupal_set_message(t("Error saving user account."), 'error');

        return FALSE;
      }

      $this->connection->merge('simplesamlphp_auth_authmap')
        ->keys(array(
          'uid' => $account->id(),
        ))
        ->fields(array('authname' => $name))
        ->execute();

//      if ($user) {
//        // Populate roles based on configuration setting.
//        $roles = _simplesamlphp_auth_rolepopulation(variable_get('simplesamlphp_auth_rolepopulation', ''));
//        $userinfo = array('roles' => $roles);
//        // @todo - Fjernet rolle-delen her da den gav en bra feilmelding når roller ikke finnes.
//        $user = user_save($user, $userinfo);
//
//        return $user;
//      }

    }

    return $account;
  }


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

  /**
   * Gets the authname attribute from the SAML assertion.
   *
   * @return string
   *   The authname attribute.
   *
   * @throws Exception
   *   Throws an exception if no valid unique id attribute is set in SAML session.
   */


  protected function getAttribute($attribute) {
    $attributes = $this->instance->getAttributes();

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

  public function isActivated() {
    if ($this->config->get('activate')) {
      return TRUE;
    }

    return FALSE;
  }

}
