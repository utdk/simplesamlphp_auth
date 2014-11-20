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
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Controller routines for simplesamlphp_auth routes.
 */
class SimplesamlphpAuthController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\simplesamlphp_auth\SimplesamlphpAuthManager
   */
  public $simplesaml;

  /**
   * The url generator service.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $requestStack;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @param SimplesamlphpAuthManager $simplesaml
   * @param UrlGeneratorInterface $url_generator
   * @param RequestStack $requestStack
   * @param AccountInterface $account
   * @param PathValidatorInterface $pathValidator
   */
  public function __construct(SimplesamlphpAuthManager $simplesaml, UrlGeneratorInterface $url_generator, RequestStack $requestStack, AccountInterface $account, PathValidatorInterface $pathValidator, LoggerInterface $logger) {
    $this->simplesaml = $simplesaml;
    $this->urlGenerator = $url_generator;
    $this->requestStack = $requestStack;
    $this->account = $account;
    $this->pathValidator = $pathValidator;
    $this->logger = $logger;
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
      $container->get('path.validator'),
      $container->get('logger.factory')->get('simplesamlphp_auth')
    );
  }

  /**
   * Logs the user in via SimpleSAML federation.
   *
   * @return RedirectResponse
   *   A redirection to either a designated page or the user login page.
   */
  public function authenticate() {
    global $base_url;

    // Ensure the module has been turned on before continuing with the request.
    if (!$this->simplesaml->isActivated()) {
      return $this->redirect('user.login');
    }

    // Ensure phpsession isn't the session storage location.
    if ($this->simplesaml->getStorage() === 'phpsession') {
      return $this->redirect('user.login');
    }

    // See if a URL has been explicitly provided in ReturnTo. If so, use it (as long as it points to this site).
    $request = $this->requestStack->getCurrentRequest();

    if (($return_to = $request->request->get('ReturnTo')) || ($return_to = $request->server->get('HTTP_REFERER'))) {
      if ($this->pathValidator->isValid($return_to) && UrlHelper::externalIsLocal($return_to, $base_url)) {
        $redirect = $return_to;
      }
    }

    // The user is not logged into Drupal.
    if ($this->account->isAnonymous()) {

      if (isset($redirect)) {
        // Set the cookie so we can deliver the user to the place they started
        // @TODO probably a more symfony way of doing this
        setrawcookie('simplesamlphp_auth_returnto', $redirect, time() + 60 * 60);
      }

      // User is logged in to the SimpleSAMLphp IdP, but not to Drupal.
      if ($this->simplesaml->isAuthenticated()) {

        // Get unique identifier from saml attributes.
        $authname = $this->simplesaml->getAuthname();

        if (!empty($authname)) {
          // User is logged in with SAML authentication and we got the unique
          // identifier, so try to log into Drupal.
          // Check to see whether the external user exists in Drupal. If they
          // do not exist, create them.
          $account = $this->simplesaml->externalLoad($authname);

          // Log the user in.
          if ($account instanceof UserInterface) {
            $this->simplesaml->externalLogin($account);
          }
        }

        $this->simplesaml->externalAuthenticate();
      }
    }

    // Check to see if we've set a cookie. If there is one, give it priority.
    if ($this->requestStack->getCurrentRequest()->cookies->has('simplesamlphp_auth_returnto')) {
      $redirect = $this->requestStack->getCurrentRequest()->cookies->get('simplesamlphp_auth_returnto');

      // unset the cookie
      setrawcookie('simplesamlphp_auth_returnto', '');
    }

    if ($redirect) {
      $response = new RedirectResponse($redirect, RedirectResponse::HTTP_FOUND);
      return $response;
    }

    return $this->redirect('user.login');

  }

}
