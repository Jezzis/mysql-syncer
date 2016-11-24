<?php
/**
 * Created by PhpStorm.
 * User: szj
 * Date: 16/11/24
 * Time: 09:50
 */

namespace Jezzis\MysqlSyncer;

use Illuminate\Support\ServiceProvider;

class MysqlSyncerServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    /**
     * Register the cache related console commands.
     *
     * @return void
     */
    public function registerCommands()
    {
        $this->app->singleton('command.db.sync', function ($app) {
            return new MysqlSyncerCommand();
        });

        $this->commands('command.db.sync');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.db.sync'];
    }
}
