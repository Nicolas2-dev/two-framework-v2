<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Seeds;

use Two\Console\Commands\GeneratorCommand;
use Two\Filesystem\Filesystem;
use Two\Application\Composer;


class SeederMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:seeder';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Database Seeder class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Seeder';

    /**
     * L'instance du compositeur.
     *
     * @var \Two\Foundation\Composer
     */
    protected $composer;


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  \Two\Foundation\Composer  $composer
     * @return void
     */
    public function __construct(Filesystem $files, Composer $composer)
    {
        parent::__construct($files);

        $this->composer = $composer;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        parent::handle();

        $this->composer->dumpAutoloads();
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ .str_replace('/', DS, '/stubs/seeder.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Database\Seeds';
    }
}
