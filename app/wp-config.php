<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', $_ENV['WP_DB_NAME']);

/** MySQL database username */
define('DB_USER', $_ENV['WP_DB_USER']);

/** MySQL database password */
define('DB_PASSWORD', $_ENV['WP_DB_PASSWORD']);

/** MySQL hostname */
define('DB_HOST', $_ENV['WP_DB_HOST']);

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('DISALLOW_FILE_EDIT', true);

define('AS3CF_SETTINGS', serialize(array(
	'provider' => 'aws',
	'access-key-id' => $_ENV['WP_S3_ACCESS_KEY'],
	'secret-access-key' => $_ENV['WP_S3_SECRET_KEY'],
	'bucket' => $_ENV['WP_S3_BUCKET']
)));

define('WP_MEMORY_LIMIT', '512M');

define('FORCE_SSL', true);
define('FORCE_SSL_ADMIN', true);
$_SERVER['HTTPS'] = 'on';


/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '^Q!rc$`Z;H$ptPUDWsJn(8[8+cikbAipZ!B(af,&%`$%6YKTWyo%Ge%<tPadtg&>');
define('SECURE_AUTH_KEY',  '+`P.`FM*62(O>t*G(!2.jV&3)}++7:sA-E/ZfRN g&X<$xviDM);Hd0qcGY}/OGi');
define('LOGGED_IN_KEY',    'vfS6N6z_&X;lgr0_K_3y3QG%R _nspAWlW8-NTg*mM &U7]@37<mh-#*,PpqW}Y;');
define('NONCE_KEY',        'ju=qE?mYJfa=bi@F9UhLZcuQPS/-#dHo K_4z%3;g)8RJ(w[FhmQ<w]YA3rQDpX:');
define('AUTH_SALT',        'RVr#4j) 3ek(O!GaZ3lY>8byFH4|#,$Wc m<|>Z+1Pg`)<I=+d/rLp+%2D([%7sr');
define('SECURE_AUTH_SALT', '`i+mzA<TWNv=i)xO6YiCE7o{e2U&jTtQI(LG;[6`E+4I.l<96]G&f3M/]8]*R}C]');
define('LOGGED_IN_SALT',   'tkwhIFB;8&314+2uOt)z~qU>+5~7`>Hmj_.<3m$B^=W/`yBdJ,J!O~F$D%BSAU-W');
define('NONCE_SALT',       'bNo`@fD`N*y>X+1-e34wca/$J%}oDKW]QK0[R=96@VKr/S2<(2@oH}Ap,B{Q6px_');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */

define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true);

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
