# SUMMARY

The simplesamlphp_auth module makes it possible for Drupal to support SAML for authentication of users. The module will 
auto-provision user accounts into Drupal if you want it to. It can also dynamically assign Drupal roles based on
identity attribute values.

# PREREQUISITES

1. You must have SimpleSAMLphp installed and configured as a working service point (SP) as the module uses your local 
SimpleSAMLphp SP for the SAML support. If you install the simplesamlphp_auth module with Composer support, you could use
the codebase that will be placed in your docroot/vendor/simplesamlphp/simplesamlphp directory (see "Installation" below)

You can also download and install SimpleSAMLphp separately. For more information on installing and configuring 
SimpleSAMLphp as an SP visit: http://www.simplesamlphp.org.

IMPORTANT: Your SP must be configured to use something other than phpsession for session storage (in config/config.php 
set store.type => 'memcache' or 'sql').

To use memcache session handling you must have memcached installed on your server and PHP must have the memcache 
extension. For more information on installing the memcache extension for PHP visit: 
http://www.php.net/manual/en/memcache.installation.php

If you are on a shared host or a machine that you cannot install memcache on then consider using the sql handler 
(store.type => 'sql').

<<<<<<< HEAD
2) You must have installed Composer Manager (https://www.drupal.org/project/composer_manager) and allowed it to
   download the simplesamlphp libraries. See README.txt in the composer_manager module for installation instructions.
=======
Make sure your SimpleSAMLphp installation has a correctly configured "config" and "metadata" folder, and an appropriate
vhost configuration. See http://www.simplesamlphp.org for more information.
   
2. You must have installed the ExternalAuth module (https://www.drupal.org/project/externalauth). See README.txt in the
externalauth module for installation instructions.
   
3. It is recommended to have Composer Manager (https://www.drupal.org/project/composer_manager) module installed and 
allow it to download the simplesamlphp libraries. See README.txt in the composer_manager module for installation 
instructions. If Composer is not an option for you, see the installation instructions below to link your SimpleSAMLphp
instance with Drupal through settings.php
 
# INSTALLATION

The Drupal simplesamlphp_auth module will need to connect to a working SimpleSAMLphp instance. This can be done in two
ways - depending on your setup:

## INSTALLATION WITH COMPOSER MANAGER
>>>>>>> e494da6... #2632324 - add alternative way to link SimpleSAMLphp instance through settings.php, rather than using the Composer-loaded library

Make sure you have the composer_manager module installed according to its README.txt.

1. Download the simplesamlphp_auth module
2. Uncompress it
3. Move it to the appropriate modules directory (usually, /modules)
4. Run the "composer drupal-update" command (see Prerequisites above)
5. The SimpleSAMLphp library will now be installed in your docroot/vendor/simplesamlphp/simplesamlphp directory.
Configure the library (see http://www.simplesamlphp.org) by adding the 'config' and 'metadata' directories with 
appropriate settings. If is recommended to symlink those from your already installed SimpleSAMLphp instance or from 
another location where they are saved.
6. Go to the Drupal module administration page for your site
7. Enable the module
8. Configure the module (see below)

## INSTALLATION WITHOUT COMPOSER

1. Make sure you have a working SimpleSAMLphp installation.
2. Download the simplesamlphp_auth module
3. Uncompress it
4. Move it to the appropriate modules directory (usually, /modules)
5. In your settings.php file, add the location of your SimpleSAMLphp installation (no trailing slashes):

   e.g.:
   $settings['simplesamlphp_dir'] = '/var/www/simplesamlphp';

6. Go to the Drupal module administration page for your site
7. Enable the module
8. Configure the module (see below)

# CONFIGURATION

The configuration of the module is fairly straight forward. You will need to know the names of the attributes that your 
SP will be making available to the module in order to map them into Drupal.

An additional step is required to allow access to SimpleSAMLphp paths within the .htaccess for the Drupal 8 version of 
this module. Add in the lines below at the appropriate place within the Drupal 8 .htaccess or the configuration will 
cause permission denied errors.

  # Copy and adapt this rule to directly execute PHP files in contributed or
  # custom modules or to run another PHP application in the same directory.
  RewriteCond %{REQUEST_URI} !/core/modules/statistics/statistics.php$
+ # Allow access to simplesaml paths
+ RewriteCond %{REQUEST_URI} !^/simplesaml
  # Deny access to any other PHP files that do not match the rules above.
  RewriteRule "^.+/.*\.php$" - [F]


# TROUBLESHOOTING

The most common reason for things not working is the SP session storage type is still set to phpsession.
