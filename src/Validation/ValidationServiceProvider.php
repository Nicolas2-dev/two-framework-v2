<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation;

use Two\Validation\Factory;

use Two\Validation\DatabasePresenceVerifier;

use Two\Application\Providers\ServiceProvider;


class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du Provider est différé.
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
        $this->registerPresenceVerifier();

        $this->app->singleton('validator', function($app)
        {
            $config = $app['config'];

            // Obtenez une instance de Validation Factory.
            $validator = new Factory($config);

            if (isset($app['validation.presence'])) {
                $presenceVerifier = $app['validation.presence'];

                $validator->setPresenceVerifier($presenceVerifier);
            }

            return $validator;
        });
    }

    /**
     * Enregistrez le vérificateur de présence de base de données.
     *
     * @return void
     */
    protected function registerPresenceVerifier()
    {
        $this->app->singleton('validation.presence', function($app)
        {
            return new DatabasePresenceVerifier($app['db']);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array('validator');
    }
}
