<?php namespace Fadion\Bouncy;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\Client as ElasticSearch;
use Illuminate\Support\Facades\Config;

class BouncyServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('fadion/bouncy');

        // Register the package's config.
        $this->app['config']->package('fadion/bouncy', __DIR__.'/../../config', 'fadion/bouncy');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('elastic', function() {
            return new ElasticSearch(Config::get('bouncy::elasticsearch'));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

}