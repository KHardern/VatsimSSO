VatsimSSO
=========

**Version:** 3.0

The VatsimSSO package integrates with the VATSIM.net Single Sign On, which lets your users log themselves in using their VATSIM ID. This is especially useful for official vACCs and ARTCCs.

**For Laravel integration, see VatsimSSO for Laravel.**

Installation
--------------

Use [Composer](http://getcomposer.org) to install the VatsimSSO and dependencies.

```sh
$ composer require vatsim/sso 3.*
```


### Set up
Let's first create a configuration file to keep our code clean.
```php
/*
 * DO NOT PUBLISH THE KEY, SECRET AND CERT TO CODE REPOSITORIES
 * FOR SECURITY.
 */

/*
 * The location of the VATSIM OAuth interface
 */
$base = 'https://';

/*
 * The consumer key for your organisation (provided by VATSIM)
 */
$key = 'MY_KEY';

/*
 * The secret key for your organisation (provided by VATSIM)
 * Do not give this to anyone else or display it to your users. It must be kept server-side
 */
$secret = 'my_secret';

/*
 * The signing method you are using to encrypt your request signature.
 * Different options must be enabled on your account at VATSIM.
 * Options: RSA / HMAC
 */
$method = 'HMAC';

/*
 * Your RSA **PRIVATE** key
 * If you are not using RSA, this value can be anything (or not set)
 */
$cert = '';

/*
 * The URL users will be redirected to after they log in, this should
 * be on the same server as the request
 */
$return = 'http://example.com/valiatelogin';

$additionalConfig = [
    'allow_suspended' => false,
    'allow_inactive' => true,
];
```

Now, let's initialise the SSO class.
```php
// load the Composer autoload file, which automatically
// loads all the classes required for use by VatsimSSO.
require 'vendor/autoload.php';
require 'config.php';

use Vatsim\OAuth\SSO;

$sso = new SSO($base, $key, $secret, $method, $cert, $additionalConfig);
```

## Usage
### Logging In
The first step would be to send a request to VATSIM to let the user login. The easiest approach would be using the `login` function. The function takes three parameters.
#### Parameters
| Parameter       | Type   | Description |
| --------------- | ------ | ----------- |
| `$returnUrl`    | string | The URL to which the user should be redirected after the login is successful |
| `$success`      | Closure | Callback function containing the actions needed to be done when you are able to let the user authenticate (ie. when your key/secret are correct). The function will return three variables: `$key`, `$secret` and `$url`. |
| *`$error`*      | Closure | *Default: null* – Callback function for error handling. The function will provide one argument: an instance of `VATSIM\OAuth\SSOException`. If no callback is provided, the `SSOException` will be thrown. |

#### Success
The success parameter provides three arguments: `$key`, `$secret` and `$url`. The `key` and `secret` should be stored in a session for the validation process. The `url` will be used to redirect the user to the VATSIM SSO site.

#### Error
Optional parameter. If this parameter is ignored and an error occurs, a `SSOException` will be thrown. If you pass a function then one parameter will be returned `$error`, which is the instance of `SSOException`.

#### Example
```php
$sso->login(
    $return,
    function($key, $secret, $url) {
        $_SESSION['vatsimauth'] = compact('key', 'secret');
        header('Location: ' . $url);
        die();
    }
);
```

If you prefer not to use the `->login()` function, you may use `->requestToken($returnUrl)`. This will return an object containing the `key` and `secret` or throw `VATSIM\OAuth\SSOException` if an error occurs. Then use `->redirectUrl()` to get the URL for the redirect.

### Validating login
After the login has been successful, we need to get the user data from VATSIM. Also for this we wrote a function to make it easier for you.
#### Parameters
| Parameter       | Type   | Description |
| --------------- | ------ | ----------- |
| `$key`          | string | The `key` stored in the session at login |
| `$secret`       | string | The `secret` stored in the session at login |
| `$verifier`     | string | The `oauth_verifier` passed in the query string |
| `$success`      | Closure | Callback function containing the actions needed to be done when the login has been successful. |
| *`$error`*      | Closure | *Default: null* – Callback function for error handling (could be because of wrong key/secret/verifier). The function will provide one argument: an instance of `VATSIM\OAuth\SSOException`. If no callback is provided, the `SSOException` will be thrown. |

#### Success
The success parameter returns two variables: `$user` and `$request`. The `user` variable will be an object containing all user data available to your organisation. The `request` variable will give you information about the request.

#### Error
Optional parameter. If this parameter is ignored and an error occurs, a `SSOException` will be thrown. If you pass a function then one parameter will be returned `$error`, which is the instance of `SSOException`.

#### Example
```php
$session = $_SESSION['vatsimauth'];

$sso->validate(
    $session['key'],
    $session['secret'],
    $_GET['oauth_verifier'],
    function($user, $request) {
        // At this point we can remove the session data.
        unset($_SESSION['vatsimauth']);
        
        // do something to log the user in on your site using the user id
        // $user->id
        
        // Redirect home
        header('Location: /');
        die();
    }
);
```

If you prefer not to use the `->validate()` function, you may use `->checkLogin($key, $secret, $verifier)`. This will return an object containing the `user` and `request` objects or throw `VATSIM\OAuth\SSOException` if an error occurs.

License
----

MIT

**Free Software, Hell Yeah!**
