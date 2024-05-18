<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Routing\Commands;

use Two\Console\Commands\GeneratorCommand;

use Symfony\Component\Console\Input\InputOption;


class ControllerMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:controller';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Controller class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Controller';


    /**
     * Déterminez si la classe existe déjà.
     *
     * @param  string  $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        return class_exists($rawName);
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('plain')) {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/controller.plain.stub');
        }

        return realpath(__DIR__) .str_replace('/', DS, '/stubs/controller.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Controllers';
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('plain', null, InputOption::VALUE_NONE, 'Generate an empty Controller class.'),
        );
    }
}
