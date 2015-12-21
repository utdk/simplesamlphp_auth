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
 *   The SimpleSAMLphp attributes for this user
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
 * @param $attributes
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
