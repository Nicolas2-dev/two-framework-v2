<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Migrations;

use Closure;

use Two\Filesystem\Filesystem;


class MigrationCreator
{
    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le courrier recommandé crée des accroches.
     *
     * @var array
     */
    protected $postCreate = array();

    /**
     * Créez une nouvelle instance de créateur de migration.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Créez une nouvelle migration sur le chemin indiqué.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string  $table
     * @param  bool    $create
     * @return string
     */
    public function create($name, $path, $table = null, $create = false)
    {
        $path = $this->getPath($name, $path);

        // Nous obtiendrons d’abord le fichier stub pour la migration, qui sert de type
        // du modèle pour la migration. Une fois que nous les aurons, nous remplirons le
        // divers espaces réservés, enregistrez le fichier et exécutez l'événement post-création.
        $stub = $this->getStub($table, $create);

        $this->files->put($path, $this->populateStub($name, $stub, $table));

        $this->firePostCreateHooks();

        return $path;
    }

    /**
     * Obtenez le fichier stub de migration.
     *
     * @param  string  $table
     * @param  bool    $create
     * @return string
     */
    protected function getStub($table, $create)
    {
        if (is_null($table)) {
            return $this->files->get($this->getStubPath() .DS .'blank.stub');
        }

        // Nous avons également des stubs pour créer de nouvelles tables et modifier des tables existantes
        // pour éviter au développeur de taper lors de la création de nouvelles tables
        // ou en modifiant les tables existantes. Nous allons récupérer le talon approprié ici.
        else {
            $stub = $create ? 'create.stub' : 'update.stub';

            return $this->files->get($this->getStubPath() .DS .$stub);
        }
    }

    /**
     * Remplissez les espaces réservés dans le talon de migration.
     *
     * @param  string  $name
     * @param  string  $stub
     * @param  string  $table
     * @return string
     */
    protected function populateStub($name, $stub, $table)
    {
        $stub = str_replace('{{class}}', $this->getClassName($name), $stub);

        // Ici, nous remplacerons les espaces réservés du tableau par le tableau spécifié par
        // le développeur, ce qui est utile pour créer rapidement une création de tables
        // ou mettre à jour la migration depuis la console au lieu de la saisir manuellement.
        if (! is_null($table)) {
            $stub = str_replace('{{table}}', $table, $stub);
        }

        return $stub;
    }

    /**
     * Obtenez le nom de classe d’un nom de migration.
     *
     * @param  string  $name
     * @return string
     */
    protected function getClassName($name)
    {
        return studly_case($name);
    }

    /**
     * Lancez le message enregistré et créez des crochets.
     *
     * @return void
     */
    protected function firePostCreateHooks()
    {
        foreach ($this->postCreate as $callback) {
            call_user_func($callback);
        }
    }

    /**
     * Enregistrez un hook de création post-migration.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function afterCreate(Closure $callback)
    {
        $this->postCreate[] = $callback;
    }

    /**
     * Obtenez le nom de chemin complet vers la migration.
     *
     * @param  string  $name
     * @param  string  $path
     * @return string
     */
    protected function getPath($name, $path)
    {
        return $path .DS .$this->getDatePrefix() .'_' .$name.'.php';
    }

    /**
     * Obtenez le préfixe de date pour la migration.
     *
     * @return string
     */
    protected function getDatePrefix()
    {
        return date('Y_m_d_His');
    }

    /**
     * Obtenez le chemin d’accès aux talons.
     *
     * @return string
     */
    public function getStubPath()
    {
        return __DIR__ .DS .'stubs';
    }

    /**
     * Obtenez l'instance du système de fichiers.
     *
     * @return \Two\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

}
