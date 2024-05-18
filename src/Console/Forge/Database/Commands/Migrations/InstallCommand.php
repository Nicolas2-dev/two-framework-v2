<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Commands\Command;
use Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface;

use Symfony\Component\Console\Input\InputOption;


class InstallCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'migrate:install';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create the migration repository';

    /**
     * L'instance du référentiel.
     *
     * @var \Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface
     */
    protected $repository;


    /**
     * Créez une nouvelle instance de commande d'installation de migration.
     *
     * @param  \Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface  $repository
     * @return void
     */
    public function __construct(MigrationRepositoryInterface $repository)
    {
        parent::__construct();

        $this->repository = $repository;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->repository->setSource($this->input->getOption('database'));

        $this->repository->createRepository();

        $this->info("Migration table created successfully.");
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
