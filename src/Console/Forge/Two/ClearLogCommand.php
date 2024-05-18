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


class ClearLogCommand extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'log:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear log files';


    /**
     * Create a new key generator command.
     *
     * @param \Two\Filesystem\Filesystem $files
     * @author Sang Nguyen
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     * @author Sang Nguyen
     */
    public function handle()
    {
        $pattern = storage_path('logs') .DS .'*.log';

        $files = $this->files->glob($pattern);

        foreach ($files as $file) {
            $this->files->delete($file);
        }

        $this->info('Log files cleared successfully!');
    }
}
