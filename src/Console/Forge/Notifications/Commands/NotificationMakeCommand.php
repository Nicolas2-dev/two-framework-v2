<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Notifications\Commands;

use Two\Console\Commands\GeneratorCommand;


class NotificationMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:notification';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Notification class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Notification';


    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ .str_replace('/', DS, '/stubs/notification.stub');
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Notifications';
    }
}
