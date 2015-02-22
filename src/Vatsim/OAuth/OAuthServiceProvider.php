<?php namespace Vatsim\OAuth;

use Illuminate\Support\ServiceProvider;

class OAuthServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	protected function dir($path) {
		return __DIR__.'/../../' . $path;
	}

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			$this->dir('config/config.php') => config_path('vatsim-sso.php')
		]);
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
				$app['config']->get('vatsim-sso.base'), // base
				$app['config']->get('vatsim-sso.key'), // key
				$app['config']->get('vatsim-sso.secret'), // secret
				$app['config']->get('vatsim-sso.method'), // method
				$app['config']->get('vatsim-sso.cert') // certificate 
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