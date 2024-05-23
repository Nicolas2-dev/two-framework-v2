<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use FilesystemIterator;

use Two\Filesystem\Filesystem;
use Two\Console\Commands\Command;
use Two\Application\Providers\ServiceProvider;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class VendorPublishCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'vendor:publish';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Publish any publishable assets from vendor packages";

    /**
     * Instance de l’éditeur d’actifs.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;


    /**
     * Créez une nouvelle instance de commande de publication du fournisseur.
     *
     * @param  \Two\Foundation\VendorPublisher  $publisher
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
        $group = $this->input->getArgument('group');

        if (is_null($group)) {
            return $this->publish();
        }

        $groups = explode(',', $group);

        foreach($groups as $group) {
            $this->publish($group);
        }
    }

    /**
     * Publiez les ressources pour un nom de groupe donné.
     *
     * @param  string|null  $group
     * @return void
     */
    protected function publish($group = null)
    {
        $paths = ServiceProvider::pathsToPublish($group);

        if (empty($paths)) {
            if (is_null($group)) {
                return $this->comment("Nothing to publish.");
            }

            return $this->comment("Nothing to publish for group [{$group}].");
        }

        foreach ($paths as $from => $to) {
            if ($this->files->isFile($from)) {
                $this->publishFile($from, $to);
            } else if ($this->files->isDirectory($from)) {
                $this->publishDirectory($from, $to);
            } else {
                $this->error("Can't locate path: <{$from}>");
            }
        }

        if (is_null($group)) {
            return $this->info("Publishing complete!");
        }

        $this->info("Publishing complete for group [{$group}]!");
    }

    /**
     * Publiez le fichier sur le chemin indiqué.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function publishFile($from, $to)
    {
        if ($this->files->exists($to) && ! $this->option('force')) {
            return;
        }

        $directory = dirname($to);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->copy($from, $to);

        $this->status($from, $to, 'File');
    }

    /**
     * Publiez le répertoire dans le répertoire donné.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    protected function publishDirectory($from, $to)
    {
        $this->copyDirectory($from, $to);

        $this->status($from, $to, 'Directory');
    }

    /**
     * Copiez un répertoire d'un emplacement à un autre.
     *
     * @param  string  $directory
     * @param  string  $destination
     * @param  bool  $force
     * @return bool
     */
    public function copyDirectory($directory, $destination)
    {
        if (! $this->files->isDirectory($directory)) {
            return false;
        }

        if (! $this->files->isDirectory($destination)) {
            $this->files->makeDirectory($destination, 0777, true);
        }

        $items = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $target = $destination .DS .$item->getBasename();

            if ($item->isDir()) {
                if (! $this->copyDirectory($item->getPathname(), $target)) {
                    return false;
                }

                continue;
            }

            // L'élément actuel est un fichier.
            if ($this->files->exists($target) && ! $this->option('force')) {
                continue;
            } else if (! $this->files->copy($item->getPathname(), $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Écrivez un message d'état sur la console.
     *
     * @param  string  $from
     * @param  string  $to
     * @param  string  $type
     * @return void
     */
    protected function status($from, $to, $type)
    {
        $from = str_replace(base_path(), '', realpath($from));

        $to = str_replace(base_path(), '', realpath($to));

        $this->output->writeln('<info>Copied '.$type.'</info> <comment>['.$from.']</comment> <info>To</info> <comment>['.$to.']</comment>');
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('group', InputArgument::OPTIONAL, 'The name of assets group being published.'),
        );
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
        );
    }
}
