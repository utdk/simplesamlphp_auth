<?php

/**
 * @file
 * Contains Drupal\Tests\simplesamlphp_auth\Unit\Service\SimplesamlphpAuthManagerTest.
 */

namespace Drupal\Tests\simplesamlphp_auth\Unit\Service;

use Drupal\Tests\UnitTestCase;
use SimpleSAML_Auth_Simple;
use SimpleSAML_Configuration;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * SimplesamlphpAuthManager unit tests.
 *
 * @ingroup simplesamlphp_auth
 * @group simplesamlphp_auth
 *
 * @coversDefaultClass \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager
 */
class SimplesamlphpAuthManagerTest extends UnitTestCase {

  /**
   * A mocked config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $config_factory;

  /**
   * A mocked SimpleSAML configuration instance.
   *
   * @var \SimpleSAML_Configuration|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $simplesamlConfig;

  /**
   * A mocked SimpleSAML instance.
   *
   * @var \SimpleSAML_Auth_Simple|\PHPUnit_Framework_MockObject_MockObject
   */
  public $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Set up default test configuration Mock object
    $this->config_factory = $this->getConfigFactoryStub(array(
      'simplesamlphp_auth.settings' => array(
        'auth_source' => 'default-sp',
        'register_users' => TRUE,
        'activate' => 1,
        'user_name' => 'name',
        'mail_attr' => 'mail',
        'unique_id' => 'uid',
      ),
    ));

    $this->instance = $this->getMockBuilder('\SimpleSAML_Auth_Simple')
      ->setMethods(array(
        'isAuthenticated',
        'requireAuth',
        'getAttributes',
        'logout',
      ))
      ->disableOriginalConstructor()
      ->getMock();

    $this->simplesamlConfig = $this->getMockBuilder('\SimpleSAML_Configuration')
      ->setMethods(array('getValue'))
      ->disableOriginalConstructor()
      ->getMock();
  }


  /**
   * @covers ::__construct
   * @covers ::isActivated
   */
  function testIsActivated() {
    // Test isActivated() method.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $return = $simplesaml->isActivated();
    $this->assertTrue($return);
  }

  /**
   * @covers ::__construct
   * @covers ::isAuthenticated
   */
  function testIsAuthenticated() {
    // Set expectations for instance.
    $this->instance->expects($this->once())
      ->method('isAuthenticated')
      ->will($this->returnValue(TRUE));

    // Test isAuthenticated() method.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $return = $simplesaml->isAuthenticated();
    $this->assertTrue($return);
  }

  /**
   * @covers ::__construct
   * @covers ::externalAuthenticate
   */
  function testExternalAuthenticate() {
    // Set expectations for instance.
    $this->instance->expects($this->once())
      ->method('requireAuth');

    // Test externalAuthenticate() method.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $simplesaml->externalAuthenticate();
  }

  /**
   * @covers ::__construct
   * @covers ::getStorage
   */
  function testGetStorage() {
    // Set expectations for config.
    $this->simplesamlConfig->expects($this->once())
      ->method('getValue')
      ->with($this->equalTo('store.type'))
      ->will($this->returnValue('sql'));

    // Test getStorage() method.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $return = $simplesaml->getStorage();
    $this->assertEquals('sql', $return);
  }

  /**
   * @covers ::__construct
   * @covers ::getAttributes
   * @covers ::getAttribute
   * @covers ::getAuthname
   * @covers ::getDefaultName
   * @covers ::getDefaultEmail
   */
  function testAttributes() {
    $data = array(
      'uid' => ['ext_user_123'],
      'name' => ['External User'],
      'mail' => ['ext_user_123@example.com'],
      'roles' => [['employee', 'webmaster']]
    );

    // Set expectations for instance.
    $this->instance->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($data));

    // Test attribute methods.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $this->assertEquals('ext_user_123', $simplesaml->getAuthname());
    $this->assertEquals('External User', $simplesaml->getDefaultName());
    $this->assertEquals('ext_user_123@example.com', $simplesaml->getDefaultEmail());
    $this->assertEquals(['employee', 'webmaster'], $simplesaml->getAttribute('roles'));
  }

  /**
   * @covers ::__construct
   * @covers ::getAttribute
   * @expectedException \Drupal\simplesamlphp_auth\Exception\SimplesamlphpAttributeException
   * @expectedExceptionMessage Error in simplesamlphp_auth.module: no valid "name" attribute set.
   */
  function testAttributesException() {
    // Set expectations for instance.
    $this->instance->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue(array('uid' => ['ext_user_123'])));

    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );
    $simplesaml->getAttribute('name');
  }

  /**
   * @covers ::__construct
   * @covers ::allowUserByAttribute
   */
  function testAllowUserByAttribute() {
    $data = array(
      'uid' => ['ext_user_123'],
      'name' => ['External User'],
      'mail' => ['ext_user_123@example.com'],
      'roles' => [['employee', 'webmaster']]
    );

    // Set expectations for instance.
    $this->instance->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($data));

    $container = new ContainerBuilder();
    $module_handler = $this->getMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->any())
      ->method('getImplementations')
      ->with($this->equalTo('simplesamlphp_auth_allow_login'))
      ->will($this->returnValue(array()));
    $container->set('module_handler', $module_handler);
    \Drupal::setContainer($container);

    // Test allowUserByAttribute method
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $return = $simplesaml->allowUserByAttribute();
    $this->assertTrue($return);
  }

  /**
   * @covers ::__construct
   * @covers ::logout
   */
  function testLogout() {
    $redirect_path = '<front>';

    // Set expectations for instance.
    $this->instance->expects($this->once())
      ->method('logout')
      ->with($this->equalTo($redirect_path));

    // Test logout() method.
    $simplesaml = new SimplesamlphpAuthManager(
      $this->config_factory,
      $this->instance,
      $this->simplesamlConfig
    );

    $simplesaml->logout($redirect_path);
  }
}
