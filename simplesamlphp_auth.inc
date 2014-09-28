<?php

/**
 * @file
 * Contains non-hook implementations.
 */

/**
 * Performs login and/or register actions for SAML authenticated users.
 */
function _simplesaml_auth_login_register() {
  global $user;
  global $_simplesamlphp_auth_as;

  // User is logged in - SimpleSAMLphp (but not Drupal).
  if ($_simplesamlphp_auth_as->isAuthenticated()) {

    // Get unique identifier from saml attributes.
    $authname = _simplesamlphp_auth_get_authname();

    _simplesaml_auth_debug(t('Authname is [%authname] userid is [%uid]', array(
      '%authname' => $authname,
      '%uid' => $user->uid,
    )));

    if (!empty($authname)) {
      // User is logged in with SAML authentication and we got the unique
      // identifier, so try to log into Drupal.
      _simplesaml_auth_debug(t('Loading Drupal user [%authname]', array('%authname' => $authname)));

      // Retrieve user mapping and attempt to log the user in.
      $ext_user = user_external_load($authname);

      // If we did not find a Drupal user, register a new one.
      if (!$ext_user) {
        $ext_user = _simplesaml_auth_user_register($authname);
      }

      // Log the user in.
      _simplesaml_auth_user_login($ext_user);

    }
  }
}


/**
 * Creates a new Drupal account for a SAML authenticated user.
 *
 * @param string $authname
 *   Gets the authname attribute from the SAML assertion as provided by
 *   _simplesamlphp_auth_get_authname().
 *
 * @return object
 *   The newly create Drupal user object.
 */
function _simplesaml_auth_user_register($authname) {
  global $user;
  global $_simplesamlphp_auth_as;

  // First we check the admin settings for simpleSAMLphp and find out if we are allowed to register users.
  if (variable_get('simplesamlphp_auth_registerusers', TRUE)) {

    // We are allowed to register new users.
    _simplesaml_auth_debug(t('Register [%authname]', array('%authname' => $authname)));

    // It's possible that a user with this name already exists, but is not
    // permitted to login to Drupal via SAML. If so, log out of SAML and
    // redirect to the front page.
    $account = user_load_by_name($authname);
    if ($account) {
      _simplesaml_auth_debug(t('User [%authname] could not be registered because that username already exists and is not SAML enabled.', array(
        '%authname' => $authname,
      )));

      drupal_set_message(t('We are sorry, your user account is not SAML enabled.'));
      $_simplesamlphp_auth_as->logout(base_path());

      return FALSE;
    }

    // Register the new user.
    user_external_login_register($authname, 'simplesamlphp_auth');

    _simplesaml_auth_debug(t('Registered [%authname] with uid @uid', array(
      '%authname' => $authname,
      '@uid' => $user->uid
    )));

    if (!empty($user->uid)) {
      // Populate roles based on configuration setting.
      $roles = _simplesamlphp_auth_rolepopulation(variable_get('simplesamlphp_auth_rolepopulation', ''));
      $userinfo = array('roles' => $roles);
      // @todo - Fjernet rolle-delen her da den gav en bra feilmelding når roller ikke finnes.
      $user = user_save($user, $userinfo);

      return $user;
    }
    else {
      // We were unable to register this new user on the site.
      // We let the user know about this, log an error, and redirect to the home page.
      drupal_set_message(t("We are sorry. While you have successfully authenticated, we were unable to create an account for you on this site. Please ask the site administrator to provision access for you."));
      watchdog('simplesamlphp_auth', 'Unable to register %authname using simplesamlphp_auth', array('%authname' => $authname), WATCHDOG_ERROR);
      $_simplesamlphp_auth_as->logout(base_path());
    }
  }
  else {
    // We are not allowed to register new users on the site through simpleSAML.
    // We let the user know about this and redirect to the user/login page.
    drupal_set_message(t("We are sorry. While you have successfully authenticated, you are not yet entitled to access this site. Please ask the site administrator to provision access for you."));
    $_simplesamlphp_auth_as->logout(base_path());
  }
}

