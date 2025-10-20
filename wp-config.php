<?php
define( 'WP_CACHE', true );
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'israwyfx_up1' );

/** Database username */
define( 'DB_USER', 'israwyfx_up1' );

/** Database password */
define( 'DB_PASSWORD', 'T7w-27-3zhqoBNigeg' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

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
define( 'AUTH_KEY',          '`J^/MC:vV(pxL&-w%`PpVP0!SY)38T}?@BAS1<1%/&dH&rat#k:ikSS]*[lxdg25' );
define( 'SECURE_AUTH_KEY',   '<{CH$4hym>4jmN55zF=zy&Ur>v7!2p<$$j}zQgK]^f;45ErQ1v(/V=E65z%Q]u9G' );
define( 'LOGGED_IN_KEY',     '5|##FjzT}3<O@Py=c>T&]xEyK-Mpc-Zx%jA*{E+4P.cNw{j<7}`0@SRU`q~ZHfpm' );
define( 'NONCE_KEY',         '7|raSF9O_Z0Ia:syt}%ry!&{2Fwz)jA=-YPF 17G1WjTv@[!X{&W5qvtaD+Xw3r/' );
define( 'AUTH_SALT',         '+9I`7s] V=XzW:J|y%Br{HLqYc0{cwqOCSt+u9L)6LqoFL,=alH-}E~^FO (z(Pa' );
define( 'SECURE_AUTH_SALT',  '*)$j;;)Nl<l4Dm=k~Xqn<^%AjqJt:]J&wLw0z9&6^&^YB/#WhH^r/9G/qgZ1noIW' );
define( 'LOGGED_IN_SALT',    '1cA,^!{fCSbm84q[xZp fdYrB:s#r>S1RmHM]OESDO[*V 8f}TF<!&|-l`tXQ{9e' );
define( 'NONCE_SALT',        'eWg9;`H((/h64O_~O6)/7A-jdsPU*$@Pj Au] ?TBets&NR$B&(e%rjq^Ur~KMSY' );
define( 'WP_CACHE_KEY_SALT', 'NLvONDXSNaP3&6PSQ_A7v.5ZSi44x.2*fYRYlJw4:8wLkf+bUzjObH:k:g-(E0>j' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'gmf_';


/* Add any custom values between this line and the "stop editing" line. */

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', true );
@ini_set( 'display_errors', 1 );

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
if ( ! defined( 'WP_DEBUG' ) ) {

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_DEBUG_LOG', true );
@ini_set( 'display_errors', 1 );
}

define( 'DISALLOW_FILE_EDIT', false ); 
define( 'WP_MEMORY_LIMIT', '256M' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';