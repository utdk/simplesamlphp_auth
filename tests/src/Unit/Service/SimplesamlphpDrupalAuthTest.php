<?php

/**
 * @file
 * Contains Drupal\Tests\simplesamlphp_auth\Unit\Service\SimplesamlphpDrupalAuthTest.
 */

namespace Drupal\Tests\simplesamlphp_auth\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth;

/**
 * SimplesamlphpDrupalAuth unit tests.
 *
 * @ingroup simplesamlphp_auth
 * @group simplesamlphp_auth
 *
 * @coversDefaultClass \Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth
 */
class SimplesamlphpDrupalAuthTest extends UnitTestCase {
  /**
   * The mocked SimpleSAMLphp Authentication helper
   *
   * @var \Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $simplesaml;

  /**
   * The mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $connection;

  /**
   * The mocked Entity Manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The mocked logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The mocked config factory instance.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $config_factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a Mock database connection object.
    $this->connection = $this->getMockBuilder('\Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();

    // Create a Mock EntityManager object.
    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');

    // Create a Mock Logger object.
    $this->logger = $this->getMockBuilder('\Psr\Log\LoggerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Create a Mock SimplesamlphpAuthManager object.
    $this->simplesaml = $this->getMockBuilder('\Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager')
      ->disableOriginalConstructor()
      ->getMock();

    // Set up default test configuration Mock object
    $this->config_factory = $this->getConfigFactoryStub(array(
      'simplesamlphp_auth.settings' => array(
        'register_users' => TRUE,
        'activate' => 1,
      ),
    ));

    // Create a Mock User object to test against.
    $this->entity_account = $this->getMock('Drupal\user\UserInterface');
  }

  /**
   * Test external load functionality
   *
   * @covers ::externalLoad
   * @covers ::__construct
   */
  public function testExternalLoad() {
    // Mock the User storage layer.
    $entity_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
    // Expect the external loading method to return a user object.
    $entity_storage->expects($this->once())
      ->method('load')
      ->will($this->returnValue($this->entity_account));

    $this->entityManager->expects($this->once())
      ->method('getStorage')
      ->will($this->returnValue($entity_storage));

    // Set up a mock for SimplesamlphpDrupalAuth class,
    // mocking getUserIdforAuthname() and externalRegister() methods
    $simplesaml_drupalauth = $this->getMockBuilder('Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth')
      ->setMethods(array('getUserIdforAuthname', 'externalRegister'))
      ->setConstructorArgs(array(
        $this->simplesaml,
        $this->config_factory,
        $this->connection,
        $this->entityManager,
        $this->logger
      ))
      ->getMock();

    // Mock some methods on SimplesamlphpDrupalAuth, since they are out of scope
    // of this specific unit test.
    $simplesaml_drupalauth->expects($this->once())
      ->method('getUserIdforAuthname')
      ->will($this->returnValue(2));
    $simplesaml_drupalauth->expects($this->never())
      ->method('externalRegister');

    // Now that everything is set up, call externalLoad() and expect a User.
    $loaded_account = $simplesaml_drupalauth->externalLoad("testuser");
    $this->assertTrue($loaded_account instanceof UserInterface);
  }

  /**
   * Tests external login with role matching.
   *
   * @covers ::externalLogin
   * @covers ::externalLoginFinalize
   * @covers ::roleMatchAdd
   * @covers ::evalRoleRule
   * @covers ::__construct
   */
  public function testExternalLoginWithRoleMatch() {
    // Set up specific configuration to test external login & role matching.
    $config_factory = $this->getConfigFactoryStub(array(
      'simplesamlphp_auth.settings' => array(
        'register_users' => TRUE,
        'activate' => 1,
        'role.eval_every_time' => 1,
        'role.population' => 'student:eduPersonAffiliation,=,student',
      ),
    ));

    // Get a Mock User object to test the external login method.
    // Expect the role "student" to be added to the user entity.
    $this->entity_account->expects($this->once())
      ->method('addRole')
      ->with($this->equalTo('student'));
    $this->entity_account->expects($this->once())
      ->method('save');

    // Create a Mock SimplesamlphpAuthManager object.
    $simplesaml = $this->getMockBuilder('\Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager')
      ->disableOriginalConstructor()
      ->setMethods(array('getAttributes'))
      ->getMock();

    // Mock the getAttributes() method on SimplesamlphpAuthManager.
    $attributes = array('eduPersonAffiliation' => array('student'));
    $simplesaml->expects($this->once())
      ->method('getAttributes')
      ->will($this->returnValue($attributes));

    // Set up a mock for SimplesamlphpDrupalAuth class,
    // mocking getUserIdforAuthname() and externalRegister() methods
    $simplesaml_drupalauth = $this->getMockBuilder('Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth')
      ->setMethods(array('externalLoginFinalize'))
      ->setConstructorArgs(array(
        $simplesaml,
        $config_factory,
        $this->connection,
        $this->entityManager,
        $this->logger
      ))
      ->getMock();

    $simplesaml_drupalauth->expects($this->once())
      ->method('externalLoginFinalize');

    // Now that everything is set up, call externalLogin() and expect a User.
    $simplesaml_drupalauth->externalLogin($this->entity_account);
  }

  /**
   * Test external registration functionality
   *
   * @covers ::externalRegister
   * @covers ::__construct
   */
  public function testExternalRegister() {
    // Mock the User storage layer to create us a new user.
    $entity_storage = $this->getMock('Drupal\Core\Entity\EntityStorageInterface');
    // Expect the external registration to return us a user object.
    $entity_storage->expects($this->any())
      ->method('create')
      ->will($this->returnValue($this->entity_account));
    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->will($this->returnValue(FALSE));

    $this->entityManager->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($entity_storage));

