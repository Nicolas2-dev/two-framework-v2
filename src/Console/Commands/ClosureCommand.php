<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Commands;

use Closure;
use ReflectionFunction;

use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ClosureCommand extends Command
{
    /**
     * The command callback.
     *
     * @var \Closure
     */
    protected $callback;

    /**
     * 
     */
    protected $signature;

    
    /**
     * Create a new command instance.
     *
     * @param  string  $signature
     * @param  Closure  $callback
     * @return void
     */
    public function __construct($signature, Closure $callback)
    {
        $this->name = $signature;

        //
        $this->callback  = $callback;
        $this->signature = $signature;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inputs = array_merge($input->getArguments(), $input->getOptions());

        $parameters = array();

        //
        $reflection = new ReflectionFunction($this->callback);

        foreach ($reflection->getParameters() as $parameter) {
            if (isset($inputs[$parameter->name])) {
                $parameters[$parameter->name] = $inputs[$parameter->name];
            }
        }

        return $this->container->call(
            $this->callback->bindTo($this, $this), $parameters
        );
    }

    /**
     * Set the description for the command.
     *
     * @param  string  $description
     * @return $this
     */
    public function describe($description)
    {
        $this->setDescription($description);

        return $this;
    }
}
