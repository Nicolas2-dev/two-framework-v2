<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Mail;

use Swift_Mailer;

use Two\Mail\TransportManager;

use Two\Application\Providers\ServiceProvider;


class MailServiceProvider extends ServiceProvider
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
        $me = $this;

        $this->app->singleton('mailer', function($app) use ($me)
        {
            $me->registerSwiftMailer();

            // Une fois que nous aurons créé l'instance de mailer, nous définirons une instance de conteneur
            // sur le mailer. Cela nous permet de résoudre les classes de courrier via des conteneurs
            // pour une testabilité maximale sur lesdites classes au lieu de passer des fermetures.
            $mailer = new Mailer(
                $app['view'], $app['swift.mailer'], $app['events']
            );

            $this->setMailerDependencies($mailer, $app);

            // Si une adresse « de » est définie, nous la définirons sur le logiciel de messagerie afin que tous les courriers
            // les messages envoyés par les applications utiliseront la même adresse "de"
            // sur chacun, ce qui rend la vie du développeur beaucoup plus pratique.
            $from = $app['config']['mail.from'];

            if (is_array($from) && isset($from['address'])) {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            // Ici, nous déterminerons si le logiciel de messagerie doit être en mode "faire semblant" pour cela.
            // environnement, qui écrira simplement les e-mails dans les journaux au lieu de
            // l'envoie sur le Web, ce qui est utile pour les environnements de développement locaux.
            $pretend = $app['config']->get('mail.pretend', false);

            $mailer->pretend($pretend);

            return $mailer;
        });
    }

    /**
     * Définissez quelques dépendances sur l'instance de messagerie.
     *
     * @param  \Two\Mail\Mailer  $mailer
     * @param  \Two\Application\Two  $app
     * @return void
     */
    protected function setMailerDependencies($mailer, $app)
    {
        $mailer->setContainer($app);

        if ($app->bound('log')) {
            $mailer->setLogger($app['log']);
        }

        if ($app->bound('queue')) {
            $mailer->setQueue($app['queue']);
        }
    }

    /**
     * Enregistrez l'instance Swift Mailer.
     *
     * @return void
     */
    public function registerSwiftMailer()
    {
        $config = $this->app['config']['mail'];

        $this->registerSwiftTransport($config);

        // Une fois le transporteur enregistré, nous enregistrerons le Swift réel.
        // instance de mailer, passant les instances de transport, ce qui nous permet de
        // remplace les instances de ce transporteur lors du démarrage de l'application si nécessaire.
        $this->app['swift.mailer'] = $this->app->share(function($app)
        {
            return new Swift_Mailer($app['swift.transport']->driver());
        });
    }

    /**
     * Enregistrez l'instance Swift Transport.
     *
     * @param  array  $config
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function registerSwiftTransport($config)
    {
        $this->app['swift.transport'] = $this->app->share(function($app)
        {
            return new TransportManager($app);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('mailer', 'swift.mailer', 'swift.transport');
    }

}
