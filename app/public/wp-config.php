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
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

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
define( 'AUTH_KEY',          'pF5!LG_Y|Cha:bL.Z3Ka<0p_<l^MBc&S>!<y~.;h@]fddh#XIOVv uZjD,1d$K3m' );
define( 'SECURE_AUTH_KEY',   '-l4;6MvX][&^G,UWGe`0}%Nsrr[!Dfwz:w,|?yv4x7*qMo}h&r83nhJ85oy 4 xf' );
define( 'LOGGED_IN_KEY',     'I;=eHuRz!yOKGQ22oJk*:1ORSprU!&s@[fVrN:dZZ[ 7.%J+q<rN4rO>[e)}8!8&' );
define( 'NONCE_KEY',         '[(:4YygPuGH>%8};KJwu+p@Lu2tA+05Q[PU UWt{E!YT{qPi#D|k/$%u;r2l$IV$' );
define( 'AUTH_SALT',         'pBi*QLo3XLpd=j4T dXQZIi;k]`J[lz=^.=2|x]uUM[1vf3%]wVWcFw#+.4f`^ u' );
define( 'SECURE_AUTH_SALT',  ')p4WQ q8*}$R|!=sz!<lRuuc17$y-V%8P8nLV28DkiE;2.-(FiztijoQr{.AJ;5x' );
define( 'LOGGED_IN_SALT',    '3t8fg)XYm;D.9!%ZqZ4 0yU@:Qnn<<lAvtr^wiNd0rNXzUIW]K5+PVq$wi}%oQ49' );
define( 'NONCE_SALT',        'fG>E$>U$d1s2PX4IjI&A#.Csn$Wnu2l!J189io[[c1fX SO(s`pTL [RkwX+uOsw' );
define( 'WP_CACHE_KEY_SALT', '&ipJOM;xZa`(ndcJ7h~3^O^mXhDG:><-jy~K13g@RQGbU~[2H1~a#k>@2w6FjVXF' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
