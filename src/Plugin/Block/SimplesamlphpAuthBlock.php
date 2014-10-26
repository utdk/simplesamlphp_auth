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
  public function isCacheable() {
    return FALSE;
  }

}
