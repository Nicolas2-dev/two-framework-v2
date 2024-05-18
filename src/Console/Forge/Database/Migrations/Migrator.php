<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Migrations;

use Two\Filesystem\Filesystem;
use Two\Database\Contracts\ConnectionResolverInterface as Resolver;
use Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface;


class Migrator
{
    /**
     * La mise en œuvre du référentiel de migration.
     *
     * @var MigrationRepositoryInterface
     */
    protected $repository;

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * L'instance du résolveur de connexion.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Le nom de la connexion par défaut.
     *
     * @var string
     */
    protected $connection;

    /**
     * Les notes pour l'opération en cours.
     *
     * @var array
     */
    protected $notes = array();

    /**
     * Créez une nouvelle instance de migrateur.
     *
     * @param  \Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface  $repository
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $resolver
     * @param  \Two\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(MigrationRepositoryInterface $repository,
                                Resolver $resolver,
                                Filesystem $files)
    {
        $this->files = $files;
        $this->resolver = $resolver;
        $this->repository = $repository;
    }

    /**
     * Exécutez les migrations en attente sur un chemin donné.
     *
     * @param  string  $path
     * @param  bool    $pretend
     * @param  string|null  $group
     * @return void
     */
    public function run($path, $pretend = false, $group = null)
    {
        $this->notes = array();

        $files = $this->getMigrationFiles($path);

        // Une fois que nous aurons récupéré tous les fichiers de migration pour le chemin, nous les comparerons
        // contre les migrations déjà exécutées pour ce package alors
        // exécute chacune des migrations en attente sur une connexion à une base de données.
        $ran = $this->repository->getRan();

        $migrations = array_diff($files, $ran);

        $this->requireFiles($path, $migrations);

        $this->runMigrationList($migrations, $pretend, $group);
    }

    /**
     * Exécutez une série de migrations.
     *
     * @param  array  $migrations
     * @param  bool   $pretend
     * @param  string|null  $group
     * @return void
     */
    public function runMigrationList($migrations, $pretend = false, $group = null)
    {
        $group = $group ?: 'app';

        // Tout d’abord, nous allons simplement nous assurer qu’il y a des migrations à exécuter. S'il y a
        // ce n'est pas le cas, nous en prendrons simplement note au développeur pour qu'il en soit informé
        // que toutes les migrations ont été exécutées sur ce système de base de données.
        if (count($migrations) === 0) {
            $this->note('<info>Nothing to migrate.</info>');

            return;
        }

        $batch = $this->repository->getNextBatchNumber($group);

        // Une fois que nous aurons l'éventail de migrations, nous les parcourrons et exécuterons le
        // migrations "up" donc les modifications sont apportées aux bases de données. Nous nous connecterons ensuite
        // que la migration a été exécutée afin que nous ne la répétions pas la prochaine fois que nous l'exécuterons.
        foreach ($migrations as $file) {
            $this->runUp($file, $batch, $pretend, $group);
        }
    }

    /**
     * Exécutez "up" une instance de migration.
     *
     * @param  string  $file
     * @param  int     $batch
     * @param  bool    $pretend
     * @param  string  $group
     * @return void
     */
    protected function runUp($file, $batch, $pretend, $group)
    {
        // Nous allons d’abord résoudre une instance « réelle » de la classe de migration à partir de ce
        // nom du fichier de migration. Une fois que nous avons les instances, nous pouvons exécuter le véritable
        // commande telle que "up" ou "down", ou on peut simplement simuler l'action.
        $migration = $this->resolve($file);

        if ($pretend) {
            return $this->pretendToRun($migration, 'up');
        }

        $migration->up();

        // Une fois que nous aurons exécuté une classe de migrations, nous enregistrerons qu'elle a été exécutée dans ce
        // référentiel pour ne pas essayer de l'exécuter la prochaine fois que nous effectuerons une migration
        // Dans l'application. Un référentiel de migration conserve l'ordre de migration.
        $this->repository->log($file, $batch, $group);

        $this->note("<info>Migrated:</info> $file");
    }

