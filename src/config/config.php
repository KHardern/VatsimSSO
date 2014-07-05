<?php
/*
 * DO NOT PUBLISH THE KEY, SECRET AND CERT TO CODE REPOSITORIES
 * FOR SECURITY. PLEASE USE LARAVEL'S .env.php FILES TO PROTECT
 * SENSITIVE DATA.
 * http://laravel.com/docs/configuration#protecting-sensitive-configuration
 *
 * Some sensible defaults have been provided so you can use .env files by adding
 * 'sso_key', 'sso_secret', and 'sso_cert' to your .env.php (production)
 *
 * Modify the three constants below to match the keys in your .env.php, otherwise it will use what you enter
 * on the second line of the key/secret/cert elements
 */

const SSO_KEY_KEY = 'sso_key';
const SSO_SECRET_KEY = 'sso_secret';
const SSO_CERT_KEY = 'sso_cert';

return array(

	/*
	 * The location of the VATSIM OAuth interface
	 */
	'base' => '',

	/*
	 * The consumer key for your organisation (provided by VATSIM)
	 */
	'key' => isset($_ENV[SSO_KEY_KEY]) ? $_ENV[SSO_KEY_KEY] :
		'', //if you aren't using .env files, modify this line

	 /*
	 * The secret key for your organisation (provided by VATSIM)
	 * Do not give this to anyone else or display it to your users. It must be kept server-side
	 */
	'secret' => isset($_ENV[SSO_SECRET_KEY]) ? $_ENV[SSO_SECRET_KEY] : 
		'', //if you aren't using .env files, modify this line


	/*
	 * The URL users will be redirected to after they log in, this should
	 * be on the same server as the request
	 */
	'return' => '', //not sensitive

	/*
	 * The signing method you are using to encrypt your request signature.
	 * Different options must be enabled on your account at VATSIM.
	 * Options: RSA / HMAC
	 */
	'method' => 'HMAC',

	/*
	 * Your RSA **PRIVATE** key
	 * If you are not using RSA, this value can be anything (or not set)
	 */
	'cert' => isset($_ENV[SSO_CERT_KEY]) ? $_ENV[SSO_CERT_KEY] : 
		'' //if you aren't using .env files, modify this line
	
);
