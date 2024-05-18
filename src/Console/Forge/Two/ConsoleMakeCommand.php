<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\GeneratorCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ConsoleMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:console';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Forge command';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Console command';


    /**
     * Remplacez le nom de classe pour le stub donné.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $stub = parent::replaceClass($stub, $name);

        return str_replace('{{command}}', $this->option('command'), $stub);
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        return realpath(__DIR__) .str_replace('/', DS, '/stubs/console.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Console\Commands';
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'The name of the command.'),
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
            array('command', null, InputOption::VALUE_OPTIONAL, 'The terminal command that should be assigned.', 'command:name'),
        );
    }
}
