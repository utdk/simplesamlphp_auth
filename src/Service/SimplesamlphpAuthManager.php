<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager.
 */

namespace Drupal\simplesamlphp_auth\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use SimpleSAML_Auth_Simple;
use SimpleSAML_Configuration;


class SimplesamlphpAuthManager {

  /**
   * The Drupal configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * A SimpleSAML configuration instance.
   *
   * @var \SimpleSAML_Configuration
   */
  protected $simplesamlConfig;

  /**
   * A SimpleSAML instance.
   *
   * @var \SimpleSAML_Auth_Simple
   */
  public $instance;

  /**
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
  }

  /**
   * Loads the SimpleSAML instance and configuration.
   *
   * @throws \Exception
   */
  public function load() {
    $auth_source = $this->config->get('auth_source');
    $this->instance = new SimpleSAML_Auth_Simple($auth_source);
    $this->simplesamlConfig = \SimpleSAML_Configuration::getInstance();
  }

  /**
   * Forwards the user to the IdP for authentication.
   */
  public function externalAuthenticate() {
    $this->instance->requireAuth();
  }

  /**
   * @return string
   *   The storage type.
   */
  public function getStorage() {
    return $this->simplesamlConfig->getValue('store.type');
  }

  /**
   * @return bool
   *   If the user is authenticated by the IdP.
   */
  public function isAuthenticated() {
    return $this->instance->isAuthenticated();
  }

  /**
   * Gets the unique id of the user from the IdP.
   *
   * @return string
   *   The authname.
   */
  public function getAuthname() {
    return $this->getAttribute($this->config->get('unique_id'));
  }

  /**
   * Gets the name attribute.
   *
   * @return string
   *   The name attribute.
   */
  public function getDefaultName() {
    return $this->getAttribute($this->config->get('user_name'));
  }

  /**
   * Gets the mail attribute.
   *
   * @return string
   *   The mail attribute.
   */
  public function getDefaultEmail() {
    return $this->getAttribute($this->config->get('mail_attr'));
  }

  /**
   * Gets all SimpleSAML attributes.
   *
   * @return mixed
   */
  public function getAttributes() {
    return $this->instance->getAttributes();
  }

  /**
   * @param $attribute
   * @return bool
   * @throws \Exception
   */
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

  /**
   * Log a user out through the SimpleSAMLphp instance.
   *
   * @param $redirect_path
   */
  public function logout($redirect_path) {
    $this->instance->logout($redirect_path);
  }

}
