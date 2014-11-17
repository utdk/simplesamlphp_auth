<?php

/**
 * @file
 * Contains non-hook implementations.
 */

/**
 * Denies non-SAML-authenticated access to the site for configured Drupal roles.
 */
function simplesaml_auth_moderate_local_login() {
  global $user;
  global $_simplesamlphp_auth_as;

  // If we forbid users from logging in using local accounts.
  if (!variable_get('simplesamlphp_auth_allowdefaultlogin', TRUE)) {
    // If the user has NOT been authenticated via simpleSAML...
    if (!$_simplesamlphp_auth_as->isAuthenticated()) {
      // :FYI: Until Drupal issue #754560 is corrected this message will never be seen by the user.
      drupal_set_message(t("We are sorry, users are not permitted to log in using local accounts."));
      // Destroy the user's session (log them out).
      _simplesamlphp_auth_destroy_drupal_session();
    }
  }
  // If we are allowing users to log in with local accounts.
  else {
    // If the user has NOT been authenticated via simpleSAML.
    if (!$_simplesamlphp_auth_as->isAuthenticated()) {
      // See if we limit this privilege to specified users
      $strAllwDefLogUsers = variable_get('simplesamlphp_auth_allowdefaultloginusers', '');
      $arrAllwDefLogUsers = array();
      // See if we limit this privilege to specified roles.
      $arrAllwDefLogRoles = variable_get('simplesamlphp_auth_allowdefaultloginroles', FALSE);

      // If user IDs or roles are specified, we let them in, but everyone else gets logged out.
      if (drupal_strlen($strAllwDefLogUsers) || $arrAllwDefLogRoles) {

        // Convert the string into an array.
        // @todo Perform a test to make sure that only numbers, spaces, or commas are in the string.
        $arrAllwDefLogUsers = explode(',', $strAllwDefLogUsers);

        // If we still have something to work with.
        if (0 < count($arrAllwDefLogUsers) || 0 < count($arrAllwDefLogRoles)) {
          /* Log the user out of Drupal if:
             1) the current user's uid is NOT in the list of allowed uids...
             2) or their role does not match and allowed mixed mode role. */
          $matchRoles = array_intersect(array_keys($user->roles), $arrAllwDefLogRoles);
          if (!in_array($user->uid, $arrAllwDefLogUsers) && count($matchRoles) == 0) {
            // User is logged into Drupal, but may not be logged into simpleSAML.
            // If this is the case we're supposed to log the user out of Drupal.

            // :FYI: Until Drupal issue #754560 is corrected this message will never be seen by the user.
            drupal_set_message(t("We are sorry, you are not permitted to log in using a local account."));

            // The least we can do is write something to the watchdog so someone will know what's happening.
            watchdog('simplesamlphp_auth', 'User %name not authorized to log in using local account.', array('%name' => $user->name));

            _simplesamlphp_auth_destroy_drupal_session();
          }
        }
      }
    }
  }
}
