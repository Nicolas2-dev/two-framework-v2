<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Providers;

use Two\Filesystem\Filesystem;
use Two\Application\Two;


class ProviderRepository
{

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le chemin vers le manifeste.
     *
     * @var string
     */
    protected $manifestPath;


    /**
     * Créez une nouvelle instance de référentiel de services.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $manifestPath
     * @return void
     */
    public function __construct(Filesystem $files, $manifestPath)
    {
        $this->files = $files;

        $this->manifestPath = $manifestPath .DS .'services.php';
    }

    /**
     * Enregistrez les fournisseurs de services d'application.
     *
     * @param    $app
     * @param  array  $providers
     * @return void
     */
    public function load(Two $app, array $providers)
    {
        $manifest = $this->loadManifest();

        // Nous allons d'abord charger le manifeste du service, qui contient des informations sur tous
        // fournisseurs de services enregistrés avec l'application et quels services elle
        // fournit. Ceci est utilisé pour savoir quels services sont des chargeurs "différés".
        if ($this->shouldRecompile($manifest, $providers)) {
            $manifest = $this->compileManifest($app, $providers);
        }

        // Si l'application est en cours d'exécution dans la console, nous ne chargerons aucun des
        // les prestataires de services. C'est principalement parce que ce n'est pas aussi nécessaire
        // pour performances et ainsi toutes les commandes Artisan fournies sont enregistrées.
        if ($app->runningInConsole()) {
            $manifest['eager'] = $manifest['providers'];
        }

        // Ensuite, nous allons enregistrer des événements pour charger les fournisseurs pour chacun 
        // des événements qu'il a demandé. Cela permet au fournisseur de services de différer
        // tout en étant automatiquement chargé lorsqu'un certain événement se produit.
        foreach ($manifest['when'] as $provider => $events) {
            $this->registerLoadEvents($app, $provider, $events);
        }

        // Nous allons continuer et enregistrer tous les fournisseurs chargés avec impatience auprès du
        // application afin que leurs services puissent être enregistrés avec l'application en tant que
        // un service fourni. Ensuite, nous y définirons la liste des services différés.
        foreach ($manifest['eager'] as $provider) {
            $app->register($this->createProvider($app, $provider));
        }

        $app->setDeferredServices($manifest['deferred']);
    }

    /**
     * Enregistrez les événements de chargement pour le fournisseur donné.
     *
     * @param  \Two\Application\Two  $app
     * @param  string  $provider
     * @param  array  $events
     * @return void
     */
    protected function registerLoadEvents(Two $app, $provider, array $events)
    {
        if (count($events) < 1) return;

        $app->make('events')->listen($events, function() use ($app, $provider)
        {
            $app->register($provider);
        });
    }

    /**
     * Compilez le fichier manifeste de l'application
     *
     * @param  \Two\Application\Two  $app
     * @param  array  $providers
     * @return array
     */
    protected function compileManifest(Two $app, $providers)
    {
        // Le manifeste de service doit contenir une liste de tous les fournisseurs pour
        // l'application pour pouvoir la comparer à chaque requête au service
        // et déterminez si le manifeste doit être recompilé ou s'il est à jour.
        $manifest = $this->freshManifest($providers);

        foreach ($providers as $provider)
        {
            $instance = $this->createProvider($app, $provider);

            // Lors de la recompilation du manifeste de service, nous parcourrons chacun des
            // fournisseurs et vérifiez s'il s'agit d'un fournisseur différé ou non. Si c'est 
            // le cas, nous allons ajoutez les services fournis au manifeste et notez le fournisseur.
            if ($instance->isDeferred()) {
                foreach ($instance->provides() as $service) {
                    $manifest['deferred'][$service] = $provider;
                }

                $manifest['when'][$provider] = $instance->when();
            }

            // Si les prestataires ne sont pas différés, nous l'ajouterons simplement à un
            // de fournisseurs chargés avec impatience qui seront enregistrés auprès de l'application
            // sur chaque demande aux applications au lieu d'être chargée paresseusement.
            else {
                $manifest['eager'][] = $provider;
            }
        }

        return $this->writeManifest($manifest);
    }

    /**
     * Créez une nouvelle instance de fournisseur.
     *
     * @param  \Two\Application\Two  $app
     * @param  string  $provider
     * @return \ApplicationProviders\ServiceProvider
     */
    public function createProvider(Two $app, $provider)
    {
        return new $provider($app);
    }

    /**
     * Déterminez si le manifeste doit être compilé.
     *
     * @param  array  $manifest
     * @param  array  $providers
     * @return bool
     */
    public function shouldRecompile($manifest, $providers)
    {
        return is_null($manifest) || ($manifest['providers'] != $providers);
    }

    /**
     * Chargez le fichier PHP manifeste du fournisseur de services.
     *
     * @return array
     */
    public function loadManifest()
    {
        // Le manifeste du service est un fichier contenant une représentation de chaque
        // service fourni par l'application et si son fournisseur utilise
        // chargement différé ou doit être chargé avec impatience à chaque demande qui 
        // nous est adressée.
        if ($this->files->exists($this->manifestPath)) {
            $manifest = $this->files->getRequire($this->manifestPath);

            return array_merge(array('when' => array()), $manifest);
        }
    }

    /**
     * Écrivez le fichier manifeste du service sur le disque.
     *
     * @param  array  $manifest
     * @return array
     */
    public function writeManifest($manifest)
    {
        $content = "<?php\n\nreturn " .var_export($manifest, true) .";\n";

        $this->files->put($this->manifestPath, $content);

        return array_merge(array('when' => array()), $manifest);
    }

    /**
     * Créez un nouveau tableau de manifestes.
     *
     * @param  array  $providers
     * @return array
     */
    protected function freshManifest(array $providers)
    {
        list($eager, $deferred) = array(array(), array());

        return compact('providers', 'eager', 'deferred');
    }

    /**
     * Obtenez l'instance du système de fichiers.
     *
     * @return \Two\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

}
