<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use Two\Database\ORM\Model;

use Two\Database\DatabaseManager;
use Two\Database\ConnectionFactory;

use Two\Application\Providers\ServiceProvider;


class DatabaseServiceProvider extends ServiceProvider
{

    /**
     * Amorcez les événements de l'application.
     *
     * @return void
     */
    public function boot()
    {
        $db = $this->app['db'];

        $events = $this->app['events'];

        // Configurez le modèle ORM.
        Model::setConnectionResolver($db);

        Model::setEventDispatcher($events);
    }

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('db.factory', function($app)
        {
            return new ConnectionFactory($app);
        });

        $this->app->singleton('db', function($app)
        {
            return new DatabaseManager($app, $app['db.factory']);
        });
    }
}
