<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Forge\Database\Migrations\Migrator;
use Two\Console\Forge\Database\Commands\Migrations\BaseCommand;

use Symfony\Component\Console\Input\InputOption;


class StatusCommand extends BaseCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'migrate:status';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Show the status of each migration';

    /**
     * L'instance de migration.
     *
     * @var \Two\Console\Forge\Database\Migrations\Migrator
     */
    protected $migrator;


    /**
     * Créez une nouvelle instance de commande d'annulation de migration.
     *
     * @param  \Two\Console\Forge\Database\Migrations\Migrator $migrator
     * @return \Two\Database\Console\Migrations\StatusCommand
     */
    public function __construct(Migrator $migrator)
    {
        parent::__construct();

        $this->migrator = $migrator;
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

        $this->migrator->setConnection($this->input->getOption('database'));

        if (! is_null($path = $this->input->getOption('path'))) {
            $path = $this->container['path.base'] .DS .$path;
        } else {
            $path = $this->getMigrationPath();
        }

        $ran = $this->migrator->getRepository()->getRan();

        //
        $migrations = array();

        foreach ($this->getAllMigrationFiles($path) as $migration) {
            $migrations[] = in_array($migration, $ran) ? array('<info>Y</info>', $migration) : array('<fg=red>N</fg=red>', $migration);
        }

        if (count($migrations) > 0) {
            $this->table(array('Ran?', 'Migration'), $migrations);
        } else {
            $this->error('No migrations found');
        }
    }

    /**
     * Obtenez tous les fichiers de migration.
     *
     * @param  string  $path
     * @return array
     */
    protected function getAllMigrationFiles($path)
    {
        return $this->migrator->getMigrationFiles($path);
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

            array('path', null, InputOption::VALUE_OPTIONAL, 'The path of migrations files to use.'),
        );
    }
}
