<?php
/**
 * @file
 * Hooks for simpleSAMLphp Authentication module.
 */

/**
 * Allows the use of custom logic to alter the roles assigned to a user.
 *
 * Whenever a user's roles are evaluated this hook will be called, allowing
 * custom logic to be used to alter or even completely replace the roles
 * evaluated.
 *
 * @param array &$roles
 *   The roles that have been selected for the current user
 *   by the role evaluation process.
 * @param array $attributes
 *   The SimpleSAMLphp attributes for this user.
 */
function hook_simplesamlphp_auth_user_roles_alter(&$roles, $attributes) {
  if (isset($attributes['roles'])) {
    // The roles provided by the IdP.
    $sso_roles = $attributes['roles'];

    // Match role names in the saml attributes to local role names.
    $user_roles = array_intersect(user_roles(), $sso_roles);

    foreach (array_keys($user_roles) as $rid) {
      $roles[$rid] = $rid;
    }
  }
}

/**
 * Allows other modules to decide whether user with the given set of
 * attributes is allowed to log in via SSO or not.
 *
 * Each implementation should take care of displaying errors, there is no
 * message implementation at hook invocation. Implementations should return
 * a boolean indicating the success of the access check. Access will be denied
 * if any implementations return FALSE.
 *
 * @param array $attributes
 *   The SimpleSAMLphp attributes for this user.
 * @return bool
 */
function hook_simplesamlphp_auth_allow_login($attributes) {
  if (in_array('student', $attributes['roles'])) {
    return FALSE;
  }
  else {
    return TRUE;
  }
}

/**
 * Allows other modules to change the authname that is being stored when
 * a pre-existing Drupal user account gets SAML-enabled.
 * This is done by clicking the checkbox "Enable this user to leverage SAML
 * authentication" upon user registration or the user edit form (given enough
 * permissions).
 *
 * For example, this allows you to pre-register Drupal accounts and store the
 * entered email address (rather than the default username) as the authname.
 * The SAML user with that email address as authname will then be able to login
 * as that Drupal user.
 *
 * @param string $authname
 * @param \Drupal\user\UserInterface $account
 */
function hook_simplesamphp_auth_account_authname_alter(&$authname, $account) {
  $authname = $account->mail;
}


/**
 * @param array $attributes
 * @return \Drupal\user\UserInterface | bool
 */
function hook_simplesamlphp_auth_existing_user($attributes) {
  $saml_mail = $attributes['mail'];
  $existing_users = \Drupal::service('entity.manager')->getStorage('user')->loadByProperties(array('mail' => $saml_mail));
  if ($existing_users) {
    $existing_user = is_array($existing_users) ? reset($existing_users) : FALSE;
    if ($existing_user) {
      return $existing_user;
    }
  }
  return FALSE;
}
