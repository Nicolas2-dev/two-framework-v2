<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Commands\Command;


class PackageOptimizeCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'package:optimize';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Optimize the packages cache for better performance';

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Generating optimized packages cache');

        $this->container['packages']->optimize();
    }
}
