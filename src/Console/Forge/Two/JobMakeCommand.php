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


class JobMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:job';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Job class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Job';


    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('queued')) {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/job-queued.stub');
        } else {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/job.stub');
        }
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Jobs';
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('queued', null, InputOption::VALUE_NONE, 'Indicates that Job should be queued.'),
        );
    }
}
