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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cache:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush the Application cache";

    /**
     * The Cache Manager instance.
     *
     * @var \Two\Cache\CacheManager
     */
    protected $cache;

    /**
     * The File System instance.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new Cache Clear Command instance.
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
     * Execute the console command.
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
