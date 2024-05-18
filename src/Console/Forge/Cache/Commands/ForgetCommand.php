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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cache:forget';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove an item from the cache';

    /**
     * The cache manager instance.
     *
     * @var \Two\Cache\CacheManager
     */
    protected $cache;


    /**
     * Create a new cache clear command instance.
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
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->driver($this->option('store'))->forget($key = $this->argument('key'));

        $this->info('The [' .$key .'] key has been removed from the cache.');
    }

    /**
     * Get the console command arguments.
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
     * Get the console command options.
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
