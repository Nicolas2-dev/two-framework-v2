<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;
use Two\Support\Str;


class TableCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:table';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a migration for the queue jobs database table';

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Créez une nouvelle instance de commande de table de travaux de file d'attente.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  \Two\Foundation\Composer    $composer
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $table = $this->container['config']['queue.connections.database.table'];

        $tableClassName = Str::studly($table);

        $fullPath = $this->createBaseMigration($table);

        $stubPath = __DIR__ .DS . 'stubs' .DS .'jobs.stub';

        $stub = str_replace(
            ['{{table}}', '{{tableClassName}}'], [$table, $tableClassName], $this->files->get($stubPath)
        );

        $this->files->put($fullPath, $stub);

        $this->info('Migration created successfully!');
    }

    /**
     * Créez un fichier de migration de base pour la table.
     *
     * @param  string  $table
     * @return string
     */
    protected function createBaseMigration($table = 'jobs')
    {
        $name = 'create_'.$table.'_table';

        $path = $this->container['path'] .DS .'Database' .DS .'Migrations';

        return $this->container['migration.creator']->create($name, $path);
    }
}
