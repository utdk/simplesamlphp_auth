<?php

/**
 * @file
 * Contains \Drupal\simplesamlphp_auth\Controller\SimplesamlphpAuthController.
 */

namespace Drupal\simplesamlphp_auth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\simplesamlphp_auth\SimplesamlphpAuthManager;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;


class SimplesamlphpAuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\simplesamlphp_auth\SimplesamlphpAuthManager
   */
  public $simplesaml;

  protected $urlGenerator;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $requestStack;

  protected $account;

  protected $pathValidator;

  public function __construct(SimplesamlphpAuthManager $simplesaml, UrlGeneratorInterface $url_generator, RequestStack $requestStack, AccountInterface $account, PathValidatorInterface $pathValidator) {
    $this->simplesaml = $simplesaml;
    $this->urlGenerator = $url_generator;
    $this->requestStack = $requestStack;
    $this->account = $account;
    $this->pathValidator = $pathValidator;
  }

  /**
   * @inheritdoc
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simplesamlphp_auth.manager'),
      $container->get('url_generator'),
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('path.validator')
    );
  }

  public function authenticate() {

    // Ensure the module has been turned on before continuing with the request.
    if (!$this->simplesaml->isActivated()) {
      return $this->redirect('user.login');
    }

    global $base_url;
    $config = $this->config('simplesamlphp_auth.settings');

    // The user is not logged into Drupal.
    if ($this->account->isAnonymous()) {

      // User is logged in to the SimpleSAMLphp IdP, but not to Drupal.
      if ($this->simplesaml->isAuthenticated()) {

        // Get unique identifier from saml attributes.
        $authname = $this->simplesaml->getAuthname();

        if (!empty($authname)) {
          // User is logged in with SAML authentication and we got the unique
          // identifier, so try to log into Drupal.

          // Check to see whether the external user exists in Drupal
          $account = $this->simplesaml->externalLoad($authname);

          // @TODO check we're not doubling work
          // If we did not find a Drupal user, register a new one.
          if (!$account) {
            $account = $this->simplesaml->externalRegister($authname);
          }

          // Log the user in.
          if ($account instanceof UserInterface) {
            $this->simplesaml->externalLogin($account);
          }
        }
      }
    }
    else {
      // Run the function for users logged in to Drupal already
      //     simplesaml_auth_moderate_local_login();
    }

    // Do some sanity checking before attempting anything.
    if ($this->simplesaml->getStorage() === 'phpsession') {
      // @TODO logging here
      return $this->redirect('user.login');
    }

    // See if a URL has been explicitly provided in ReturnTo. If so, use it (as long as it points to this site).
    $request = $this->requestStack->getCurrentRequest();

    if ($return_to = $request->request->get('ReturnTo')) {
      if ($this->pathValidator->isValid($return_to) && UrlHelper::externalIsLocal($return_to, $base_url)) {
        $redirect = $request->request->get('ReturnTo');
      }
    }
    elseif ($referer = $request->server->get('HTTP_REFERER')) {
      if ($this->pathValidator->isValid($referer) && UrlHelper::externalIsLocal($referer, $base_url)) {
        $redirect = $request->server->get('HTTP_REFERER');
      }
    }

    // If the user is anonymous, set the cookie (if we can) and require authentication.
    if ($this->account->isAnonymous()) {
      if (isset($redirect)) {
        // Set the cookie so we can deliver the user to the place they started
        // @TODO probably a more symfony way of doing this
        setrawcookie('simplesamlphp_auth_returnto', $redirect, time() + 60 * 60);
      }

      $this->simplesaml->externalAuthenticate();

      // If the user is authenticated, send them along.
      // @TODO learn this
    }
    else {

      $go_to_url = NULL;

      // Check to see if we've set a cookie. If there is one, give it priority.
      if (isset($_COOKIE['simplesamlphp_auth_returnto']) && $_COOKIE['simplesamlphp_auth_returnto']) {
        // use the cookie for the ReturnTo
        $go_to_url = $_COOKIE['simplesamlphp_auth_returnto'];

        // unset the cookie
        setrawcookie('simplesamlphp_auth_returnto', '');

      }
      elseif ($return_to) {
        $go_to_url = $return_to;
      }

      // @TODO temp hack
      return $this->redirect('user.login');
      // If a ReturnTo has been set.
      if ($go_to_url) {
//        drupal_goto(str_replace($base_url . '/', '', $go_to_url));
      }
      else {
        return $this->redirect('user.login');
      }

      return $this->redirect('user.login');

    }
    // Needs this here @todo
    return $this->redirect('user.login');

  }
}

