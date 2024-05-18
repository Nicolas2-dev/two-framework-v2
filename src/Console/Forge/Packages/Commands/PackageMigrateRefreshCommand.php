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
use Two\Packages\PackageManager;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


class PackageMigrateRefreshCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:migrate:refresh';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all migrations for a specific or all Packages';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * Créez une nouvelle instance de commande.
     *
     * @param PackageManager  $package
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

        if (! $this->packages->exists($slug)) {
            return $this->error('Package does not exist.');
        }

        $this->call('package:migrate:reset', array(
            'slug'       => $slug,
            '--database' => $this->option('database'),
            '--force'    => $this->option('force'),
            '--pretend'  => $this->option('pretend'),
        ));

        $this->call('package:migrate', array(
            'slug'       => $slug,
            '--database' => $this->option('database'),
        ));

        if ($this->needsSeeding()) {
            $this->runSeeder($slug, $this->option('database'));
        }

        $this->info('Package has been refreshed.');
    }

    /**
     * Déterminez si le développeur a demandé l’amorçage de la base de données.
     *
     * @return bool
     */
    protected function needsSeeding()
    {
        return $this->option('seed');
    }

    /**
     * Exécutez la commande Package seeder.
     *
     * @param string $database
     */
    protected function runSeeder($slug = null, $database = null)
    {
        $this->call('package:seed', array(
            'slug'       => $slug,
            '--database' => $database,
        ));
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::REQUIRED, 'Package slug.'),
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
