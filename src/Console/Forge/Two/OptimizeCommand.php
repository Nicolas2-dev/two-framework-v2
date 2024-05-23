<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;
use Two\Application\Composer;

use Symfony\Component\Console\Input\InputOption;


class OptimizeCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'two:optimize';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Optimize the Framework for better performance";

    /**
     * L'instance du compositeur.
     *
     * @var \Two\Foundation\Composer
     */
    protected $composer;

    /**
     * Créez une nouvelle instance de commande d'optimisation.
     *
     * @param  \Two\Foundation\Composer  $composer
     * @return void
     */
    public function __construct(Composer $composer)
    {
        parent::__construct();

        $this->composer = $composer;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Generating optimized class loader');

        if ($this->option('psr')) {
            $this->composer->dumpAutoloads();
        } else {
            $this->composer->dumpOptimized();
        }

        $this->call('clear-compiled');
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('psr', null, InputOption::VALUE_NONE, 'Do not optimize Composer dump-autoload.'),
        );
    }

}
