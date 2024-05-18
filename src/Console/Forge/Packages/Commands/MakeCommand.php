<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Commands\Command as CommandGenerator;
use Two\Filesystem\Filesystem;
use Two\Packages\PackageManager;
use Two\Support\Str;


class MakeCommand extends CommandGenerator
{
    /**
     * Dossiers de packages à créer.
     *
     * @var array
     */
    protected $listFolders = array();

    /**
     * Fichiers de package à créer.
     *
     * @var array
     */
    protected $listFiles = array();

    /**
     * Option de signature du package.
     *
     * @var array
     */
    protected $signOption = array();

    /**
     * Stubs de package utilisés pour remplir les fichiers définis.
     *
     * @var array
     */
    protected $listStubs = array();

    /**
     * L'instance des packages.
     *
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * Le chemin des packages.
     *
     * @var string
     */
    protected $packagePath;

    /**
     * Les informations sur les forfaits.
     *
     * @var Two\Support\Collection;
     */
    protected $packageInfo;

    /**
     * L'instance du système de fichiers.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Tableau pour stocker les détails de configuration.
     *
     * @var array
     */
    protected $data;

    /**
     * Chaîne pour stocker le type de commande.
     *
     * @var string
     */
    protected $type;


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param Filesystem $files
     * @param \Two\Packages\PackageManager    $package
     */
    public function __construct(Filesystem $files, PackageManager $packages)
    {
        parent::__construct();

        //
        $this->files = $files;

        $this->packages = $packages;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        $slug = $this->parseSlug($this->argument('slug'));
        $name = $this->parseName($this->argument('name'));

        if (! $this->packages->exists($slug)) {
            return $this->error('Package ['.$this->data['slug'].'] does not exist.');
        }

        $this->packageInfo = collect(
            $this->packages->where('slug', $slug)
        );

        // Vérifiez le type approprié de
        $type = $this->packageInfo->get('type');

        if (($type != 'package') && ($type != 'module')) {
            return $this->error('Package [' .$this->data['slug'] .'] has no generator of this type.');
        }

        $this->packagePath = ($type == 'module')
            ? $this->packages->getModulesPath()
            : $this->packages->getPackagesPath();

        $this->data['slug'] = $slug;
        $this->data['name'] = $name;

        return $this->generate();
    }

    /**
     * générer la commande console.
     *
     * @return mixed
     */
    protected function generate()
    {
        foreach ($this->listFiles as $key => $file) {
            $filePath = $this->makeFilePath($this->listFolders[$key], $this->data['name']);

            $this->resolveByPath($filePath);

            $file = $this->formatContent($file);

            //
            $find = basename($filePath);

            $filePath = strrev(preg_replace(strrev("/$find/"), '', strrev($filePath), 1));

            $filePath = $filePath .$file;

            if ($this->files->exists($filePath)) {
                return $this->error($this->type .' already exists!');
            }

            $this->makeDirectory($filePath);

            foreach ($this->signOption as $option) {
                if ($this->option($option)) {
                    $stubFile = $this->listStubs[$option][$key];

                    $this->resolveByOption($this->option($option));

                    break;
                }
            }

            if (! isset($stubFile)) {
                $stubFile = $this->listStubs['default'][$key];
            }

            $this->files->put($filePath, $this->getStubContent($stubFile));
        }

        return $this->info($this->type.' created successfully.');
    }

    /**
     * Résolvez le conteneur après avoir obtenu le chemin du fichier.
     *
     * @param string $FilePath
     *
     * @return array
     */
    protected function resolveByPath($filePath)
    {
        //
    }

    /**
     * Résolvez le conteneur après avoir obtenu l’option de saisie.
     *
     * @param string $option
     *
     * @return array
     */
    protected function resolveByOption($option)
    {
        //
    }

    /**
     * Analyser le nom du slug du package.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function parseSlug($slug)
    {
        return Str::snake($slug);
    }

    /**
     * Analyser le nom de la classe du package.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function parseName($name)
    {
        if (str_contains($name, '\\')) {
            $name = str_replace('\\', '/', $name);
        }

        if (str_contains($name, '/')) {
            $formats = collect(explode('/', $name))->map(function ($name)
            {
                return Str::studly($name);
            });

            $name = $formats->implode('/');
        } else {
            $name = Str::studly($name);
        }

        return $name;
    }

    /**
     * Créez FilePath.
     *
     * @param string $folder
     * @param string $name
     *
     * @return string
     */
    protected function makeFilePath($folder, $name)
    {
        $folder = ltrim($folder, '\/');
        $folder = rtrim($folder, '\/');

        $name = ltrim($name, '\/');
        $name = rtrim($name, '\/');

        if ($this->packageInfo->get('type') == 'module') {
            return $this->packagePath .DS .$this->packageInfo->get('basename') .DS .$folder .DS .$name;
        }

        return $this->packagePath .DS .$this->packageInfo->get('basename') .DS .'src' .DS .$folder .DS .$name;
    }

    /**
     * Créez un nom de fichier.
     *
     * @param string $filePath
     *
     * @return string
     */
    protected function makeFileName($filePath)
    {
        return basename($filePath);
    }

    /**
     * Créez le répertoire de la classe si nécessaire.
     *
     * @param string $path
     *
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Récupère l'espace de noms du fichier actuel.
     *
     * @param string $file
     *
     * @return string
     */
    protected function getNamespace($file)
    {
        $basename = $this->packageInfo->get('basename');

        if ($this->packageInfo->get('type') == 'module') {
            $namespace = str_replace($this->packagePath .DS .$basename, '', $file);
        } else {
            $namespace = str_replace($this->packagePath .DS .$basename .DS .'src', '', $file);
        }

        $find = basename($namespace);

        $namespace = strrev(preg_replace(strrev("/$find/"), '', strrev($namespace), 1));

        $namespace = $this->packageInfo->get('namespace') .'\\' .trim($namespace, '\\/');

        return str_replace('/', '\\', $namespace);
    }

    /**
     * Obtenez l’espace de noms de base du package configuré.
     *
     * @return string
     */
    protected function getBaseNamespace()
    {
        if ($this->packageInfo->get('type') == 'module') {
            return $this->packages->getModulesNamespace();
        }

        return $this->packages->getPackagesNamespace();
    }

    /**
     * Obtenez le contenu du stub par clé.
     *
     * @param int $key
     *
     * @return string
     */
    protected function getStubContent($stubName)
    {
        $stubPath = $this->getStubsPath() .$stubName;

        $content = $this->files->get($stubPath);

        return $this->formatContent($content);
    }

    /**
     * Obtenez le chemin des stubs.
     *
     * @return string
     */
    protected function getStubsPath()
    {
        return dirname(__FILE__) .DS .'stubs' .DS;
    }

    /**
     * Remplacez le texte de l'espace réservé par des valeurs correctes.
     *
     * @return string
     */
    protected function formatContent($content)
    {
        //
    }
}