    // Set up a mock for SimplesamlphpDrupalAuth class,
    // mocking synchronizeUserAttributes() and saveAuthmap() methods
    $simplesaml_drupalauth = $this->getMockBuilder('Drupal\simplesamlphp_auth\Service\SimplesamlphpDrupalAuth')
      ->setMethods(array('synchronizeUserAttributes', 'saveAuthmap'))
      ->setConstructorArgs(array(
        $this->simplesaml,
        $this->config_factory,
        $this->connection,
        $this->entityManager,
        $this->logger
      ))
      ->getMock();

    // Mock some methods on SimplesamlphpDrupalAuth, since they are out of scope
    // of this specific unit test.
    $simplesaml_drupalauth->expects($this->once())
      ->method('synchronizeUserAttributes');
    $simplesaml_drupalauth->expects($this->once())
      ->method('saveAuthmap');

    // Now that everything is set up, call externalRegister() and expect a User.
    $registered_account = $simplesaml_drupalauth->externalRegister("testuser");
    $this->assertTrue($registered_account instanceof UserInterface);
  }

  /**
   * Test user attribute syncing.
   *
   * @covers ::synchronizeUserAttributes
   */
  public function testSynchronizeUserAttributes() {
    // Create a Mock SimplesamlphpAuthManager object.
    $simplesaml = $this->getMockBuilder('\Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager')
      ->disableOriginalConstructor()
      ->setMethods(array('getDefaultName', 'getDefaultEmail'))
      ->getMock();

    // Mock the getDefaultName() & getDefaultEmail methods.
    $simplesaml->expects($this->once())
      ->method('getDefaultName')
      ->will($this->returnValue("Test name"));
    $simplesaml->expects($this->once())
      ->method('getDefaultEmail')
      ->will($this->returnValue("test@example.com"));

    // Get a Mock User object to test the user attribute syncing.
    $this->entity_account->expects($this->once())
      ->method('setUsername')
      ->with($this->equalTo("Test name"));
    $this->entity_account->expects($this->once())
      ->method('setEmail')
      ->with($this->equalTo("test@example.com"));
    $this->entity_account->expects($this->once())
      ->method('save');

    $simplesaml_drupalauth = new SimplesamlphpDrupalAuth(
      $simplesaml,
      $this->config_factory,
      $this->connection,
      $this->entityManager,
      $this->logger
    );

    $simplesaml_drupalauth->synchronizeUserAttributes($this->entity_account, TRUE);
  }

  /**
   * Test role matching logic
   *
   * @covers ::getMatchingRoles
   * @covers ::evalRoleRule
   *
   * @dataProvider roleMatchingDataProvider
   */
  public function testRoleMatching($rolemap, $attributes, $expected_roles) {
    // Set up specific configuration to test role matching.
    $config_factory = $this->getConfigFactoryStub(array(
      'simplesamlphp_auth.settings' => array(
        'register_users' => TRUE,
        'activate' => 1,
        'role.population' => $rolemap,
      ),
    ));

    // Create a Mock SimplesamlphpAuthManager object.
    $simplesaml = $this->getMockBuilder('\Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager')
      ->disableOriginalConstructor()
      ->setMethods(array('getAttributes'))
      ->getMock();

    // Mock the getAttributes() method on SimplesamlphpAuthManager.
    $simplesaml->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($attributes));

    $simplesaml_drupalauth = new SimplesamlphpDrupalAuth(
      $simplesaml,
      $config_factory,
      $this->connection,
      $this->entityManager,
      $this->logger
    );

    $matching_roles = $simplesaml_drupalauth->getMatchingRoles();
    $this->assertEquals(count($expected_roles), count($matching_roles), 'Number of expected roles matches');
    $this->assertEquals($expected_roles, $matching_roles, 'Expected roles match');
  }

  /**
   * Provides test parameters for testRoleMatching
   *
   * @return array
   *   Parameters
   *
   * @see \Drupal\Tests\simplesamlphp_auth\Unit\Service\SimplesamlphpDrupalAuthTest::testRoleMatching
   */
  public function roleMatchingDataProvider() {
    return array(
      // test matching of exact attribute value
      array(
        'admin:userName,=,externalAdmin|test:something,=,something',
        array('userName' => array('externalAdmin')),
        array('admin')
      ),
      // test matching of attribute portion
      array(
        'employee:mail,@=,company.com',
        array('mail' => array('joe@company.com')),
        array('employee')
      ),
      // test non-matching of attribute portion
      array(
        'employee:mail,@=,company.com',
        array('mail' => array('joe@anothercompany.com')),
        array()
      ),
      // test matching of any attribute portion
      array(
        'employee:affiliate,~=,xyz',
        array('affiliate' => array('abcd', 'wxyz')),
        array('employee')
      ),
      // test multiple roles
      array(
        'admin:userName,=,externalAdmin|employee:mail,@=,company.com',
        array('userName' => array('externalAdmin'), 'mail' => array('externalAdmin@company.com')),
        array('admin', 'employee')
      ),
      // test special characters (colon) in attribute
      array(
        'admin:domain,=,http://admindomain.com',
        array('domain' => array('http://admindomain.com', 'http://drupal.org')),
        array('admin')
      ),
    );
  }

}
