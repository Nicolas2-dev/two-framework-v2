<?php 
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema;

use Closure;

use Two\Database\Connection;


class Builder
{
    /**
     * L'instance de connexion à la base de données.
     *
     * @var \Two\Database\Connection
     */
    protected $connection;

    /**
     * L’instance de grammaire de schéma.
     *
     * @var \Two\Database\Schema\Grammar
     */
    protected $grammar;

    /**
     * Le rappel du résolveur Blueprint.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * La longueur de chaîne par défaut pour les migrations.
     *
     * @var int|null
     */
    public static $defaultStringLength = 255;

    /**
     * Créez un nouveau gestionnaire de schéma de base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @return void
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        $this->grammar = $connection->getSchemaGrammar();
    }

    /**
     * Définissez la longueur de chaîne par défaut pour les migrations.
     *
     * @param  int  $length
     * @return void
     */
    public static function defaultStringLength($length)
    {
        static::$defaultStringLength = $length;
    }

    /**
     * Déterminez si la table donnée existe.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        $sql = $this->grammar->compileTableExists();

        $table = $this->connection->getTablePrefix().$table;

        return count($this->connection->select($sql, array($table))) > 0;
    }

    /**
     * Déterminez si le tableau donné a une colonne donnée.
     *
     * @param  string  $table
     * @param  string  $column
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        $column = strtolower($column);

        return in_array($column, array_map('strtolower', $this->getColumnListing($table)));
    }

    /**
     * Obtenez la liste des colonnes pour une table donnée.
     *
     * @param  string  $table
     * @return array
     */
    public function getColumnListing($table)
    {
        $table = $this->connection->getTablePrefix().$table;

        $results = $this->connection->select($this->grammar->compileColumnExists($table));

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Modifier une table sur le schéma.
     *
     * @param  string    $table
     * @param  \Closure  $callback
     * @return \Two\Database\Schema\Blueprint
     */
    public function table($table, Closure $callback)
    {
        $this->build($this->createBlueprint($table, $callback));
    }

    /**
     * Créez une nouvelle table sur le schéma.
     *
     * @param  string    $table
     * @param  \Closure  $callback
     * @return \Two\Database\Schema\Blueprint
     */
    public function create($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        $this->build($blueprint);
    }

    /**
     * Supprimez une table du schéma.
     *
     * @param  string  $table
     * @return \Two\Database\Schema\Blueprint
     */
    public function drop($table)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->drop();

        $this->build($blueprint);
    }

    /**
     * Supprimez une table du schéma si elle existe.
     *
     * @param  string  $table
     * @return \Two\Database\Schema\Blueprint
     */
    public function dropIfExists($table)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->dropIfExists();

        $this->build($blueprint);
    }

    /**
     * Renommez une table sur le schéma.
     *
     * @param  string  $from
     * @param  string  $to
     * @return \Two\Database\Schema\Blueprint
     */
    public function rename($from, $to)
    {
        $blueprint = $this->createBlueprint($from);

        $blueprint->rename($to);

        $this->build($blueprint);
    }

    /**
     * Exécutez le plan pour créer/modifier la table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @return void
     */
    protected function build(Blueprint $blueprint)
    {
        $blueprint->build($this->connection, $this->grammar);
    }

    /**
     * Créez un nouveau jeu de commandes avec une fermeture.
     *
     * @param  string    $table
     * @param  \Closure  $callback
     * @return \Two\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        if (isset($this->resolver))
        {
            return call_user_func($this->resolver, $table, $callback);
        }

        return new Blueprint($table, $callback);
    }

    /**
     * Obtenez l'instance de connexion à la base de données.
     *
     * @return \Two\Database\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Définissez l'instance de connexion à la base de données.
     *
     * @param  \Two\Database\Connection
     * @return $this
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Définissez le rappel du résolveur Schema Blueprint.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public function blueprintResolver(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

}
