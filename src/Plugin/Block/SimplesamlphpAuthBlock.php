<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Plugin\Block\SimplesamlphpAuthBlock.
 */

namespace Drupal\simplesamlphp_auth\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Simplesaml Status' block.
 *
 * @Block(
 *   id = "simplesamlphp_auth_block",
 *   admin_label = @Translation("simpleSAMLphp authentication"),
 * )
 */
class SimplesamlphpAuthBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // @TODO this or feed the constructor a simplesaml obj?
    $account = \Drupal::currentUser();
    return array(
      '#title' => $this->t('simpleSAMLphp login'),
      // @TODO return _simplesamlphp_auth_generate_block_text
    );
  }

  /**
   * {@inheritdoc}
   */
//  public function isCacheable() {
//    return FALSE;
//  }
//
//}
//
//if (!_simplesamlphp_auth_isEnabled()) {
//  // Exit without executing.
//  return;
//}
//
//// Check if valid local session exists..
//if ($_simplesamlphp_auth_as->isAuthenticated()) {
//  $block_content .= '<p>Logged in as: ' . $user->name . '<br />' . l('Log Out', 'user/logout') . '</a></p>';
//}
//else {
//  $block_content .= '<p>' . l('Federated Log In', 'saml_login') . '</p>';
//}
//
//return $block_content;
