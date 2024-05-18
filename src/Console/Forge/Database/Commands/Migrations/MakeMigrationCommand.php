<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Forge\Database\Migrations\MigrationCreator;
use Two\Console\Forge\Database\Commands\Migrations\BaseCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class MakeMigrationCommand extends BaseCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:migration';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new migration file';

    /**
     * Instance du créateur de migration.
     *
     * @var \Two\Console\Forge\Database\Migrations\MigrationCreator
     */
    protected $creator;

    /**
     * Le chemin d'accès au répertoire des packages (fournisseur).
     *
     * @var string
     */
    protected $packagePath;


    /**
     * Créez une nouvelle instance de commande d'installation de migration.

     *
     * @param  \Two\Console\Forge\Database\Migrations\MigrationCreator  $creator
     * @param  string  $packagePath
     * @return void
     */
    public function __construct(MigrationCreator $creator, $packagePath)
    {
        parent::__construct();

        $this->creator = $creator;

        $this->packagePath = $packagePath;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        // Il est possible pour le développeur de préciser les tables à modifier dans cet
        // opération de schéma. Le développeur peut également préciser si cette table nécessite
        // doit être fraîchement créé afin que nous puissions créer les migrations appropriées.
        $name = $this->input->getArgument('name');

        $table = $this->input->getOption('table');

        $create = $this->input->getOption('create');

        if (! $table && is_string($create)) {
            $table = $create;
        }

        // Nous sommes maintenant prêts à écrire la migration sur le disque. Une fois que nous avons écrit
        // la migration sortante, nous optimiserons pour que l'ensemble du framework soit réalisé
        // sûr que les migrations sont enregistrées par les chargeurs de classes.
        $this->writeMigration($name, $table, $create);

        $this->call('optimize');
    }

    /**
     * Écrivez le fichier de migration sur le disque.
     *
     * @param  string  $name
     * @param  string  $table
     * @param  bool    $create
     * @return string
     */
    protected function writeMigration($name, $table, $create)
    {
        $path = $this->getMigrationPath();

        $file = pathinfo($this->creator->create($name, $path, $table, $create), PATHINFO_FILENAME);

        $this->line("<info>Created Migration:</info> $file");
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'The name of the migration'),
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
            array('create', null, InputOption::VALUE_OPTIONAL, 'The table to be created.'),
            array('path',   null, InputOption::VALUE_OPTIONAL, 'Where to store the migration.', null),
            array('table',  null, InputOption::VALUE_OPTIONAL, 'The table to migrate.'),
        );
    }
}
