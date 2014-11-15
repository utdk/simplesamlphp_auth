<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Form\BasicSettingsForm.
 */

namespace Drupal\simplesamlphp_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for the simplesamlphp_auth admin form.
 */
class AdminForm extends ConfigFormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'simplesamlphp_auth_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('simplesamlphp_auth.settings');

    // @TODO update these
    global $_simplesamlphp_auth_saml_version, $base_url;
    if (!empty($_simplesamlphp_auth_saml_version )) {
      $ver = explode('.', $_simplesamlphp_auth_saml_version);
      if ( !($ver[0] >= 1 && $ver[1] >= 5) ) {
        drupal_set_message(t("Please upgrade SimpleSAMLphp. You are using %ssp_version", array('%ssp_version' => $_simplesamlphp_auth_saml_version)), 'warning');
      }
    }

    $form['basic'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Setup'),
      '#collapsible' => FALSE,
    );
    $form['basic']['activate'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Activate authentication via SimpleSAMLphp'),
      '#default_value' => $config->get('activate'),
      '#description' => $this->t('Checking this box before configuring the module could lock you out of Drupal.'),
    );
    $form['basic']['install_dir'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Installation directory (default: /var/simplesamlphp)'),
      '#default_value' => $config->get('install_dir'),
      '#description' => $this->t('The base directory of simpleSAMLphp. Absolute path with no trailing slash.'),
    );
    $form['basic']['auth_source'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Autenticaton source for this SP (default: default-sp)'),
      '#default_value' => $config->get('auth_source'),
      '#description' => $this->t('The name of the source to use from @path/config/authsources.php', array('@path' => $config->get('install_dir'))),
    );
    $form['basic']['force_https'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Force https for login links'),
      '#default_value' => $config->get('force_https'),
      '#description' => $this->t('Should be enabled on production sites.'),
    );

    $form['user_info'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('User Info and Syncing'),
      '#collapsible' => FALSE,
    );
    $form['user_info']['user_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which attribute from simpleSAMLphp should be used as user\'s name'),
      '#default_value' => $config->get('user_name'),
      '#description' => $this->t('Example: <i>eduPersonPrincipalName</i> or <i>displayName</i><br />If the attribute is multivalued, the first value will be used.'),
      '#required' => TRUE,
    );
    $form['user_info']['unique_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which attribute from simpleSAMLphp should be used as unique identifier for the user'),
      '#default_value' => $config->get('unique_id'),
      '#description' => $this->t('Example: <i>eduPersonPrincipalName</i> or <i>eduPersonTargetedID</i><br />If the attribute is multivalued, the first value will be used.'),
      '#required' => TRUE,
    );
    $form['user_info']['mail_attr'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which attribute from simpleSAMLphp should be used as user mail address'),
      '#default_value' => $config->get('mail_attr'),
      '#description' => $this->t('Example: <i>mail</i><br />If the user attribute is multivalued, the first value will be used.'),
    );
    $form['user_info']['role_population'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Automatic role population from simpleSAMLphp attributes'),
      '#default_value' => $config->get('role.population'),
      '#description' => $this->t('A pipe separated list of rules.<br />Example: <i>roleid1:condition1|roleid2:contition2...</i> <br />For instance: <i>1:eduPersonPrincipalName,@=,uninett.no;affiliation,=,employee|2:mail,=,andreas@uninett.no</i>'),
    );
    $form['user_info']['role_eval_every_time'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Reevaluate roles every time the user logs in.'),
      '#default_value' => $config->get('role.eval_every_time'),
      '#description' => $this->t('NOTE: This means users could lose any roles that have been assigned manually in Drupal.'),
    );

    $form['user_provisioning'] = array(
      '#type' => 'details',
      '#title' => $this->t('User Provisioning'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['user_provisioning']['register_users'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Register users (i.e., auto-provisioning)'),
      '#default_value' => $config->get('register_users'),
      '#description' => $this->t('Determines wether or not the module should automatically create/register new Drupal accounts for users that authenticate using SimpleSAMLphp. Unless you\'ve done some custom work to provision Drupal accounts with the necessary authmap entries you will want this checked.<br /><br />NOTE: If unchecked each user must already have been provisioned a Drupal account with an appropriate entry in the authmap table before logging in. Otherwise they will receive a notice and be denied access. Be aware that simply creating a Drupal account will not create the necessary entry in the authmap table.'),
    );

    $form['authentication'] = array(
      '#type' => 'details',
      '#title' => $this->t('Drupal Authentication'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['authentication']['allow_set_drupal_pwd'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow SAML users to set Drupal passwords.'),
      '#default_value' => $config->get('allow.set_drupal_pwd'),
      '#description' => $this->t('Check this box if you want to let people set passwords for their local Drupal accounts. This will allow users to log in using either SAML or a local Drupal account. Disabling this removes the password change fields from the user profile form.<br/>NOTE: In order for them to login using their local Drupal password you must allow local logins with the settings below.'),
    );
    $form['authentication']['allow_default_login'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow authentication with local Drupal accounts'),
      '#default_value' => $config->get('allow.default_login'),
      '#description' => $this->t('Check this box if you want to let people log in with local Drupal accounts (without using simpleSAMLphp). If you want to restrict this privilege to certain users you can enter the Drupal user IDs in the field below.'),
    );
    $form['authentication']['allow_default_login_roles'] = array(
      '#type' => 'select',
      '#size' => 3,
      '#options' => array_map('\Drupal\Component\Utility\String::checkPlain', user_role_names(TRUE)),
      '#multiple' => TRUE,
      '#title' => $this->t('Which ROLES should be allowed to login with local accounts?'),
      '#default_value' => $config->get('allow.default_login_roles'),
      '#description' => $this->t('Roles that should be allowed to login without simpleSAMLphp. Examples are dev/admin roles or guest roles.'),
    );
    $form['authentication']['allow_default_login_users'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which users should be allowed to login with local accounts?'),
      '#default_value' => $config->get('allow.default_login_users'),
      '#description' => $this->t('Example: <i>1,2,3</i><br />A comma-separated list of user IDs that should be allowed to login without simpleSAMLphp. If left blank, all local accounts can login.'),
    );
    $form['authentication']['logout_goto_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Optionally, specify a URL for users to go to after logging out'),
      '#default_value' => $config->get('logout_goto_url'),
      '#description' => $this->t('Example: ' . $base_url),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('simplesamlphp_auth.settings')
      ->set('activate', $form_state->getValue('activate'))
      ->set('install_dir', $form_state->getValue('install_dir'))
      ->set('auth_source', $form_state->getValue('auth_source'))
      ->set('force_https', $form_state->getValue('force_https'))
      ->set('user_name', $form_state->getValue('user_name'))
      ->set('unique_id', $form_state->getValue('unique_id'))
      ->set('mail_attr', $form_state->getValue('mail_attr'))
      ->set('role.population', $form_state->getValue('role_population'))
      ->set('role.eval_every_time', $form_state->getValue('role_eval_every_time'))
      ->set('register_users', $form_state->getValue('register_users'))
      ->set('allow.set_drupal_pwd', $form_state->getValue('allow_set_drupal_pwd'))
      ->set('allow.default_login', $form_state->getValue('allow_default_login'))
      ->set('allow.default_login_roles', $form_state->getValue('allow_default_login_roles'))
      ->set('allow.default_login_users', $form_state->getValue('allow_default_login_users'))
      ->set('logout_goto_url', $form_state->getValue('logout_goto_url'))
      ->save();

    parent::submitForm($form, $form_state);

  }
}
