<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use Two\Container\Container;
use Two\Console\Commands\Command;


class Seeder
{
    /**
     * L'instance de conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * L'instance de commande de console.
     *
     * @var \Two\Console\Command
     */
    protected $command;

    /**
     * Exécutez les graines de la base de données.
     *
     * @return void
     */
    public function run() {}

    /**
     * Amorce la connexion donnée à partir du chemin donné.
     *
     * @param  string  $class
     * @return void
     */
    public function call($class)
    {
        $this->resolve($class)->run();

        if (isset($this->command)) {
            $this->command->getOutput()->writeln("<info>Seeded:</info> $class");
        }
    }

    /**
     * Résolvez une instance de la classe seeder donnée.
     *
     * @param  string  $class
     * @return \Two\Database\Seeder
     */
    protected function resolve($class)
    {
        if (isset($this->container)) {
            $instance = $this->container->make($class);

            $instance->setContainer($this->container);
        } else {
            $instance = new $class;
        }

        if (isset($this->command)) {
            $instance->setCommand($this->command);
        }

        return $instance;
    }

    /**
     * Définissez l'instance de conteneur IoC.
     *
     * @param  \Two\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Définissez l’instance de commande de console.
     *
     * @param  \Two\Console\Commands\Command  $command
     * @return $this
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;

        return $this;
    }

}