/**
 * Updates a SAML authenticated user's account with current username and email.
 *
 * @param object $account
 *   The user account object to update.
 */
function _simplesaml_auth_user_update($account) {
  _simplesaml_auth_debug(t('Updating username [%acctname]', array('%acctname' => $account->name)));

  db_update('users')
    ->fields(array('name' => $account->name))
    ->condition('uid', $account->uid)
    ->execute();

  // Get mail from default attribute.
  try {
    $mail_address = _simplesamlphp_auth_get_mail();
  } catch (Exception $e) {
    drupal_set_message(t('Your e-mail address was not provided by your identity provider (IDP).'), "error");
    watchdog('simplesamlphp_auth', $e->getMessage(), NULL, WATCHDOG_CRITICAL);
  }

  _simplesaml_auth_debug(t('Updating mail [%mailaddr]', array('%mailaddr' => $mail_address)));

  if (!empty($mail_address)) {
    db_update('users')
      ->fields(array('mail' => $mail_address))
      ->condition('uid', $account->uid)
      ->execute();
  }
}


/**
 * Logs an SAML-authenticated user into Drupal.
 *
 * @param object $ext_user
 *   The Drupal user object to be logged in.
 *
 * @throws Exception
 */
function _simplesaml_auth_user_login($ext_user) {
  global $user;

  // See if we're supposed to re-evaluate role assignments.
  if (variable_get('simplesamlphp_auth_roleevaleverytime', 0)) {
    // Populate roles based on configuration setting.
    _simplesaml_auth_debug(t('User already registered [%authname] updating roles.', array('%authname' => $ext_user->name)));
    $roles = _simplesamlphp_auth_rolepopulation(variable_get('simplesamlphp_auth_rolepopulation', ''));
    $userinfo = array('roles' => $roles);

    // Save the updated roles and populate the user object.
    $user = user_save($ext_user, $userinfo);
  }
  else {
    // No need to evaluate roles, populate the user object.
    $user = $ext_user;
  }

  if (module_exists('rules')) {
    rules_invoke_event('simplesamlphp_auth_rules_event_login', $user);
  }

  // Finalizing the login, calls hook_user op login.
  $edit = array();
  user_login_finalize($edit);
}

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

/****************************************************************************
 * Public functions *********************************************************
 ****************************************************************************/

/**
 * Return any attributes provided by the SAML IDP.
 *
 * @param $attribute
 *   The attribute whose value to return.  Can be skipped if all attribute
 *   values are requested.
 *
 * @return
 *   If an attribute was provided, the value of the attribute is returned.
 *   Otherwise, an array of all attribute values is returned, keyed by
 *   attribute.
 */
function simplesamlphp_auth_get_attributes($attribute = NULL) {
  global $_simplesamlphp_auth_saml_attributes;

  if (isset($attribute)) {

    // Initially, assume that there's nothing to return.
    $result = NULL;

    // If the specified attribute is set, grab it.
    if (isset($_simplesamlphp_auth_saml_attributes)) {
      if (isset($_simplesamlphp_auth_saml_attributes[$attribute])) {
        $result = $_simplesamlphp_auth_saml_attributes[$attribute];
      }
    }
  }

  // No specific attribute was requested; return all of them.
  else {

    // Initially, assume that there's nothing to return.
    $result = array();

    // If the global array exists, return it.
    if (isset($_simplesamlphp_auth_saml_attributes)) {
      $result = $_simplesamlphp_auth_saml_attributes;
    }
  }

  // Return whatever we've got.`
  return $result;
}

/**
 * Determine if the current user is authenticated through SAML.
 *
 * @return
 *   TRUE if the current user is authenticated through SAML.  FALSE otherwise.
 */
function simplesamlphp_auth_is_authenticated() {
  global $_simplesamlphp_auth_as;

  // Assume that the user isn't authenticated until proven otherwise.
  $authenticated = FALSE;

  // If the associated global variable exists, and the auth flag is set, note it.
  if (isset($_simplesamlphp_auth_as) && $_simplesamlphp_auth_as->isAuthenticated()) {
    $authenticated = TRUE;
  }

  // Return the result.
  return $authenticated;
}
