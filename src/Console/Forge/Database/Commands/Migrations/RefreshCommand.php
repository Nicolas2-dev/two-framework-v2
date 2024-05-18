<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Commands\Command;
use Two\Console\Traits\ConfirmableTrait;

use Symfony\Component\Console\Input\InputOption;


class RefreshCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'migrate:refresh';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all migrations';


    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) return;

        $database = $this->input->getOption('database');

        $force = $this->input->getOption('force');

        $this->call('migrate:reset', array(
            '--database' => $database, '--force' => $force
        ));

        // La commande d'actualisation n'est essentiellement qu'un bref agrégat de quelques autres
        // les commandes de migration et fournit simplement un wrapper pratique à exécuter
        // les successivement. Nous verrons également si nous devons ré-amorcer la base de données.
        $this->call('migrate', array(
            '--database' => $database, '--force' => $force
        ));

        if ($this->needsSeeding()) {
            $this->runSeeder($database);
        }
    }

    /**
     * Déterminez si le développeur a demandé l’amorçage de la base de données.
     *
     * @return bool
     */
    protected function needsSeeding()
    {
        return $this->option('seed') || $this->option('seeder');
    }

    /**
     * Exécutez la commande seeder de base de données.
     *
     * @param  string  $database
     * @return void
     */
    protected function runSeeder($database)
    {
        $class = $this->option('seeder') ?: 'DatabaseSeeder';

        $this->call('db:seed', array('--database' => $database, '--class' => $class));
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
            array('seed',     null, InputOption::VALUE_NONE,     'Indicates if the seed task should be re-run.'),
            array('seeder',   null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'),
        );
    }
}
