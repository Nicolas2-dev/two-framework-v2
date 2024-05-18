<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\GeneratorCommand;


class EventMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:event';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Event class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Event';


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
        return realpath(__DIR__) .str_replace('/', DS, '/stubs/event.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Events';
    }
}
