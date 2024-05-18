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
use Two\Console\Forge\Database\Migrations\Migrator;
use Two\Console\Traits\ConfirmableTrait;
use Two\Console\Forge\Packages\Commands\MigrationTrait;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PackageMigrateRollbackCommand extends Command
{
    use ConfirmableTrait;
    use MigrationTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:migrate:rollback';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Rollback the last database migrations for a specific or all Packages';

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
     * @param \Two\Packages\PackageManager $packages
     */
    public function __construct(Migrator $migrator, PackageManager $packages)
    {
        parent::__construct();

        $this->migrator = $migrator;
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

            return $this->rollback($slug);
        }

        foreach ($this->packages->all() as $package) {
            $this->comment('Rollback the last migration from Package: ' .$package['name']);

            $this->rollback($package['slug']);
        }
    }

    /**
     * Exécutez la restauration de la migration pour le package spécifié.
     *
     * @param string $slug
     *
     * @return mixed
     */
    protected function rollback($slug)
    {
        if (! $this->packages->exists($slug)) {
            return $this->error('Package does not exist.');
        }

        $this->requireMigrations($slug);

        //
        $this->migrator->setConnection($this->input->getOption('database'));

        $pretend = $this->input->getOption('pretend');

        $this->migrator->rollback($pretend, $slug);

        //
        foreach ($this->migrator->getNotes() as $note) {
            if (! $this->option('quiet')) {
                $this->line($note);
            }
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
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run while in production.'),
            array('pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run.'),
        );
    }
}
