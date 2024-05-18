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
use Two\Console\Forge\Database\Migrations\Migrator;

use Symfony\Component\Console\Input\InputOption;


class ResetCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'migrate:reset';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Rollback all database migrations';

    /**
     * L'instance de migration.
     *
     * @var \Two\Console\Forge\Database\Migrations\Migrator
     */
    protected $migrator;


    /**
     * Créez une nouvelle instance de commande d'annulation de migration.
     *
     * @param  \Two\Console\Forge\Database\Migrations\Migrator  $migrator
     * @return void
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
        if (! $this->confirmToProceed()) return;

        $this->migrator->setConnection($this->input->getOption('database'));

        $pretend = $this->input->getOption('pretend');

        while (true) {
            $count = $this->migrator->rollback($pretend);

            // Une fois le migrateur exécuté, nous récupérerons la sortie de la note et l'enverrons à
            // l'écran de la console, puisque le migrateur lui-même fonctionne sans avoir
            // toutes les instances du contrat OutputInterface transmises à la classe.
            foreach ($this->migrator->getNotes() as $note) {
                $this->output->writeln($note);
            }

            if ($count == 0) break;
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
            array('pretend',  null, InputOption::VALUE_NONE,     'Dump the SQL queries that would be run.'),
        );
    }
}
