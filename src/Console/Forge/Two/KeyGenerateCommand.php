<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Support\Str;
use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;


class KeyGenerateCommand extends Command
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'key:generate';

    /**
     * Description de la commande de console.
     *
     * @var string
     */
    protected $description = "Set the Application Key";

    /**
     * 
     */
    protected $files;

    
    /**
     * Créez une nouvelle commande Key Generator.
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
        list($path, $contents) = $this->getKeyFile();

        $key = $this->getRandomKey();

        $contents = str_replace($this->container['config']['app.key'], $key, $contents);

        $this->files->put($path, $contents);

        $this->container['config']['app.key'] = $key;

        $this->info("Application key [$key] set successfully.");
    }

    /**
     * Obtenez le fichier clé et son contenu.
     *
     * @return array
     */
    protected function getKeyFile()
    {
        $path = $this->container['path'] .DS .'Config' .DS .'App.php';

        $contents = $this->files->get($path);

        return array($path, $contents);
    }

    /**
     * Générez une clé aléatoire pour l'application.
     *
     * @return string
     */
    protected function getRandomKey()
    {
        return Str::random(32);
    }

}
