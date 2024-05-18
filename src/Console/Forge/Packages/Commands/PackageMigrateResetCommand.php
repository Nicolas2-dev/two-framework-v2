<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Filesystem\Filesystem;
use Two\Packages\PackageManager;
use Two\Console\Commands\Command;
use Two\Console\Forge\Database\Migrations\Migrator;
use Two\Console\Traits\ConfirmableTrait;
use Two\Console\Forge\Packages\Commands\MigrationTrait;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PackageMigrateResetCommand extends Command
{
    use ConfirmableTrait;
    use MigrationTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:migrate:reset';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Rollback all database migrations for a specific or all Packages';

    /**
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * @var Migrator
     */
    protected $migrator;

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * Créez une nouvelle instance de commande.
     *
     * @param PackageManager  $packages
     * @param Filesystem     $files
     * @param Migrator       $migrator
     */
    public function __construct(PackageManager $packages, Filesystem $files, Migrator $migrator)
    {
        parent::__construct();

        $this->packages  = $packages;
        $this->files    = $files;
        $this->migrator = $migrator;
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
                return $this->reset($slug);
            }

            return;
        }

        $packages = $this->packages->enabled()->reverse();

        foreach ($packages as $package) {
            $this->comment('Resetting the migrations of Package: ' .$package['name']);

            $this->reset($package['slug']);
        }
    }

    /**
     * Exécutez la réinitialisation de la migration pour le package spécifié.
     *
     * Les migrations doivent être réinitialisées dans l'ordre inverse de celui où elles ont été
     * migré vers le haut en tant que. Cela garantit que la base de données est correctement inversée
     *sans conflit.
     *
     * @param string $slug
     *
     * @return mixed
     */
    protected function reset($slug)
    {
        if (! $this->packages->exists($slug)) {
            return $this->error('Package does not exist.');
        }

        $this->requireMigrations($slug);

        //
        $this->migrator->setconnection($this->input->getOption('database'));

        $pretend = $this->input->getOption('pretend');

        while (true) {
            $count = $this->migrator->rollback($pretend, $slug);

            foreach ($this->migrator->getNotes() as $note) {
                $this->output->writeln($note);
            }

            if ($count == 0) break;
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
            array('pretend', null, InputOption::VALUE_OPTIONAL, 'Dump the SQL queries that would be run.'),
        );
    }
}
