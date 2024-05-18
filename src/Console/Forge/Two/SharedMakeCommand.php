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
use Two\Support\Arr;

use Symfony\Component\Console\Helper\ProgressBar;


class SharedMakeCommand extends Command
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'shared:make';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create the standard Shared namespace';

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le nom du fichier d'assistance personnalisé.
     *
     * @var string
     */
    protected $helpers = 'shared/Support/helpers.php';


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param Two\Filesystem\Filesystem $files
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
     * @return mixed
     */
    public function handle()
    {
        $path = base_path('shared');

        if ($this->files->exists($path)) {
            $this->error('The Shared namespace already exists!');

            return false;
        }

        $steps = array(
            'Generating folders...'          => 'generateFolders',
            'Generating files...'            => 'generateFiles',
            'Updating the composer.json ...' => 'updateComposerJson',
        );

        $progress = new ProgressBar($this->output, count($steps));

        $progress->start();

        foreach ($steps as $message => $method) {
            $progress->setMessage($message);

            call_user_func(array($this, $method), $path);

            $progress->advance();
        }

        $progress->finish();

        $this->info("\nGenerating optimized class loader");

        $this->container['composer']->dumpOptimized();

        $this->info("Package generated successfully.");
    }

    /**
     * Générez les dossiers.
     *
     * @param string $type
     * @return void
     */
    protected function generateFolders($path)
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true, true);
        }

        $this->files->makeDirectory($path .DS .'Language');
        $this->files->makeDirectory($path .DS .'Support');

        // Générez les dossiers de langue.
        $languages = $this->container['config']->get('languages', array());

        foreach (array_keys($languages) as $code) {
            $directory = $path .DS .'Language' .DS .strtoupper($code);

            $this->files->makeDirectory($directory);
        }
    }

    /**
     * Générez les fichiers.
     *
     * @param string $type
     * @return void
     */
    protected function generateFiles($path)
    {
        //
        // Générez le fichier d'aide personnalisé.

        $content ='<?php

//----------------------------------------------------------------------
// Custom Helpers
//----------------------------------------------------------------------
';

        $filePath = $path .DS .'Support' .DS .'helpers.php';

        $this->files->put($filePath, $content);

        //
        // Générez les fichiers de langue.

        $content ='<?php

return array (
);';

        $languages = $this->container['config']->get('languages', array());

        foreach (array_keys($languages) as $code) {
            $filePath = $path .DS .'Language' .DS .strtoupper($code) .DS .'messages.php';

            $this->files->put($filePath, $content);
        }
    }

    /**
     * Mettez à jour le composer.json et exécutez Composer.
     *
     * @param string $type
     * @return void
     */
    protected function updateComposerJson($path)
    {
        $helpers = 'shared/Support/helpers.php';

        //
        $composerJson = getenv('COMPOSER') ?: 'composer.json';

        $path = base_path($composerJson);

        // Obtenez le contenu du composer.json sous une forme décodée.
        $config = json_decode(file_get_contents($path), true);

        if (! is_array($config)) {
            return;
        }

        // Mettre à jour le composer.json
        else if (! in_array($helpers, $files = Arr::get($config, "autoload.files", array()))) {
            array_push($files, $helpers);

            Arr::set($config, "autoload.files", $files);

            $output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

            file_put_contents($path, $output);
        }
    }
}
