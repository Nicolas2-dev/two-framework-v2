<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Cache\Commands;

use Two\Filesystem\Filesystem;
use Two\Console\Commands\Command;


class CacheTableCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'cache:table';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a migration for the Cache database table';

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Créez une nouvelle instance de commande de table de session.
     *
     * @param  \Two\Filesystem\Filesystem  $files
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
        $fullPath = $this->createBaseMigration();

        $stubPath = realpath(__DIR__) .str_replace('/', DS, '/stubs/cache.stub');

        $this->files->put($fullPath, $this->files->get($stubPath));

        $this->info('Migration created successfully!');

        $this->call('optimize');
    }

    /**
     * Créez un fichier de migration de base pour la table.
     *
     * @return string
     */
    protected function createBaseMigration()
    {
        $name = 'create_cache_table';

        $path = $this->container['path'] .DS .'Database' .DS .'Migrations';

        return $this->container['migration.creator']->create($name, $path);
    }

}
