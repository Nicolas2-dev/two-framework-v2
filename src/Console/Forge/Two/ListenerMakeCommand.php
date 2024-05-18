<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Support\Str;
use Two\Console\Commands\GeneratorCommand;

use Symfony\Component\Console\Input\InputOption;

class ListenerMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:listener';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Event Listener class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Listener';


    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->option('event')) {
            return $this->error('Missing required option: --event');
        }

        parent::handle();
    }

    /**
     * Construisez la classe avec le nom donné.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $event = $this->option('event');

        //
        $namespace = $this->container->getNamespace();

        if (! Str::startsWith($event, $namespace)) {
            $event = $namespace .'Events\\' .$event;
        }

        $stub = str_replace(
            '{{event}}', class_basename($event), $stub
        );

        $stub = str_replace(
            '{{fullEvent}}', $event, $stub
        );

        return $stub;
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('queued')) {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/listener-queued.stub');
        } else {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/listener.stub');
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
        return $rootNamespace .'\Listeners';
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('event', 'e', InputOption::VALUE_REQUIRED, 'The event class being listened for.'),

            array('queued', null, InputOption::VALUE_NONE, 'Indicates the event listener should be queued.'),
        );
    }
}
