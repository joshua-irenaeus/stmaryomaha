<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wp_stmaryomaha');

/** MySQL database username */
define('DB_USER', 'stmary_user');

/** MySQL database password */
define('DB_PASSWORD', 'C1rftdtddbdautittbl');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'wjO9u{[q@cQiMbe(*R<OoUr-)oc6&1wltc+,i5&#o*u%zOC3oiDO|^.[97:X8t|T');
define('SECURE_AUTH_KEY',  'pT)pDbKOzO1uG0/1[pGi[[8G-SNP6L(?Z+.-Q|wzvdZvxafmj&6iLu^eijL? U)5');
define('LOGGED_IN_KEY',    'JlKf8T[3,u/-n&e0e#$>Ml@.do8kJ()7yZ%}mTF-Q6*RR3D<r3~;yZ|K[e0pH#R1');
define('NONCE_KEY',        '0=HXl1>D4&@f4l$:Q~m,@KJ_H F~j$jG%ow+%-II&p{TLs$pS-981B;lkAa&/Ww!');
define('AUTH_SALT',        'UN#r5>0opw|SR=rmtfrBx%/R-m:%+3Xb87ttX3 !jX|>0;UNtDmvi=;lrintFb%A');
define('SECURE_AUTH_SALT', 'XFvlX)dO~FF]S)6a)E++5_ Ep-|60o8upAe!r=<B-.og|6z-%f-+/1.nT]&aCdqG');
define('LOGGED_IN_SALT',   'M~yYq:^-{&I#Gv})8VSXxhT+Vo?Qv=*0|rO0hb6{78j=KCLe*Ug#<A,bGA1cZ}dj');
define('NONCE_SALT',       'p|Kju=Ip#>H`;3^Mx)4o):=>TSwct<wII#</ezm`VOmV5?]-?hRuAyboCpP>nVQ{');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
