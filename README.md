VatsimSSO
=========

The VatsimSSO package integrates with the VATSIM.net Single Sign On, which lets your users log themselves in using their VATSIM ID. This is especially useful for official vACCs and ARTCCs.

Version
----

1.0

Installation
--------------

Use [Composer](http://getcomposer.org) to install the VatsimSSO and dependencies.

```sh
$ composer require vatsim/sso 1.*
```

### Laravel
#### Set up
Using VatsimSSO in Laravel is made easy through the use of Service Providers. Add the service provider to your `app/config/app.php` file:
```php
'providers' => array(
    // ...
    'Vatsim\OAuth\OAuthServiceProvider',
),
```

Followed by the alias:
```php
'aliases' => array(
    // ...
    'VatsimSSO'       => 'Vatsim\OAuth\Facades\SSO',
),
```

#### Configuration file
Use artisan to publish the configuration file. After running the command you will find the file in `app/config/packages/vatsim/sso/config.php`. Change the settings accordingly.
```sh
$ artisan config:publish vatsim/sso
```

### Outside Laravel
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
```

Now, let's initialise the SSO class.
```php
// load the Composer autoload file, which automatically
// loads all the classes required for use by VatsimSSO.
require 'vendor/autoload.php';
require 'config.php';

use Vatsim\OAuth\SSO;

$sso = new SSO($base, $key, $secret, $method, $cert);
```

## Usage
### Logging In
The first step would be to send a request to VATSIM to let the user login. The easiest approach would be using the `login` function. The function takes three parameters.
#### Parameters
| Parameter       | Type   | Description |
| --------------- | ------ | ----------- |
| `$returnUrl`    | string &#124; array | The URL to which the user should be redirected after the login is successful |
| `$success`      | string &#124; Closure | Callback function containing the actions needed to be done when you are able to let the user authenticate (ie. when your key/secret are correct). The function will return three variables: `$key`, `$secret` and `$url`. |
| *`$error`*      | string &#124; Closure | *Default: null* – Callback function containing the actions needed to be done when your credentials (ie. key/secret) are incorrect. |

For both `$success` and `$error`, you may pass a string in `[class]@[method]` format to call a function in another Model, otherwise pass an anonymous function.

#### Return URL
The return URL parameter will also take an array instead of a string. In this array you can add the values `suspended` and/or `inactive` to allow members with suspended or inactive accounts to log in. The first element of this array that is a valid URL will be used as the return URL.

#### Success
The success parameter returns three variables: `$key`, `$secret` and `$url`. The `key` and `secret` should be stored in a session for the validation process. The `url` will be used to redirect the user to the VATSIM SSO site.

#### Error
Optional parameter. If this parameter is ignored and an error occurs, the function will return `false`. If you pass a function then one parameter will be returned `$error`, which is an array of data related to the last error.

#### Example
```php
// Laravel
return VatsimSSO::login(
    Config::get('vatsimsso:return'),
    function($key, $secret, $url) {
        Session::put('vatsimauth', compact('key', 'secret');
        return Redirect::to($url);
    },
    function($error) {
        throw new Exception('Could not authenticate: ' . $error['message']);
    }
);

// Outside Laravel
$sso->login(
    $return,
    function($key, $secret, $url) {
        $_SESSION['vatsimauth'] = compact('key', 'secret');
        header('Location: ' . $url);
        die();
    }
);
```

If you prefer not to use the `->login()` function, you may use `->requestToken($returnUrl)`. This will return an object containing the `key` and `secret` or returns `false` if an error occurs, at that point you can use `->error()` to get the array of the last occured error. Then use `->sendToVatsim()` to get the URL for the redirect.

### Validating login
After the login has been successful, we need to get the user data from VATSIM. Also for this we wrote a function to make it easier for you.
#### Parameters
| Parameter       | Type   | Description |
| --------------- | ------ | ----------- |
| `$key`          | string | The `key` stored in the session at login |
| `$secret`       | string | The `secret` stored in the session at login |
| `$verifier`     | string | The `oauth_verifier` passed in the query string |
| `$success`      | string &#124; Closure | Callback function containing the actions needed to be done when the login has been successful. |
| *`$error`*      | string &#124; Closure | *Default: null* – Callback function containing the actions needed to be done when authentication was unsuccessful (could be because of wrong key/secret/verifier) |

For both `$success` and `$error`, you may pass a string in `[class]@[method]` format to call a function in another Model, otherwise pass an anonymous function.

#### Success
The success parameter returns two variables: `$user` and `$request`. The `user` variable will be an object containing all user data available to your organisation. The `request` variable will give you information about the request.

#### Error
Optional parameter. If this parameter is ignored and an error occurs, the function will return `false`. If you pass a function then one parameter will be returned `$error`, which is an array of data related to the last error.

#### Example
```php
// Laravel
$session = Session::get('vatsimauth');

return VatsimSSO::validate(
    $session['key'],
    $session['secret'],
    Input::get('oauth_verifier'),
    function($user, $request) {
        // At this point we can remove the session data.
        Session::forget('vatsimauth');
        
        Auth::loginUsingId($user->id);
        return Redirect::home();
    },
    function($error) {
        throw new Exception('Could not authenticate: ' . $error['message']);
    }
);

// Outside Laravel
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

If you prefer not to use the `->validate()` function, you may use `->checkLogin($key, $secret, $verifier)`. This will return an object containing the `user` and `request` objects or returns `false` if an error occurs, at that point you can use `->error()` to get the array of the last occured error.


License
----

MIT

**Free Software, Hell Yeah!**