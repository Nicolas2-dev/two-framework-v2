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
use Two\Console\forge\Database\Migrations\Migrator;
use Two\Console\Forge\Packages\Commands\MigrationTrait;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PackageMigrateStatusCommand extends Command
{
    use MigrationTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:migrate:status';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Show the status of each migration';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * L'instance de migration.
     *
     * @var \Two\Database\Migrations\Migrator
     */
    protected $migrator;


    /**
     * Créez une nouvelle instance de commande d'annulation de migration.
     *
     * @param  \Two\Database\Migrations\Migrator $migrator
     * @return \Two\Database\Console\Migrations\StatusCommand
     */
    public function __construct(Migrator $migrator, PackageManager $packages)
    {
        parent::__construct();

        $this->migrator = $migrator;
        $this->packages  = $packages;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->migrator->repositoryExists()) {
            return $this->error('No migrations found.');
        }

        $slug = $this->argument('slug');

        if (! empty($slug)) {
            if (! $this->packages->exists($slug)) {
                return $this->error('package does not exist.');
            }

            return $this->status($slug);
        }

        foreach ($this->packages->all() as $package) {
            $this->comment('Migrations Status for package: ' .$package['name']);

            $this->status($package['slug']);
        }
    }

    protected function status($slug)
    {
        if (! $this->packages->exists($slug)) {
            return $this->error('package does not exist.');
        }

        $this->requireMigrations($slug);

        //
        $this->migrator->setConnection($this->input->getOption('database'));

        $ran = $this->migrator->getRepository()->getRan();

        //
        $migrations = array();

        foreach ($this->getAllMigrationFiles($slug) as $migration) {
            $migrations[] = in_array($migration, $ran) ? array('<info>Y</info>', $migration) : array('<fg=red>N</fg=red>', $migration);
        }

        if (count($migrations) > 0) {
            $this->table(array('Ran?', 'Migration'), $migrations);
        } else {
            $this->error('No migrations found');

            $this->output->writeln('');
        }
    }

    /**
     * Obtenez tous les fichiers de migration.
     *
     * @param  string  $path
     * @return array
     */
    protected function getAllMigrationFiles($slug)
    {
        $path = $this->getMigrationPath($slug);

        return $this->migrator->getMigrationFiles($path);
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::OPTIONAL, 'package slug.'),
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
        );
    }
}
