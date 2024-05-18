<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Cache\Commands;

use Two\Cache\CacheManager;
use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ForgetCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'cache:forget';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Remove an item from the cache';

    /**
     * L'instance du gestionnaire de cache.
     *
     * @var \Two\Cache\CacheManager
     */
    protected $cache;


    /**
     * Créez une nouvelle instance de commande d'effacement du cache.
     *
     * @param  \Two\Cache\CacheManager  $cache
     * @return void
     */
    public function __construct(CacheManager $cache)
    {
        parent::__construct();

        $this->cache = $cache;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->driver($this->option('store'))->forget($key = $this->argument('key'));

        $this->info('The [' .$key .'] key has been removed from the cache.');
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('key', InputArgument::REQUIRED, 'The key to remove'),
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
            array('store', null, InputOption::VALUE_OPTIONAL, 'The store to remove the key from.'),
        );
    }
}
