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
    $form['basic']['auth_source'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Authentication source for this SP'),
      '#default_value' => $config->get('auth_source'),
      '#description' => $this->t('The name of the source to use (Usually in authsources.php).'),
    );
    $form['basic']['login_link_display_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Federated Log In Link Display Name'),
      '#default_value' => $config->get('login_link_display_name'),
      '#description' => $this->t('Text to display as the link to the external federated login page.'),
    );

    $form['user_info'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('User info and syncing'),
      '#collapsible' => FALSE,
    );
    $form['user_info']['user_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('SimpleSAMLphp attribute to be used as username for the user'),
      '#default_value' => $config->get('user_name'),
      '#description' => $this->t('Example: <i>eduPersonPrincipalName</i> or <i>displayName</i><br />If the attribute is multivalued, the first value will be used.'),
      '#required' => TRUE,
    );
    $form['user_info']['user_name_sync'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Synchronize user name on every login'),
      '#default_value' => $config->get('sync.user_name'),
      '#description' => $this->t('Check if user name should be synchronized every time a user logs in.'),
      '#required' => FALSE,
    );
    $form['user_info']['unique_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('SimpleSAMLphp attribute to be used as unique identifier for the user'),
      '#default_value' => $config->get('unique_id'),
      '#description' => $this->t('Example: <i>eduPersonPrincipalName</i> or <i>eduPersonTargetedID</i><br />If the attribute is multivalued, the first value will be used.'),
      '#required' => TRUE,
    );
    $form['user_info']['mail_attr'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('SimpleSAMLphp attribute to be used as email address for the user'),
      '#default_value' => $config->get('mail_attr'),
      '#description' => $this->t('Example: <i>mail</i><br />If the user attribute is multivalued, the first value will be used.'),
    );
    $form['user_info']['mail_attr_sync'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Synchronize email address on every login'),
      '#default_value' => $config->get('sync.mail'),
      '#description' => $this->t('Check if email address should be synchronized every time a user logs in.'),
      '#required' => FALSE,
    );
    $form['user_info']['role_population'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Automatic role population from simpleSAMLphp attributes'),
      '#default_value' => $config->get('role.population'),
      '#description' => $this->t('A pipe separated list of rules. Each rule consists of a Drupal role id, a SimpleSAML attribute name, an operation and a value to match. <i>e.g. role_id1:attribute_name,operation,value|role_id2:attribute_name2,operation,value... etc</i><br /><br />Each operation may be either "@", "@=" or "~=". <ul><li>"=" requires the value exactly matches the attribute;</li><li>"@=" requires the portion after a "@" in the attribute to match the value;</li><li>"~=" allows the value to match any part of any element in the attribute array.</li></ul>For instance:<br /><i>staff:eduPersonPrincipalName,@=,uninett.no;affiliation,=,employee|admin:mail,=,andreas@uninett.no</i><br />would ensure any user with an eduPersonPrinciplaName SAML attribute matching .*@uninett.no would be assigned a staff role and the user with the mail attribute exactly matching andreas@uninett.no would assume the admin role.'),

      // A '=' requires the $value exactly matches the $attribute, A '@='
      // requires the portion after a '@' in the $attribute to match theuninett.no
      // $value and a '~=' allows the value to match any part of any
      // element in the $attribute array.

      // The full role map string, when mapped to the variables below, presents
      // itself thus:
      // $role_id:$key,$op,$value;$key,$op,$value;$key,$op,$value|$role_id:$key,$op,$value... etc
    );
    $form['user_info']['role_eval_every_time'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Reevaluate roles every time the user logs in'),
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
      '#title' => $this->t('Register users'),
      '#default_value' => $config->get('register_users'),
      '#description' => $this->t('Determines wether or not the module should automatically create/register new Drupal accounts for users that authenticate using SimpleSAMLphp. Unless you\'ve done some custom work to provision Drupal accounts with the necessary simplesamlphp_auth_authmap entries you will want this checked.<br /><br />NOTE: If unchecked each user must already have been provisioned a Drupal account with an appropriate entry in the simplesamlphp_auth_authmap table before logging in. Otherwise they will receive a notice and be denied access. Be aware that simply creating a Drupal account will not create the necessary entry in the simplesamlphp_auth_authmap table.'),
    );

    $form['authentication'] = array(
      '#type' => 'details',
      '#title' => $this->t('Drupal authentication'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $form['authentication']['allow_set_drupal_pwd'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow SAML users to set Drupal passwords'),
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
      '#type' => 'checkboxes',
      '#size' => 3,
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names(TRUE)),
      '#multiple' => TRUE,
      '#title' => $this->t('Roles to be allowed to log in locally'),
      '#default_value' => $config->get('allow.default_login_roles'),
      '#description' => $this->t('Roles that should be allowed to login without simpleSAMLphp. Examples are dev/admin roles or guest roles.'),
    );
    $form['authentication']['allow_default_login_users'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Users to be allowed to log in locally'),
      '#default_value' => $config->get('allow.default_login_users'),
      '#description' => $this->t('Example: <i>1,2,3</i><br />A comma-separated list of user IDs that should be allowed to login without simpleSAMLphp. If left blank, all local accounts can login.'),
    );
    $form['authentication']['logout_goto_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Redirect users after logging out'),
      '#default_value' => $config->get('logout_goto_url'),
      '#description' => $this->t('Optionally, specify a URL for users to go to after logging out.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('simplesamlphp_auth.settings')
      ->set('activate', $form_state->getValue('activate'))
      ->set('auth_source', $form_state->getValue('auth_source'))
      ->set('login_link_display_name', $form_state->getValue('login_link_display_name'))
      ->set('user_name', $form_state->getValue('user_name'))
      ->set('sync.user_name', $form_state->getValue('user_name_sync'))
      ->set('unique_id', $form_state->getValue('unique_id'))
      ->set('mail_attr', $form_state->getValue('mail_attr'))
      ->set('sync.mail', $form_state->getValue('mail_attr_sync'))
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

    /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'simplesamlphp_auth.settings'
    ];
  }
}
