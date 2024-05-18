<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Packages\PackageManager;
use Two\Console\Commands\Command;
use Two\Console\Traits\ConfirmableTrait;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PackageSeedCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:seed';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Seed the database with records for a specific or all Packages';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * Créez une nouvelle instance de commande.
     *
     * @param \Two\Packages\PackageManager $packages
     */
    public function __construct(PackageManager $packages)
    {
        parent::__construct();

        $this->packages = $packages;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $slug = $this->argument('slug');

        if (! empty($slug)) {
            if (! $this->packages->exists($slug)) {
                return $this->error('Package does not exist.');
            }

            if ($this->packages->isEnabled($slug)) {
                $this->seed($slug);
            } else if ($this->option('force')) {
                $this->seed($slug);
            }

            return;
        }

        if ($this->option('force')) {
            $packages = $this->packages->all();
        } else {
            $packages = $this->packages->enabled();
        }

        foreach ($packages as $package) {
            $slug = $package['slug'];

            $this->seed($slug);
        }
    }

    /**
     * Amorcez le package spécifique.
     *
     * @param string $package
     *
     * @return array
     */
    protected function seed($slug)
    {
        $package = $this->packages->where('slug', $slug);

        $className = $package['namespace'] .'\Database\Seeds\DatabaseSeeder';

        if (! class_exists($className)) {
            return;
        }

        // Préparez les paramètres d'appel.
        $params = array();

        if ($this->option('class')) {
            $params['--class'] = $this->option('class');
        } else {
            $params['--class'] = $className;
        }

        if ($option = $this->option('database')) {
            $params['--database'] = $option;
        }

        if ($option = $this->option('force')) {
            $params['--force'] = $option;
        }

        $this->call('db:seed', $params);
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::OPTIONAL, 'Package slug.')
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
            array('class', null, InputOption::VALUE_OPTIONAL, 'The class name of the Package\'s root seeder.'),
            array('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to seed.'),
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run while in production.'),
        );
    }
}
