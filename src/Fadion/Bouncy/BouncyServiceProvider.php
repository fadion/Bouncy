<?php namespace Fadion\Bouncy;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\Client as ElasticSearch;

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
        $this->publishes(array(
            __DIR__.'/../../config/config.php' => config_path('bouncy.php'),
            __DIR__.'/../../config/elasticsearch.php' => config_path('elasticsearch.php')
        ));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/config.php',
            'bouncy'
        );

        $this->app->singleton('elastic', function($app) {
            return new ElasticSearch($app['config']->get('elasticsearch'));
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('elastic');
    }

}
