<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Two\Queue\CallQueuedClosure;

use Two\Queue\Connectors\SqsConnector;
use Two\Queue\Connectors\IronConnector;
use Two\Queue\Connectors\NullConnector;
use Two\Queue\Connectors\SyncConnector;
use Two\Queue\Connectors\RedisConnector;
use Two\Queue\Connectors\DatabaseConnector;
use Two\Queue\Failed\NullFailedJobProvider;
use Two\Queue\Connectors\BeanstalkdConnector;
use Two\Queue\Failed\DatabaseFailedJobProvider;
use Two\Application\Providers\ServiceProvider;
use Two\Console\Forge\Queue\Commands\WorkCommand;
use Two\Console\Forge\Queue\Commands\ListenCommand;
use Two\Console\Forge\Queue\Commands\RestartCommand;
use Two\Console\Forge\Queue\Commands\SubscribeCommand;


class QueueServiceProvider extends ServiceProvider
{

    /**
     * Indique si le chargement du fournisseur est différé.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerManager();

        $this->registerWorker();

        $this->registerListener();

        $this->registerSubscriber();

        $this->registerFailedJobServices();

        $this->registerQueueClosure();
    }

    /**
     * Enregistrez le gestionnaire de files d'attente.
     *
     * @return void
     */
    protected function registerManager()
    {
        $this->app->bindShared('queue', function($app)
        {

            // Une fois que nous aurons une instance du gestionnaire de files d'attente, nous enregistrerons les différents
            // résolveurs pour les connecteurs de file d'attente. Ces connecteurs sont responsables de
            // création des classes qui acceptent les configurations de file d'attente et instancient les files d'attente.
            $manager = new QueueManager($app);

            $this->registerConnectors($manager);

            return $manager;
        });

        $this->app->singleton('queue.connection', function ($app) {
            return $app['queue']->connection();
        });
    }

    /**
     * Enregistrez le gestionnaire de file d'attente.
     *
     * @return void
     */
    protected function registerWorker()
    {
        $this->registerWorkCommand();

        $this->registerRestartCommand();

        $this->app->bindShared('queue.worker', function($app)
        {
            return new Worker($app['queue'], $app['queue.failer'], $app['events']);
        });
    }

    /**
     * Enregistrez la commande de la console de travail de file d'attente.
     *
     * @return void
     */
    protected function registerWorkCommand()
    {
        $this->app->bindShared('command.queue.work', function($app)
        {
            return new WorkCommand($app['queue.worker']);
        });

        $this->commands('command.queue.work');
    }

    /**
     * Enregistrez l'écouteur de file d'attente.
     *
     * @return void
     */
    protected function registerListener()
    {
        $this->registerListenCommand();

        $this->app->bindShared('queue.listener', function($app)
        {
            return new Listener($app['path.base']);
        });
    }

    /**
     * Enregistrez la commande de console d'écouteur de file d'attente.
     *
     * @return void
     */
    protected function registerListenCommand()
    {
        $this->app->bindShared('command.queue.listen', function($app)
        {
            return new ListenCommand($app['queue.listener']);
        });

        $this->commands('command.queue.listen');
    }

    /**
     * Enregistrez la commande de console de redémarrage de file d'attente.
     *
     * @return void
     */
    public function registerRestartCommand()
    {
        $this->app->bindShared('command.queue.restart', function()
        {
            return new RestartCommand;
        });

        $this->commands('command.queue.restart');
    }

    /**
     * Enregistrez la commande Push Queue Subscribe.
     *
     * @return void
     */
    protected function registerSubscriber()
    {
        $this->app->bindShared('command.queue.subscribe', function()
        {
            return new SubscribeCommand;
        });

        $this->commands('command.queue.subscribe');
    }

    /**
     * Enregistrez les connecteurs sur le gestionnaire de files d'attente.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    public function registerConnectors($manager)
    {
        foreach (array('Null', 'Sync', 'Database', 'Beanstalkd', 'Redis', 'Sqs', 'Iron') as $connector) {
            $method = "register{$connector}Connector";

            $this->{$method}($manager);
        }
    }

    /**
     * Enregistrez le connecteur de file d'attente Null.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerNullConnector($manager)
    {
        $manager->addConnector('null', function () {
            return new NullConnector();
        });
    }

    /**
     * Enregistrez le connecteur de file d’attente de synchronisation.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerSyncConnector($manager)
    {
        $manager->addConnector('sync', function()
        {
            return new SyncConnector();
        });
    }

    /**
     * Enregistrez le connecteur de file d'attente de base de données.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerDatabaseConnector($manager)
    {
        $manager->addConnector('database', function () {
            return new DatabaseConnector($this->app['db']);
        });
    }

    /**
     * Enregistrez le connecteur de file d'attente Beanstalkd.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerBeanstalkdConnector($manager)
    {
        $manager->addConnector('beanstalkd', function()
        {
            return new BeanstalkdConnector();
        });
    }

    /**
     * Enregistrez le connecteur de file d'attente Redis.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerRedisConnector($manager)
    {
        $app = $this->app;

        $manager->addConnector('redis', function() use ($app)
        {
            return new RedisConnector($app['redis']);
        });
    }

    /**
     * Enregistrez le connecteur de file d'attente Amazon SQS.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerSqsConnector($manager)
    {
        $manager->addConnector('sqs', function()
        {
            return new SqsConnector();
        });
    }

    /**
     * Enregistrez le connecteur de file d'attente IronMQ.
     *
     * @param  \Two\Queue\QueueManager  $manager
     * @return void
     */
    protected function registerIronConnector($manager)
    {
        $app = $this->app;

        $manager->addConnector('iron', function() use ($app)
        {
            return new IronConnector($app['encrypter'], $app['request']);
        });

        $this->registerIronRequestBinder();
    }

    /**
     * Enregistrez l’événement de reliure de demande pour la file d’attente Iron.
     *
     * @return void
     */
    protected function registerIronRequestBinder()
    {
        $this->app->rebinding('request', function($app, $request)
        {
            if ($app['queue']->connected('iron')) {
                $app['queue']->connection('iron')->setRequest($request);
            }
        });
    }

    /**
     * Enregistrez les services de travail ayant échoué.
     *
     * @return void
     */
    protected function registerFailedJobServices()
    {
        $this->app->bindShared('queue.failer', function($app)
        {
            $config = $app['config']['queue.failed'];

            if (isset($config['table'])) {
                return new DatabaseFailedJobProvider($app['db'], $config['database'], $config['table']);
            } else {
                return new NullFailedJobProvider();
            }
        });
    }

    /**
     * Enregistrez le travail de fermeture à deux files d'attente.
     *
     * @return void
     */
    protected function registerQueueClosure()
    {
        $this->app->bindShared('QueueClosure', function($app)
        {
            return new CallQueuedClosure($app['encrypter']);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'queue', 'queue.worker', 'queue.listener', 'queue.failer',
            'command.queue.work', 'command.queue.listen', 'command.queue.restart',
            'command.queue.subscribe', 'queue.connection'
        );
    }

}
