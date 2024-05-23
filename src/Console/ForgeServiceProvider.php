<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console;

use Two\Console\Forge\Two\UpCommand;
use Two\Console\Forge\Two\DownCommand;
use Two\Console\Forge\Two\ServeCommand;
use Two\Console\Forge\Two\JobMakeCommand;
use Two\Console\Forge\Two\ClearLogCommand;
use Two\Console\Forge\Two\OptimizeCommand;
use Two\Console\Forge\Two\AssetLinkCommand;
use Two\Console\Forge\Two\EventMakeCommand;
use Two\Console\Forge\Two\ModelMakeCommand;
use Two\Console\Forge\Two\ViewClearCommand;
use Two\Console\Forge\Two\PolicyMakeCommand;
use Two\Console\Forge\Two\SharedMakeCommand;
use Two\Console\Forge\Two\ConsoleMakeCommand;
use Two\Console\Forge\Two\EnvironmentCommand;
use Two\Console\Forge\Two\KeyGenerateCommand;
use Two\Console\Forge\Two\RequestMakeCommand;
use Two\Console\Forge\Two\ViewPublishCommand;
use Two\Console\Forge\Two\AssetPublishCommand;
use Two\Console\Forge\Two\ListenerMakeCommand;
use Two\Console\Forge\Two\ProviderMakeCommand;
use Two\Console\Forge\Publishers\ViewPublisher;
use Two\Console\Forge\Publishers\AssetPublisher;
use Two\Console\Forge\Publishers\ConfigPublisher;
use Two\Console\Forge\Two\ClearCompiledCommand;
use Two\Console\Forge\Two\ConfigPublishCommand;
use Two\Console\Forge\Two\VendorPublishCommand;
use Two\Application\Providers\ServiceProvider;


class ForgeServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du fournisseur est différé.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Les commandes à enregistrer.
     *
     * @var array
     */
    protected $commands = array(
        'AssetPublish'     => 'command.asset.publish',
        'ConfigPublish'    => 'command.config.publish',
        'ClearCompiled'    => 'command.clear-compiled',
        'ClearLog'         => 'command.clear-log',
        'ConsoleMake'      => 'command.console.make',
        'Down'             => 'command.down',
        'Environment'      => 'command.environment',
        'EventMake'        => 'command.event.make',
        'JobMake'          => 'command.job.make',
        'KeyGenerate'      => 'command.key.generate',
        'ListenerMake'     => 'command.listener.make',
        'ModelMake'        => 'command.model.make',
        'Optimize'         => 'command.optimize',
        'PolicyMake'       => 'command.policy.make',
        'ProviderMake'     => 'command.provider.make',
        'RequestMake'      => 'command.request.make',
        'Serve'            => 'command.serve',
        'SharedMake'       => 'command.shared.make',
        'AssetLink'        => 'command.assets-link',
        'Up'               => 'command.up',
        'VendorPublish'    => 'command.vendor.publish',
        'ViewClear'        => 'command.view.clear',
        'ViewPublish'      => 'command.view.publish',
    );

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        foreach (array_keys($this->commands) as $command) {
            $method = "register{$command}Command";

            call_user_func_array(array($this, $method), array());
        }

        $this->commands(array_values($this->commands));
    }

    /**
     * Enregistrez la classe et la commande de l'éditeur de configuration.
     *
     * @return void
     */
    protected function registerAssetPublishCommand()
    {
        $this->app->singleton('asset.publisher', function($app)
        {
            $publisher = new AssetPublisher($app['files'], $app['path.public']);

            //
            $path = $app['path.base'] .DS .'vendor';

            $publisher->setPackagePath($path);

            return $publisher;
        });

        $this->app->singleton('command.asset.publish', function($app)
        {
            $assetPublisher  = $app['asset.publisher'];

            return new AssetPublishCommand($app['assets.dispatcher'], $assetPublisher);
        });
    }

    /**
     * Enregistrez la classe et la commande de l'éditeur de configuration.
     *
     * @return void
     */
    protected function registerConfigPublishCommand()
    {
        $this->app->singleton('config.publisher', function($app)
        {
            $path = $app['path'] .DS .'Config';

            $publisher = new ConfigPublisher($app['files'], $app['config'], $path);

            return $publisher;
        });

        $this->app->singleton('command.config.publish', function($app)
        {
            $configPublisher = $app['config.publisher'];

            return new ConfigPublishCommand($configPublisher);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerAssetLinkCommand()
    {
        $this->app->singleton('command.assets-link', function ()
        {
            return new AssetLinkCommand;
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerClearCompiledCommand()
    {
        $this->app->singleton('command.clear-compiled', function ()
        {
            return new ClearCompiledCommand;
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerClearLogCommand()
    {
        $this->app->singleton('command.clear-log', function ($app)
        {
            return new ClearLogCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerViewClearCommand()
    {
        $this->app->singleton('command.view.clear', function ($app)
        {
            return new ViewClearCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerConsoleMakeCommand()
    {
        $this->app->singleton('command.console.make', function ($app)
        {
            return new ConsoleMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerDownCommand()
    {
        $this->app->singleton('command.down', function ()
        {
            return new DownCommand;
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerEnvironmentCommand()
    {
        $this->app->singleton('command.environment', function ()
        {
            return new EnvironmentCommand;
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerEventMakeCommand()
    {
        $this->app->singleton('command.event.make', function ($app)
        {
            return new EventMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerJobMakeCommand()
    {
        $this->app->singleton('command.job.make', function ($app)
        {
            return new JobMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerKeyGenerateCommand()
    {
        $this->app->singleton('command.key.generate', function ($app)
        {
            return new KeyGenerateCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerListenerMakeCommand()
    {
        $this->app->singleton('command.listener.make', function ($app)
        {
            return new ListenerMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerModelMakeCommand()
    {
        $this->app->singleton('command.model.make', function ($app)
        {
            return new ModelMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerOptimizeCommand()
    {
        $this->app->singleton('command.optimize', function ($app)
        {
            return new OptimizeCommand($app['composer']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerPolicyMakeCommand()
    {
        $this->app->singleton('command.policy.make', function ($app)
        {
            return new PolicyMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerProviderMakeCommand()
    {
        $this->app->singleton('command.provider.make', function ($app)
        {
            return new ProviderMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerRequestMakeCommand()
    {
        $this->app->singleton('command.request.make', function ($app)
        {
            return new RequestMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerServeCommand()
    {
        $this->app->singleton('command.serve', function ()
        {
            return new ServeCommand;
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerSharedMakeCommand()
    {
        $this->app->singleton('command.shared.make', function ($app)
        {
            return new SharedMakeCommand($app['files']);
        });
    }

    /**
     * Enregistrez la commande.
     *
     * @return void
     */
    protected function registerUpCommand()
    {
        $this->app->singleton('command.up', function ()
        {
            return new UpCommand;
        });
    }

    /**
     * Enregistrez la commande de console de publication du fournisseur.
     *
     * @return void
     */
    protected function registerVendorPublishCommand()
    {
        $this->app->singleton('command.vendor.publish', function ($app)
        {
            return new VendorPublishCommand($app['files']);
        });
    }

    /**
     * Enregistrez la classe et la commande de l'éditeur de configuration.
     *
     * @return void
     */
    protected function registerViewPublishCommand()
    {
        $this->app->singleton('view.publisher', function($app)
        {
            $viewPath = $app['path'] .DS .'Views';

            $vendorPath = $app['path.base'] .DS .'vendor';

            //
            $publisher = new ViewPublisher($app['files'], $viewPath);

            $publisher->setPackagePath($vendorPath);

            return $publisher;
        });

        $this->app->singleton('command.view.publish', function($app)
        {
            return new ViewPublishCommand($app['packages'], $app['view.publisher']);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array_values($this->commands);
    }
}
