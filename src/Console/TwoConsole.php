<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console;

use Closure;
use Exception;
use Throwable;

use Two\Console\Commands\Command;
use Two\Console\Commands\ClosureCommand;
use Two\Exceptions\Exception\FatalThrowableError;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;


class TwoConsole extends \Symfony\Component\Console\Application
{
    /**
     * L'instance de deux applications.
     *
     * @var \Two\Application\Two
     */
    protected $container;


    /**
     * Créez et démarrez une nouvelle application console.
     *
     * @param  \Two\Application\Two  $app
     * @return \Two\Console\TwoConsole
     */
    public static function start($app)
    {
        return static::make($app)->boot();
    }

    /**
     * Créez une nouvelle application console.
     *
     * @param  \Two\Application\Two  $app
     * @return \Two\Console\TwoConsole
     */
    public static function make($app)
    {
        $app->boot();

        $console = with($console = new static('PHP : '.PHP_VERSION.' - Two Framework '.$app::VERSION))
            ->setContainer($app)
            ->setAutoExit(false);

        $app->instance('forge', $console);

        return $console;
    }

    /**
     * Démarrez l'application Console.
     *
     * @return $this
     */
    public function boot()
    {
        $path = $this->container['path'] .DS .'Console' .DS .'Bootstrap.php';

        if (is_readable($path)) require $path;

        // Si le répartiteur d'événements est défini sur l'application, nous déclencherons un événement
        // avec l'instance Two pour offrir à chaque auditeur la possibilité de
        // enregistre leurs commandes sur cette application avant qu'elle ne démarre.
        if (isset($this->container['events'])) {
            $events = $this->container['events'];

            $events->dispatch('forge.start', array($this));
        }

        return $this;
    }

    /**
     * Exécutez l'application console.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function handle($input, $output = null)
    {
        try {
            return $this->run($input, $output);
        }
        catch (Exception $e) {
            $this->manageException($output, $e);

            return 1;
        }
        catch (Throwable $e) {
            $this->manageException($output, new FatalThrowableError($e));

            return 1;
        }
    }

    /**
     * Signaler et restituer l’exception donnée.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  \Exception  $e
     * @return void
     */
    protected function manageException(OutputInterface $output, Exception $e)
    {
        $handler = $this->getExceptionHandler();

        $handler->report($e);

        $handler->renderForConsole($output, $e);
    }

    /**
     * Exécutez une commande de deux consoles par son nom.
     *
     * @param  string  $command
     * @param  array   $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function call($command, array $parameters = array(), OutputInterface $output = null)
    {
        $parameters['command'] = $command;

        // À moins qu'une implémentation d'interface de sortie ne nous soit spécifiquement transmise, nous
        // utilisera l'implémentation "NullOutput" par défaut pour conserver toute écriture
        // supprimé afin qu'il ne soit pas divulgué au navigateur ou à toute autre source.
        $output = $output ?: new NullOutput;

        $input = new ArrayInput($parameters);

        return $this->find($command)->run($input, $output);
    }

    /**
     * Ajoutez une commande à la console.
     *
     * @param  \Symfony\Component\Console\Command\Command  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function add(SymfonyCommand $command)
    {
        if ($command instanceof Command) {
            $command->setContainer($this->container);
        }

        return $this->addToParent($command);
    }

    /**
     * Ajoutez la commande à l'instance parent.
     *
     * @param  \Symfony\Component\Console\Command\Command  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    protected function addToParent(SymfonyCommand $command)
    {
        return parent::add($command);
    }

    /**
     * Ajoutez une commande, résolvant via l'application.
     *
     * @param  string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function resolve($command)
    {
        return $this->add($this->container[$command]);
    }

    /**
     * Résolvez un tableau de commandes via l'application.
     *
     * @param  array|mixed  $commands
     * @return void
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        foreach ($commands as $command)  {
            $this->resolve($command);
        }
    }

    /**
     * Enregistrez une commande basée sur la fermeture auprès de l'application.
     *
     * @param  string  $signature
     * @param  Closure  $callback
     * @return \Two\Console\ClosureCommand
     */
    public function command($signature, Closure $callback)
    {
        $command = new ClosureCommand($signature, $callback);

        return $this->add($command);
    }

    /**
     * Obtenez les définitions d'entrée par défaut pour les applications.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Obtenez l’option d’environnement global pour la définition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }

    /**
     * Définissez l’instance de deux applications.
     *
     * @param  \Two\Application\Two  $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Définissez si l'application Console doit se fermer automatiquement une fois terminé.
     *
     * @param  bool  $boolean
     * @return $this
     */
    public function setAutoExit($boolean)
    {
        parent::setAutoExit($boolean);

        return $this;
    }

    /**
     * Obtenez l’instance du gestionnaire d’exceptions.
     *
     * @return \Two\Exceptions\Contracts\HandlerInterface
     */
    public function getExceptionHandler()
    {
        return $this->container->make('Two\Exceptions\Contracts\HandlerInterface');
    }
}
