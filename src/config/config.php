<?php
/*
 * DO NOT PUBLISH THE KEY, SECRET AND CERT TO CODE REPOSITORIES
 * FOR SECURITY. PLEASE USE LARAVEL'S .env.php FILES TO PROTECT
 * SENSITIVE DATA.
 * http://laravel.com/docs/configuration#protecting-sensitive-configuration
 */

return array(

	/*
	 * The location of the VATSIM OAuth interface
	 */
	'base' => '',

	/*
	 * The consumer key for your organisation (provided by VATSIM)
	 */
	'key' => '',

	 /*
	 * The secret key for your organisation (provided by VATSIM)
	 * Do not give this to anyone else or display it to your users. It must be kept server-side
	 */
	'secret' => '',

	/*
	 * The URL users will be redirected to after they log in, this should
	 * be on the same server as the request
	 */
	'return' => '',

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
	'cert' => ''
	
);