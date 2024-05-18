<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;
use Two\Console\Forge\Publishers\ViewPublisher;
use Two\Packages\PackageManager;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ViewPublishCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'view:publish';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Publish a Package views to the application";

    /**
     * L'instance du gestionnaire de plugins.
     *
     * @var \Two\Packages\PackageManager
     */
    protected $plugins;

    /**
     * L'instance d'éditeur de vue.
     *
     * @var \Two\Foundation\ViewPublisher
     */
    protected $publisher;


    /**
     * Créez une nouvelle instance de commande de publication de vue.
     *
     * @param  \Two\Foundation\ViewPublisher  $view
     * @return void
     */
    public function __construct(PackageManager $plugins, ViewPublisher $publisher)
    {
        parent::__construct();

        $this->plugins = $plugins;

        $this->publisher = $publisher;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $package = $this->input->getArgument('package');

        // Spécification directe du package et du chemin des vues.
        if (! is_null($path = $this->getPath())) {
            $this->publisher->publish($package, $path);
        }

        // Pour les packages enregistrés en tant que plugins.
        else if ($this->plugins->exists($package)) {
            if (Str::length($package) > 3) {
                $slug = Str::snake($package);
            } else {
                $slug = Str::lower($package);
            }

            $properties = $this->plugins->where('slug', $slug);

            //
            $package = $properties['name'];

            if ($properties['type'] == 'package') {
                $path = $properties['path'] .str_replace('/', DS, '/src/Views');
            } else {
                $path = $properties['path'] .DS . 'Views';
            }

            $this->publisher->publish($package, $path);
        }

        // Pour les autres packages situés chez le fournisseur.
        else {
            $this->publisher->publishPackage($package);
        }

        $this->output->writeln('<info>Views published for package:</info> '.$package);
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
            array('package', InputArgument::REQUIRED, 'The name of the package being published.'),
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
            array('path', null, InputOption::VALUE_OPTIONAL, 'The path to the source view files.', null),
        );
    }

}
