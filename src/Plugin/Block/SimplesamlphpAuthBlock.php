<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Plugin\Block\SimplesamlphpAuthBlock.
 */

namespace Drupal\simplesamlphp_auth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\LinkGeneratorTrait;
use Drupal\Core\Url;

/**
 * Provides a 'Most recent poll' block.
 *
 * @Block(
 *   id = "simplesamlphp_auth_block",
 *   admin_label = @Translation("SimpleSAMLphp Auth Status"),
 * )
 */
class SimplesamlphpAuthBlock extends BlockBase {

  use LinkGeneratorTrait;

  /**
   * {@inheritdoc}
   */
  public function build() {

    $activated = \Drupal::config('simplesamlphp_auth.settings')->get('activate');

    $simplesaml = \Drupal::service('simplesamlphp_auth.manager');

    if ($activated) {
      if ($simplesaml->isAuthenticated()) {
        $content = $this->t('Logged in as %authname<br />!logout', array(
          '%authname' => $simplesaml->getAuthname(),
          '!logout' => $this->l('Log Out', new Url('user.logout')),
        ));
      }
      else {
        $label = Drupal::config('simplesamlphp_auth.settings')->get('login_link_display_name');
        $content = $this->t('!login', array(
          '!login' => $this->l($label, new Url('simplesamlphp_auth.saml_login')),
        ));
      }
    }
    else {
      $content = $this->t('SimpleSAML not enabled');
    }

    return array(
      '#title' => $this->t('SimpleSAMLphp Auth Status'),
      '#markup' => $content,
    );
  }

}
