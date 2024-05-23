<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema;

use Closure;

use Two\Support\Fluent;
use Two\Database\Connection;
use Two\Database\Schema\Grammar;


class Blueprint
{
    /**
     * Le tableau décrit par le plan.
     *
     * @var string
     */
    protected $table;

    /**
     * Les colonnes qui doivent être ajoutées au tableau.
     *
     * @var array
     */
    protected $columns = array();

    /**
     * Les commandes qui doivent être exécutées pour la table.
     *
     * @var array
     */
    protected $commands = array();

    /**
     * Le moteur de stockage qui doit être utilisé pour la table.
     *
     * @var string
     */
    public $engine;

    /**
     * Créez un nouveau plan de schéma.
     *
     * @param  string   $table
     * @param  \Closure  $callback
     * @return void
     */
    public function __construct($table, Closure $callback = null)
    {
        $this->table = $table;

        if (! is_null($callback)) $callback($this);
    }

    /**
     * Exécutez le plan sur la base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @param  \Two\Database\Schema\Grammar $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement)
        {
            $connection->statement($statement);
        }
    }

    /**
     * Obtenez les instructions SQL brutes pour le plan.
     *
     * @param  \Two\Database\Connection  $connection
     * @param  \Two\Database\Schema\Grammar  $grammar
     * @return array
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands();

        $statements = array();

        // Chaque type de commande a une fonction de compilateur correspondante sur le schéma
        // grammaire utilisée pour construire les instructions SQL nécessaires à la construction
        // l'élément blueprint, nous appellerons donc simplement cette fonction du compilateur.
        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->$method($this, $command, $connection))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Ajoutez les commandes impliquées par le plan.
     *
     * @return void
     */
    protected function addImpliedCommands()
    {
        if (count($this->columns) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        $this->addFluentIndexes();
    }

    /**
     * Ajoutez les commandes d'index couramment spécifiées sur les colonnes.
     *
     * @return void
     */
    protected function addFluentIndexes()
    {
        foreach ($this->columns as $column)
        {
            foreach (array('primary', 'unique', 'index') as $index) {
                // Si l'index a été spécifié sur la colonne donnée, mais est simplement
                // égal à "true" (booléen), aucun nom n'a été spécifié pour cela
                // index, nous appellerons donc simplement les méthodes d'index sans une.
                if ($column->$index === true) {
                    $this->$index($column->name);

                    continue 2;
                }

                // Si l'index a été spécifié sur la colonne et que c'est quelque chose
                // autre que boolean true, nous supposerons qu'un nom a été fourni sur
                // la spécification de l'index et transmet le nom à la méthode.
                else if (isset($column->$index)) {
                    $this->$index($column->name, $column->$index);

                    continue 2;
                }
            }
        }
    }

    /**
     * Déterminez si le plan a une commande de création.
     *
     * @return bool
     */
    protected function creating()
    {
        foreach ($this->commands as $command) {
            if ($command->name == 'create') return true;
        }

        return false;
    }

    /**
     * Indiquez que la table doit être créée.
     *
     * @return \Two\Support\Fluent
     */
    public function create()
    {
        return $this->addCommand('create');
    }

    /**
     * Indiquez que la table doit être supprimée.
     *
     * @return \Two\Support\Fluent
     */
    public function drop()
    {
        return $this->addCommand('drop');
    }

    /**
     * Indiquez que la table doit être supprimée si elle existe.
     *
     * @return \Two\Support\Fluent
     */
    public function dropIfExists()
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indiquez que les colonnes données doivent être supprimées.
     *
     * @param  string|array  $columns
     * @return \Two\Support\Fluent
     */
    public function dropColumn($columns)
    {
        $columns = is_array($columns) ? $columns : (array) func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Indiquez que les colonnes données doivent être renommées.
     *
     * @param  string  $from
     * @param  string  $to
     * @return \Two\Support\Fluent
     */
    public function renameColumn($from, $to)
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Indiquez que la clé primaire donnée doit être supprimée.
     *
     * @param  string|array  $index
     * @return \Two\Support\Fluent
     */
    public function dropPrimary($index = null)
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Indiquez que la clé unique donnée doit être supprimée.
     *
     * @param  string|array  $index
     * @return \Two\Support\Fluent
     */
    public function dropUnique($index)
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Indiquez que l'index donné doit être supprimé.
     *
     * @param  string|array  $index
     * @return \Two\Support\Fluent
     */
    public function dropIndex($index)
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Indique que la clé étrangère donnée doit être supprimée.
     *
     * @param  string  $index
     * @return \Two\Support\Fluent
     */
    public function dropForeign($index)
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Indiquez que les colonnes d'horodatage doivent être supprimées.
     *
     * @return void
     */
    public function dropTimestamps()
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
    * Indiquez que la colonne de suppression logicielle doit être supprimée.
    *
    * @return void
    */
    public function dropSoftDeletes()
    {
        $this->dropColumn('deleted_at');
    }

    /**
     * Renommez la table en un nom donné.
     *
     * @param  string  $to
     * @return \Two\Support\Fluent
     */
    public function rename($to)
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Spécifiez la ou les clés primaires de la table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Two\Support\Fluent
     */
    public function primary($columns, $name = null)
    {
        return $this->indexCommand('primary', $columns, $name);
    }

    /**
     * Spécifiez un index unique pour la table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Two\Support\Fluent
     */
    public function unique($columns, $name = null)
    {
        return $this->indexCommand('unique', $columns, $name);
    }

    /**
     * Spécifiez un index pour la table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Two\Support\Fluent
     */
    public function index($columns, $name = null)
    {
        return $this->indexCommand('index', $columns, $name);
    }

    /**
     * Spécifiez une clé étrangère pour la table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Two\Support\Fluent
     */
    public function foreign($columns, $name = null)
    {
        return $this->indexCommand('foreign', $columns, $name);
    }

    /**
     * Créez une nouvelle colonne entière à incrémentation automatique sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function increments($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Créez une nouvelle colonne de grands entiers à incrémentation automatique sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function bigIncrements($column)
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Créez une nouvelle colonne de caractères sur la table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Two\Database\Schema\ColumnDefinition
     */
    public function char($column, $length = null)
    {
        $length = ! is_null($length) ? $length : Builder::$defaultStringLength;

        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Créez une nouvelle colonne de chaîne sur la table.
     *
     * @param  string  $column
     * @param  int|null  $length
     * @return \Two\Database\Schema\ColumnDefinition
     */
    public function string($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Créez une nouvelle colonne de texte sur le tableau.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Créez une nouvelle colonne de texte moyenne sur le tableau.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Créez une nouvelle colonne de texte long sur le tableau.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Créez une nouvelle colonne entière sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Two\Support\Fluent
     */
    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Créez une nouvelle grande colonne entière sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Two\Support\Fluent
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Créez une nouvelle colonne entière moyenne sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Two\Support\Fluent
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Créez une nouvelle petite colonne entière sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Two\Support\Fluent
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Créez une nouvelle petite colonne entière sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Two\Support\Fluent
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Créez une nouvelle colonne entière non signée sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Two\Support\Fluent
     */
    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Créez une nouvelle colonne de grands entiers non signés sur la table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Two\Support\Fluent
     */
    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Créez une nouvelle colonne flottante sur la table.
     *
     * @param  string  $column
     * @param  int     $total
     * @param  int     $places
     * @return \Two\Support\Fluent
     */
    public function float($column, $total = 8, $places = 2)
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Créez une nouvelle double colonne sur le tableau.
     *
     * @param  string   $column
     * @param  int|null    $total
     * @param  int|null $places
     * @return \Two\Support\Fluent
     */
    public function double($column, $total = null, $places = null)
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Créez une nouvelle colonne décimale sur le tableau.
     *
     * @param  string  $column
     * @param  int     $total
     * @param  int     $places
     * @return \Two\Support\Fluent
     */
    public function decimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Créez une nouvelle colonne booléenne sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Créez une nouvelle colonne d'énumération sur la table.
     *
     * @param  string  $column
     * @param  array   $allowed
     * @return \Two\Support\Fluent
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Créez une nouvelle colonne de date sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Créez une nouvelle colonne date-heure sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function dateTime($column)
    {
        return $this->addColumn('dateTime', $column);
    }

    /**
     * Créez une nouvelle colonne de temps sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function time($column)
    {
        return $this->addColumn('time', $column);
    }

    /**
     * Créez une nouvelle colonne d'horodatage sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function timestamp($column)
    {
        return $this->addColumn('timestamp', $column);
    }

    /**
     * Ajoutez des horodatages de création et de mise à jour nullables à la table.
     *
     * @return void
     */
    public function nullableTimestamps()
    {
        $this->timestamp('created_at')->nullable();

        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Ajoutez des horodatages de création et de mise à jour au tableau.
     *
     * @return void
     */
    public function timestamps()
    {
        $this->timestamp('created_at');

        $this->timestamp('updated_at');
    }

    /**
     * Ajoutez un horodatage « supprimé à » pour la table.
     *
     * @return \Two\Support\Fluent
     */
    public function softDeletes()
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    /**
     * Créez une nouvelle colonne binaire sur la table.
     *
     * @param  string  $column
     * @return \Two\Support\Fluent
     */
    public function binary($column)
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Ajoutez les colonnes appropriées pour une table polymorphe.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function morphs($name, $indexName = null)
    {
        $this->unsignedInteger("{$name}_id");

        $this->string("{$name}_type");

        $this->index(array("{$name}_id", "{$name}_type"), $indexName);
    }

    /**
     * Ajoute la colonne `remember_token` au tableau.
     *
     * @return \Two\Support\Fluent
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Créez une nouvelle commande drop index sur le plan.
     *
     * @param  string  $command
     * @param  string  $type
     * @param  string|array  $index
     * @return \Two\Support\Fluent
     */
    protected function dropIndexCommand($command, $type, $index)
    {
        $columns = array();

        // Si "l'index" donné est en réalité un tableau de colonnes, le développeur veut dire
        // pour supprimer un index simplement en spécifiant les colonnes impliquées sans le
        // nom conventionnel, nous allons donc construire le nom de l'index à partir des colonnes.
        if (is_array($index)) {
            $columns = $index;

            $index = $this->createIndexName($type, $columns);
        }

        return $this->indexCommand($command, $columns, $index);
    }

    /**
     * Ajoutez une nouvelle commande d'index au plan.
     *
     * @param  string        $type
     * @param  string|array  $columns
     * @param  string        $index
     * @return \Two\Support\Fluent
     */
    protected function indexCommand($type, $columns, $index)
    {
        $columns = (array) $columns;

        // Si aucun nom n'a été spécifié pour cet index, nous en créerons un en utilisant un fichier de base
        // convention du nom de la table, suivi des colonnes, suivi d'un
        // Type d'index, tel que primaire ou index, qui rend l'index unique.
        if (is_null($index)) {
            $index = $this->createIndexName($type, $columns);
        }

        return $this->addCommand($type, compact('index', 'columns'));
    }

    /**
     * Créez un nom d'index par défaut pour la table.
     *
     * @param  string  $type
     * @param  array   $columns
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(array('-', '.'), '_', $index);
    }

    /**
     * Ajoutez une nouvelle colonne au plan.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  array   $parameters
     * @return \Two\Support\Fluent
     */
    protected function addColumn($type, $name, array $parameters = array())
    {
        $attributes = array_merge(compact('type', 'name'), $parameters);

        $this->columns[] = $column = new Fluent($attributes);

        return $column;
    }

    /**
     * Supprimez une colonne du plan de schéma.
     *
     * @param  string  $name
     * @return $this
     */
    public function removeColumn($name)
    {
        $this->columns = array_values(array_filter($this->columns, function($c) use ($name)
        {
            return $c['attributes']['name'] != $name;
        }));

        return $this;
    }

    /**
     * Ajoutez une nouvelle commande au plan.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return \Two\Support\Fluent
     */
    protected function addCommand($name, array $parameters = array())
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Créez une nouvelle commande Fluent.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @return \Two\Support\Fluent
     */
    protected function createCommand($name, array $parameters = array())
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Obtenez le tableau décrit par le plan.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Obtenez les colonnes qui doivent être ajoutées.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Obtenez les commandes sur le plan.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }

}
