<?php namespace Vatsim\OAuth;

/**
 * @version 1.0a
 */

use Closure, Exception, InvalidArgumentException;
use Illuminate\Container\Container;

use Eher\OAuth\Consumer;
use Eher\OAuth\HmacSha1;
use Eher\OAuth\Request;

class SSO {
    
    /*
     * Location of the VATSIM SSO system 
     * Set in __construct
     */
    private $base = '';
    
    /*
     * Location for all OAuth requests
     */
    private $loc_api = 'api/';
    
    /*
     * Location for all login token requests
     */
    private $loc_token = 'login_token/';
    
    /*
     * Location to query for all user data requests (upon return of user login)
     */
    private $loc_return = 'login_return/';
    
    /*
     * Location to redirect the user to once we have generated a token
     */
    private $loc_login = 'auth/pre_login/?oauth_token=';
    
    /*
     * Format of the data returned by SSO, default json
     * Set in responseFormat method
     */
    private $format = 'json';
    
    /*
     * cURL timeout (seconds) for all requests
     */
    private $timeout = 10;
    
    /*
     * Holds the details of the most recent error in this class
     */
    private $error = array(
        'type'=>false,
        'message'=>false,
        'code'=>false
    );
    
    /*
     * The signing method being used to encrypt your request signature.
     * Set the 'signature' method
     */
    private $signature = false;
    
    /*
     * A request token genereted by (or saved to) the class
     */
    private $token = false;
    
    /*
     * Consumer credentials, instance of OAuthConsumer
     */
    private $consumer = false;

    /**
	 * The container instance.
	 *
	 * @var \Illuminate\Container\Container
	 */
	protected $container;
    
    /**
     * Configures the SSO class with consumer/organisation credentials
     * 
     * @param string $base            SSO Server URL
     * @param string $key             Organisation key
     * @param string $secret          Secret key corresponding to this organisation (only required if using HMAC)
     * @param string $method          RSA|HMAC
     * @param string $cert            openssl RSA private key (only required if using RSA)
     */
	public function __construct($base, $key, $secret = false, $method = false, $cert = false) {

		$this->container = new Container;

		$this->base = $base;
			
		// Store consumer credentials
		$this->consumer = new Consumer($key, $secret);
		
		// if signature method is defined, set the signature method now (can be set or changed later)
		if ($method){
			$this->signature($method, $cert);
		}
	}
	
    /**
     * Return or change the output format (returned by VATSIM)
     * 
     * @param string $change        json|xml
     * @return string               current format or bool false (unable to set format)
     */
	public function format($change = false) {
		
		// lower case values only
		$change = strtolower($change);
		
		// if set, attempt to change format
		if ($change){
			switch($change){
				// allowed formats, change to new format
				case "xml":
				case "json":
					$this->format = $change;
					break;
				// other formats now allowed/recognised
				default:
					return false;
					break;
			}
			
			// return the new format (string)
			return $this->format;
			
		} else { 
			// get and return the current format
			return $this->format;
		}
	}
	
    /**
     * Set the signing method to be used to encrypt request signature.
     * 
     * @param string $signature         Signature encryption method: RSA|HMAC
     * @param string $private_key       openssl RSA private key (only needed if using RSA)
     * @return boolean                  true if able to use this signing type
     */
	public function signature($signature, $private_key = false) {
		
		$signature = strtoupper($signature);
		
		// RSA-SHA1 public key/private key encryption
		if ($signature == 'RSA' || $signature == 'RSA-SHA1') {
			
			// private key must be provided
			if (!$private_key){
				return false;
			}
			
			// signature method set to RSA-SHA1 using this private key (interacts with OAuth class)
			$this->signature = new RsaSha1($private_key);
			
			return true;
			
		} elseif ($signature == 'HMAC' || $signature == 'HMAC-SHA1') {
			
			// signature method set to HMAC-SHA1 - no private key
			$this->signature = new HmacSha1;
			
			return true;
		} else {
			// signature method was not recognised
			return false;
		}
		
	}
	
