<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Routing\Assets\AssetDispatcher;
use Two\Console\Commands\Command;
use Two\Console\Forge\Publishers\AssetPublisher;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class AssetPublishCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'asset:publish';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Publish a package's assets to the public directory";

    /**
     * L’instance du répartiteur d’actifs.
     *
     * @var \Two\Asssets\AssetDispatcher
     */
    protected $dispatcher;

    /**
     * Instance de l’éditeur d’actifs.
     *
     * @var \Two\Foundation\AssetPublisher
     */
    protected $publisher;


    /**
     * Créez une nouvelle instance de commande de publication d’actifs.
     *
     * @param  \Two\Assets\AssetDispatcher $dispatcher
     * @param  \Two\Foundation\AssetPublisher  $assets
     * @return void
     */
    public function __construct(AssetDispatcher $dispatcher, AssetPublisher $publisher)
    {
        parent::__construct();

        $this->dispatcher = $dispatcher;

        $this->publisher = $publisher;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->getPackages() as $package) {
            $this->publishAssets($package);
        }
    }

    /**
     * Publiez les ressources pour un nom de package donné.
     *
     * @param  string  $package
     * @return void
     */
    protected function publishAssets($package)
    {
        if (! $this->dispatcher->hasNamespace($package)) {
            return $this->error('Package does not exist.');
        }

        if ( ! is_null($path = $this->getPath())) {
            $this->publisher->publish($package, $path);
        } else {
            $path = $this->dispatcher->getPackagePath($package);

            $this->publisher->publishPackage($package, $path);
        }

        $this->output->writeln('<info>Assets published for package:</info> ' .$package);
    }

    /**
     * Obtenez le nom du package en cours de publication.
     *
     * @return array
     */
    protected function getPackages()
    {
        if (! is_null($package = $this->input->getArgument('package'))) {
            if (Str::length($package) > 3) {
                $package = Str::snake($package, '-');
            } else {
                $package = Str::lower($package);
            }

            return array($package);
        }

        return $this->findAllAssetPackages();
    }

    /**
     * Trouvez tous les packages d’hébergement d’actifs dans le système.
     *
     * @return array
     */
    protected function findAllAssetPackages()
    {
        $packages = array();

        //
        $namespaces = $this->dispatcher->getHints();

        foreach ($namespaces as $name => $hint) {
            $packages[] = $name;
        }

        return $packages;
    }

    /**
     * Obtenez le chemin spécifié vers les fichiers.
     *
     * @return string
     */
    protected function getPath()
    {
        $path = $this->input->getOption('path');

        if (! is_null($path)) {
            return $this->container['path.base'] .DS .$path;
        }
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('package', InputArgument::OPTIONAL, 'The name of package being published.'),
        );
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('path', null, InputOption::VALUE_OPTIONAL, 'The path to the asset files.', null),
        );
    }
}