    /**
     * Annulez la dernière opération de migration.
     *
     * @param  bool  $pretend
     * @param  string|null  $group
     * @return int
     */
    public function rollback($pretend = false, $group = null)
    {
        $group = $group ?: 'app';

        //
        $this->notes = array();

        // Nous souhaitons récupérer le dernier lot de migrations exécutées lors de la précédente
        // opération de migration. Nous annulerons ensuite ces migrations et les exécuterons chacune
        // d'entre eux "down" pour annuler la dernière "opération" de migration exécutée.
        $migrations = $this->repository->getLast($group);

        if (count($migrations) === 0) {
            $this->note('<info>Nothing to rollback.</info>');

            return count($migrations);
        }

        // Nous devons inverser ces migrations afin qu'elles soient « abattues » à l'envers.
        // à ce sur quoi ils s'exécutent "up". Cela nous permet de revenir en arrière dans les migrations
        // et inverse correctement l'intégralité de l'opération de schéma de base de données exécutée.
        foreach ($migrations as $migration) {
            $this->runDown((object) $migration, $pretend);
        }

        return count($migrations);
    }

    /**
     * Exécutez « vers le bas » une instance de migration.
     *
     * @param  object  $migration
     * @param  bool    $pretend
     * @return void
     */
    protected function runDown($migration, $pretend)
    {
        $file = $migration->migration;

        // Nous obtiendrons d'abord le nom du fichier de la migration afin de pouvoir résoudre un
        // instance de la migration. Une fois que nous obtenons une instance, nous pouvons soit exécuter un
        // simulation d'exécution de la migration ou nous pouvons exécuter la vraie migration.
        $instance = $this->resolve($file);

        if ($pretend) {
            return $this->pretendToRun($instance, 'down');
        }

        $instance->down();

        // Une fois que nous aurons exécuté avec succès la migration, nous la supprimerons de
        // le dépôt de migration donc il sera considéré comme n'ayant pas été exécuté
        // par l'application pourra alors se déclencher par toute opération ultérieure.
        $this->repository->delete($migration);

        $this->note("<info>Rolled back:</info> $file");
    }

    /**
     * Obtenez tous les fichiers de migration dans un chemin donné.
     *
     * @param  string  $path
     * @return array
     */
    public function getMigrationFiles($path)
    {
        $files = $this->files->glob($path .'/*_*.php');

        // Une fois que nous avons le tableau de fichiers dans le répertoire, nous supprimerons simplement le
        // extension et prends le nom de base du fichier qui est tout ce dont nous avons besoin quand
        // recherche les migrations qui n'ont pas été exécutées sur les bases de données.
        if ($files === false) return array();

        $files = array_map(function($file)
        {
            return str_replace('.php', '', basename($file));

        }, $files);

        // Une fois que nous aurons tous les noms de fichiers formatés, nous les trierons et puisque
        // ils commencent tous par un horodatage, cela devrait nous donner les migrations dans
        // l'ordre dans lequel ils ont été réellement créés par les développeurs d'applications.
        sort($files);

        return $files;
    }

    /**
     * Exiger dans tous les fichiers de migration dans un chemin donné.
     *
     * @param  string  $path
     * @param  array   $files
     * @return void
     */
    public function requireFiles($path, array $files)
    {
        foreach ($files as $file) {
            $this->files->requireOnce($path .DS .$file .'.php');
        }
    }

    /**
     * Faites semblant d'exécuter les migrations.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function pretendToRun($migration, $method)
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Obtenez toutes les requêtes qui seraient exécutées pour une migration.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        $connection = $migration->getConnection();

        // Maintenant que nous avons les connexions, nous pouvons résoudre le problème et faire semblant d'exécuter le
        // requêtes sur la base de données renvoyant le tableau d'instructions SQL brutes
        // qui serait déclenché sur le système de base de données pour cette migration.

        $db = $this->resolveConnection($connection);

        return $db->pretend(function() use ($migration, $method)
        {
            $migration->$method();
        });
    }

    /**
     * Résolvez une instance de migration à partir d'un fichier.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        $file = implode('_', array_slice(explode('_', $file), 4));

        $class = studly_case($file);

        return new $class;
    }

    /**
     * Déclenchez un événement de note pour le migrateur.
     *
     * @param  string  $message
     * @return void
     */
    protected function note($message)
    {
        $this->notes[] = $message;
    }

    /**
     * Obtenez les notes de la dernière opération.
     *
     * @return array
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Résolvez l’instance de connexion à la base de données.
     *
     * @param  string  $connection
     * @return \Two\Database\Connection
     */
    public function resolveConnection($connection)
    {
        return $this->resolver->connection($connection);
    }

    /**
     * Définissez le nom de connexion par défaut.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnection($name)
    {
        if (! is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;
    }

    /**
     * Obtenez l'instance du référentiel de migration.
     *
     * @return \Two\Console\Forge\Database\Contracts\MigrationRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Déterminez si le référentiel de migration existe.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        return $this->repository->repositoryExists();
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
