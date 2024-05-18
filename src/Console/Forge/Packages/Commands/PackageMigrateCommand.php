<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Commands\Command;
use Two\Console\Traits\ConfirmableTrait;
use Two\Console\forge\Database\Migrations\Migrator;
use Two\Packages\PackageManager;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PackageMigrateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:migrate';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Run the database migrations for a specific or all Packages';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * @var Migrator
     */
    protected $migrator;


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param Migrator $migrator
     * @param PackageManager  $package
     */
    public function __construct(Migrator $migrator, PackageManager $packages)
    {
        parent::__construct();

        $this->migrator = $migrator;
        $this->packages   = $packages;
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

        $this->prepareDatabase();

        $slug = $this->argument('slug');

        if (! empty($slug)) {
            if (! $this->packages->exists($slug)) {
                return $this->error('Package does not exist.');
            }

            if ($this->packages->isEnabled($slug)) {
                return $this->migrate($slug);
            }

            return $this->error('Nothing to migrate.');
        }

        if ($this->option('force')) {
            $packages = $this->packages->all();
        } else {
            $packages = $this->packages->enabled();
        }

        foreach ($packages as $package) {
            $this->comment('Migrating the Package: ' .$package['name']);

            $this->migrate($package['slug']);
        }
    }

    /**
     * Exécutez des migrations pour le package spécifié.
     *
     * @param string $slug
     *
     * @return mixed
     */
    protected function migrate($slug)
    {
        if (! $this->packages->exists($slug)) {
            return $this->error('Package does not exist.');
        }

        $path = $this->getMigrationPath($slug);

        //
        $pretend = $this->input->getOption('pretend');

        $this->migrator->run($path, $pretend, $slug);

        //
        foreach ($this->migrator->getNotes() as $note) {
            if (! $this->option('quiet')) {
                $this->line($note);
            }
        }

        if ($this->option('seed')) {
            $this->call('package:seed', array('slug' => $slug, '--force' => true));
        }
    }

    /**
     * Obtenez le chemin du répertoire de migration.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function getMigrationPath($slug)
    {
        $package = $this->packages->where('slug', $slug);

        $path = $this->packages->resolveClassPath($package);

        return $path .'Database' .DS .'Migrations' .DS;
    }

    /**
     * Préparez la base de données de migration pour son exécution.
     */
    protected function prepareDatabase()
    {
        $this->migrator->setConnection($this->option('database'));

        if (! $this->migrator->repositoryExists()) {
            $options = array('--database' => $this->option('database'));

            $this->call('migrate:install', $options);
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
            array('slug', InputArgument::OPTIONAL, 'Package slug.'),
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
            array('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
            array('pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'),
            array('seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'),
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run while in production.'),
        );
    }
}
