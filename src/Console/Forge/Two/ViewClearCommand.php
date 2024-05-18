<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;

use RuntimeException;


class ViewClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'view:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Clear all compiled View files";

    /**
     * The File System instance.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Create a new View Clear Command instance.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $cachePath
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        //
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $path = $this->container['config']->get('view.compiled');

        if (! $this->files->exists($path)) {
            throw new RuntimeException('View path not found.');
        }

        foreach ($this->files->glob("{$path}/*.php") as $view) {
            $this->files->delete($view);
        }

        $this->info('Compiled views cleared!');
    }
}
