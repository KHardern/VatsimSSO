<?php namespace Vatsim\OAuth;

use Illuminate\Support\ServiceProvider;

class OAuthServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('vatsim/sso');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['vatsimoauth'] = $this->app->share(function($app)
		{
			return new SSO(
				$app['config']->get('sso::base'), // base
				$app['config']->get('sso::key'), // key
				$app['config']->get('sso::secret'), // secret
				$app['config']->get('sso::method'), // method
				$app['config']->get('sso::cert') // certificate 
			);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('vatsimoauth');
	}

}