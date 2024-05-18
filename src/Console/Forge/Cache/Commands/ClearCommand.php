<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Cache\Commands;

use Two\Cache\CacheManager;
use Two\Filesystem\Filesystem;
use Two\Console\Commands\Command;


class ClearCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'cache:clear';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Flush the Application cache";

    /**
     * L'instance du gestionnaire de cache.
     *
     * @var \Two\Cache\CacheManager
     */
    protected $cache;

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Créez une nouvelle instance de commande Cache Clear.
     *
     * @param  \Two\Cache\CacheManager  $cache
     * @param  \Two\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(CacheManager $cache, Filesystem $files)
    {
        parent::__construct();

        $this->cache = $cache;
        $this->files = $files;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->flush();

        $this->files->delete($this->container['config']['app.manifest'] .DS .'services.php');

        $this->info('Application cache cleared!');
    }

}
