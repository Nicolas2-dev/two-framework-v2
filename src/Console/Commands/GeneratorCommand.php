<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Commands;

use Two\Support\Str;
use Two\Filesystem\Filesystem;
use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputArgument;


abstract class GeneratorCommand extends Command
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type;


    /**
     * Créez une nouvelle instance de commande de créateur de contrôleur.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        //
        $this->files = $files;
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    abstract protected function getStub();

    /**
     * Exécutez la commande de la console.
     *
     * @return bool|null
     */
    public function handle()
    {
        $name = $this->parseName($this->getNameInput());

        $path = $this->getPath($name);

        if ($this->alreadyExists($this->getNameInput())) {
            $this->error($this->type .' already exists!');

            return false;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($name));

        $this->info($this->type .' created successfully.');
    }

    /**
     * Déterminez si la classe existe déjà.
     *
     * @param  string  $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        $name = $this->parseName($rawName);

        return $this->files->exists($this->getPath($name));
    }

    /**
     * Obtenez le chemin de classe de destination.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = str_replace($this->container->getNamespace(), '', $name);

        return $this->container['path'] .DS .str_replace('\\', DS, $name) .'.php';
    }

    /**
     * Analysez le nom et le format en fonction de l'espace de noms racine.
     *
     * @param  string  $name
     * @return string
     */
    protected function parseName($name)
    {
        $rootNamespace = $this->container->getNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        if (Str::contains($name, '/')) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->parseName($this->getDefaultNamespace(trim($rootNamespace, '\\')) .'\\' .$name);
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Créez le répertoire de la classe si nécessaire.
     *
     * @param  string  $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (! $this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Construisez la classe avec le nom donné.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Remplacez l'espace de noms pour le stub donné.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace('{{namespace}}', $this->getNamespace($name), $stub);

        $stub = str_replace('{{rootNamespace}}', $this->container->getNamespace(), $stub);

        return $this;
    }

    /**
     * Obtenez le nom complet de l’espace de noms pour une classe donnée.
     *
     * @param  string  $name
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Remplacez le nom de classe pour le stub donné.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) .'\\', '', $name);

        return str_replace('{{className}}', $class, $stub);
    }

    /**
     * Obtenez le nom de classe souhaité à partir de l’entrée.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'The name of the class'),
        );
    }
}
