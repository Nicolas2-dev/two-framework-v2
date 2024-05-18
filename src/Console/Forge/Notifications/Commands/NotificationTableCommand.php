<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Notifications\Commands;

use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;


class NotificationTableCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'notifications:table';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a migration for the notifications table';

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Créez une nouvelle instance de commande de table de notifications.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  mixed $composer
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        //
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

        //
        $path = __DIR__ .str_replace('/', DS, '/stubs/notifications.stub');

        $this->files->put($fullPath, $this->files->get($path));

        $this->info('Migration created successfully!');

        $this->call('optimize');
    }

    /**
     * Créez un fichier de migration de base pour les notifications.
     *
     * @return string
     */
    protected function createBaseMigration()
    {
        $path = $this->container['path'] .DS .'Database' .DS .'Migrations';

        return $this->container['migration.creator']->create('create_notifications_table', $path);
    }
}
