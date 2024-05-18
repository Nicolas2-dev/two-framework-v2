<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Forge\Database\Migrations\Migrator;
use Two\Console\Traits\ConfirmableTrait;
use Two\Console\Forge\Database\Commands\Migrations\BaseCommand;

use Symfony\Component\Console\Input\InputOption;


class MigrateCommand extends BaseCommand
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'db:migrate';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Run the database migrations';

    /**
     * L'instance de migration.
     *
     * @var \Two\Console\Forge\Database\Migrations\Migrator
     */
    protected $migrator;

    /**
     * Le chemin d'accès au répertoire des packages (fournisseur).
     */
    protected $packagePath;


    /**
     * Créez une nouvelle instance de commande de migration.
     *
     * @param  \Two\Console\Forge\Database\Migrations\Migrator  $migrator
     * @param  string  $packagePath
     * @return void
     */
    public function __construct(Migrator $migrator, $packagePath)
    {
        parent::__construct();

        $this->migrator = $migrator;
        $this->packagePath = $packagePath;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) return;

        $this->prepareDatabase();

        // L'option simulation peut être utilisée pour "simuler" la migration et la capture
        // les requêtes SQL qui se déclencheraient si la migration devait être exécutée sur
        // une base de données réelle, utile pour vérifier les migrations.
        $pretend = $this->input->getOption('pretend');

        $path = $this->getMigrationPath();

        $this->migrator->run($path, $pretend);

        // Une fois le migrateur exécuté, nous récupérerons la sortie de la note et l'enverrons à
        // l'écran de la console, puisque le migrateur lui-même fonctionne sans avoir
        // toutes les instances du contrat OutputInterface transmises à la classe.
        foreach ($this->migrator->getNotes() as $note) {
            $this->output->writeln($note);
        }

        // Enfin, si l'option "seed" a été donnée, nous relancerons la base de données
        // tâche de départ pour repeupler la base de données, ce qui est pratique lors de l'ajout
        // une migration et une graine à la fois, puisqu'il s'agit uniquement de cette commande.
        if ($this->input->getOption('seed')) {
            $this->call('db:seed', array('--force' => true));
        }
    }

    /**
     * Préparez la base de données de migration pour son exécution.
     *
     * @return void
     */
    protected function prepareDatabase()
    {
        $this->migrator->setConnection($this->input->getOption('database'));

        if (! $this->migrator->repositoryExists()) {
            $options = array('--database' => $this->input->getOption('database'));

            $this->call('migrate:install', $options);
        }
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
            array('force',    null, InputOption::VALUE_NONE,     'Force the operation to run when in production.'),
            array('path',     null, InputOption::VALUE_OPTIONAL, 'The path to migration files.', null),
            array('pretend',  null, InputOption::VALUE_NONE,     'Dump the SQL queries that would be run.'),
            array('seed',     null, InputOption::VALUE_NONE,     'Indicates if the seed task should be re-run.'),
        );
    }
}