    /**
     * Request a login token from VATSIM (required to send someone for an SSO login)
     * 
     * @param string $return_url        URL for VATSIM to return memers to after login
     * @param boolean $allow_sus        true to allow suspended VATSIM accounts to log in
     * @param boolean $allow_ina        true to allow inactive VATSIM accounts to log in
     * @return object|boolean
     */
	public function requestToken($return_url=false, $allow_sus=false, $allow_ina=false) {
		
		// signature method must have been set
		if (!$this->signature){
			return false;
		}
		
		// if the return URL isn't specified, assume this file (though don't consider GET data)
		if (!$return_url){
			// using https or http?
			$http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://';
			// the current URL
			$return_url = $http.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
		}
		
		$tokenUrl = $this->base.$this->loc_api.$this->loc_token.$this->format.'/';
		
		// generate a token request from the consumer details
		$req = Request::from_consumer_and_token($this->consumer, false, "POST", $tokenUrl, array(
			'oauth_callback' => $return_url,
			'oauth_allow_suspended' => ($allow_sus) ? true : false,
			'oauth_allow_inactive' => ($allow_ina) ? true : false
		));

		// sign the request using the specified signature/encryption method (set in this class)
		$req->sign_request($this->signature, $this->consumer, false);
		
		$response = $this->curlRequest($tokenUrl, $req->to_postdata());
		
		if ($response){
			// convert using our response format (depending upon user preference)
			$sso = $this->responseFormat($response);
			
			// did VATSIM return a successful result?
			if ($sso->request->result == 'success'){
					
				// this parameter is required by 1.0a spec
				if ($sso->token->oauth_callback_confirmed == 'true'){
					// store the token data saved
					$this->token = new Consumer($sso->token->oauth_token, $sso->token->oauth_token_secret);

					// return the full object to the user
					return $sso;
				} else {
					// no callback_confirmed parameter
					$this->error = array(
						'type' => 'callback_confirm',
						'code' => false,
						'message' => 'Callback confirm flag missing - protocol mismatch'
					);

					return false;
				}
				

			} else {
				
				// oauth returned a failed request, store the error details
				$this->error = array(
					'type' => 'oauth_response',
					'code' => false,
					'message' => $sso->request->message
				);
				
				return false;
				
			}
			
		} else {
			// cURL response failed
			return false;
		}
		
	}
	
    /**
     * Redirect the user to VATSIM to log in/confirm login
     * 
     * @return boolean              false if failed
     */
	public function sendToVatsim() {
		// a token must have been returned to redirect this user
		if (!$this->token){
			return false;
		}
		
		// redirect to the SSO login location, appending the token
		return $this->base . $this->loc_login . $this->token->key;
	}
	
	/**
     * Obtains a user's login details from a token key and secret
     * 
     * @param string $tokenKey      The token key provided by VATSIM
     * @param secret $tokenSecret   The secret associated with the token
     * @return object|false         false if error, otherwise returns user details
     */
	public function checkLogin($tokenKey, $tokenSecret, $tokenVerifier) {
		
		$this->token = new Consumer($tokenKey, $tokenSecret);
		
		// the location to send a cURL request to to obtain this user's details
		$returnUrl = $this->base.$this->loc_api.$this->loc_return.$this->format.'/';
		
		// generate a token request call using post data
		$req = Request::from_consumer_and_token($this->consumer, $this->token, "POST", $returnUrl, array(
			'oauth_token' => $tokenKey,
			'oauth_verifier' => $tokenVerifier
		));
		
		// sign the request using the specified signature/encryption method (set in this class)
		$req->sign_request($this->signature, $this->consumer, $this->token);
		
		// post the details to VATSIM and obtain the result
		$response = $this->curlRequest($returnUrl, $req->to_postdata());
		
		if ($response){
			// convert using our response format (depending upon user preference)
			$sso = $this->responseFormat($response);
			
			// did VATSIM return a successful result?
			if ($sso->request->result == 'success'){
				
				// one time use of tokens only, token no longer valid
				$this->token = false;
				
				// return the full object to the user
				return $sso;

			} else {
				
				// oauth returned a failed request, store the error details
				$this->error = array(
					'type' => 'oauth_response',
					'code' => false,
					'message' => $sso->request->message
				);
				
				return false;
				
			}
			
		} else {
			// cURL response failed
			return false;
		}
		
	}
	
