<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Log;

use Two\Log\Writer;

use Two\Application\Providers\ServiceProvider;


use Monolog\Logger as Monolog;


class LogServiceProvider extends ServiceProvider
{
    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('log', function ()
        {
            return $this->createLogger();
        });
    }

    /**
     * Créez l'enregistreur.
     *
     * @return \Two\Log\Writer
     */
    protected function createLogger()
    {
        $log = new Writer(
            new Monolog('Two'), $this->app['events']
        );

        $this->configureHandler($log);

        return $log;
    }

    /**
     * Configurez les gestionnaires Monolog pour l'application.
     *
     * @param  \Two\Application\Two  $app
     * @param  \Two\Log\Writer  $log
     * @return void
     */
    protected function configureHandler(Writer $log)
    {
        $driver = $this->app['config']['app.log'];

        $method = 'configure' .ucfirst($driver) .'Handler';

        call_user_func(array($this, $method), $log);
    }

    /**
     * Configurez les gestionnaires Monolog pour l'application.
     *
     * @param  \Two\Application\Two  $app
     * @param  \Two\Log\Writer  $log
     * @return void
     */
    protected function configureSingleHandler(Writer $log)
    {
        $log->useFiles($this->app['path.storage'] .DS .'logs' .DS .'framework.log');
    }

    /**
     * Configurez les gestionnaires Monolog pour l'application.
     *
     * @param  \Two\Log\Writer  $log
     * @return void
     */
    protected function configureDailyHandler(Writer $log)
    {
        $log->useDailyFiles(
            $this->app['path.storage'] .DS .'logs' .DS .'framework.log',
            $this->app['config']->get('app.log_max_files', 5)
        );
    }

    /**
     * Configurez les gestionnaires Monolog pour l'application.
     *
     * @param  \Two\Log\Writer  $log
     * @return void
     */
    protected function configureSyslogHandler(Writer $log)
    {
        $log->useSyslog('Two');
    }

    /**
     * Configurez les gestionnaires Monolog pour l'application.
     *
     * @param  \Two\Log\Writer  $log
     * @return void
     */
    protected function configureErrorlogHandler(Writer $log)
    {
        $log->useErrorLog();
    }
}
