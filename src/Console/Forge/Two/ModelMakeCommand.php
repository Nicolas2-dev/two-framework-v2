<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\GeneratorCommand;


class ModelMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:model';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new ORM Model class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        return realpath(__DIR__) .str_replace('/', DS, '/stubs/model.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Models';
    }
}
