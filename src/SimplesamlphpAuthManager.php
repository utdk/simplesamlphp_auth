<?php

namespace Drupal\simplesamlphp_auth;

use Drupal\Core\Config\ConfigFactoryInterface;

class SimplesamlphpAuthManager {

  protected $config;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('simplesamlphp_auth.settings');
  }

  public function isActivated() {
    if ($this->config->get('activate')) {
      return TRUE;
    }

    return FALSE;
  }

}