	/**
     * Perform a (post) cURL request
     * 
     * @param type $url             Destination of request
     * @param type $requestString   Query string of data to be posted
     * @return boolean              true if able to make request
     */
	private function curlRequest($url, $requestString) {
		
		// using cURL to post the request to VATSIM
		$ch = curl_init();
		
		// configure the post request to VATSIM
		curl_setopt_array($ch, array(
			CURLOPT_URL => $url, // the url to make the request to
			CURLOPT_RETURNTRANSFER => 1, // do not output the returned data to the user
			CURLOPT_TIMEOUT => $this->timeout, // time out the request after this number of seconds
			CURLOPT_POST => 1, // we are sending this via post
			CURLOPT_POSTFIELDS => $requestString // a query string to be posted (key1=value1&key2=value2)
		));
		
		// perform the request
		$response = curl_exec($ch);
		
		// request failed?
		if (!$response){
			$this->error = array(
				'type' => 'curl_response',
				'code' => curl_errno($ch),
				'message' => curl_error($ch)
			);
			
			return false;
			
		} else {
			
			return $response;
			
		}
		
	}
	
	/**
     * Convert the response into a usable format
     * 
     * @param string $response      json|xml
     * @return object               Format processed into an object (Simple XML Element or json_decode)
     */
	private function responseFormat($response) {
		
		if ($this->format=='xml'){
			return new \SimpleXMLElement($response);
		} else {
			return json_decode($response);
		}
		
	}
	
	/**
     * Obtain the last generated error from this class
     * 
     * @return array                Array of the latest error
     */
	public function error() {
		return $this->error;
	}

	public function login($returnUrl, $success, $error = null)
	{
		if(is_array($returnUrl)) {
			$allow_sus = in_array('suspended', $returnUrl);
			$allow_ina = in_array('inactive', $returnUrl);
			
			$returnUrl = array_values(array_filter($returnUrl, function($val) {
				return filter_var($val, FILTER_VALIDATE_URL);
			}));

			if(count($returnUrl) == 0) {
				throw new InvalidArgumentException('No return URL can be found.');
			}
		} else {
			$allow_sus = false;
			$allow_ina = false;
		}

		if($token = $this->requestToken($returnUrl, $allow_sus, $allow_ina))
		{
			return $this->callResponse($success, array(
				(string) $token->token->oauth_token,
				(string) $token->token->oauth_token_secret,
				$this->sendToVatsim()
			));
		}
		else
		{
			if(is_null($error))
				return false;

			return $this->callResponse($error, array($this->error()));
		}
	}

	public function validate($key, $secret, $verifier, $success, $error = null)
	{
		if($request = $this->checkLogin($key, $secret, $verifier))
		{
			return $this->callResponse($success, array(
				$request->user,
				$request->request
			));
		}
		else
		{
			if(is_null($error))
				return false;

			return $this->callResponse($error, array($this->error()));
		}
	}

	protected function callResponse($callback, $parameters)
	{
		if ($callback instanceof Closure)
		{
			return call_user_func_array($callback, $parameters);
		}
		elseif (is_string($callback))
		{
			return $this->callClassBasedResponse($callback, $parameters);
		}
	}

	protected function callClassBasedResponse($callback, $parameters)
	{
		list($class, $method) = explode('@', $callback);

		return call_user_func_array(array($this->container->make($class), $method), $parameters);
	}
	
}