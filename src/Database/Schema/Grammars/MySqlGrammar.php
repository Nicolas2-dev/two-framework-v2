<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema\Grammars;

use Two\Support\Fluent;
use Two\Database\Connection;
use Two\Database\Schema\Blueprint;
use Two\Database\Schema\Grammar;


class MySqlGrammar extends Grammar
{
    /**
     * Les modificateurs de colonnes possibles.
     *
     * @var array
     */
    protected $modifiers = array('Unsigned', 'Nullable', 'Default', 'Increment', 'Comment', 'After');

    /**
     * Les séries de colonnes possibles
     *
     * @var array
     */
    protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

    /**
     * Compilez la requête pour déterminer la liste des tables.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return 'select * from information_schema.tables where table_schema = ? and table_name = ?';
    }

    /**
     * Compilez la requête pour déterminer la liste des colonnes.
     *
     * @return string
     */
    public function compileColumnExists()
    {
        return "select column_name from information_schema.columns where table_schema = ? and table_name = ?";
    }

    /**
     * Compilez une commande de création de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @param  \Two\Database\Connection  $connection
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        $sql = 'create table '.$this->wrapTable($blueprint)." ($columns)";

        // Une fois que nous avons le SQL principal, nous pouvons ajouter l'option d'encodage au SQL pour
        // la table. Ensuite, nous pouvons vérifier si un moteur de stockage a été fourni pour
        // la table. Si tel est le cas, nous ajouterons la déclaration du moteur à la requête SQL.
        $sql = $this->compileCreateEncoding($sql, $connection);

        if (isset($blueprint->engine)) {
            $sql .= ' engine = '.$blueprint->engine;
        }

        return $sql;
    }

    /**
     * Ajoutez les spécifications du jeu de caractères à une commande.
     *
     * @param  string  $sql
     * @param  \Two\Database\Connection  $connection
     * @return string
     */
    protected function compileCreateEncoding($sql, Connection $connection)
    {
        if (! is_null($charset = $connection->getConfig('charset'))) {
            $sql .= ' default character set '.$charset;
        }

        if (! is_null($collation = $connection->getConfig('collation'))) {
            $sql .= ' collate '.$collation;
        }

        return $sql;
    }

    /**
     * Compilez une commande d’ajout de colonne.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $columns = $this->prefixArray('add', $this->getColumns($blueprint));

        return 'alter table '.$table.' '.implode(', ', $columns);
    }

    /**
     * Compilez une commande clé primaire.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $command->name(null);

        return $this->compileKey($blueprint, $command, 'primary key');
    }

    /**
     * Compilez un raccourci clavier unique.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'unique');
    }

    /**
     * Compilez une commande de touche d'index simple.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'index');
    }

    /**
     * Compilez une commande de création d'index.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @param  string  $type
     * @return string
     */
    protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
    {
        $columns = $this->columnize($command->columns);

        $table = $this->wrapTable($blueprint);

        return "alter table {$table} add {$type} {$command->index}($columns)";
    }

    /**
     * Compilez une commande drop table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compilez une commande drop table (si elle existe).
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compilez une commande de suppression de colonne.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

        $table = $this->wrapTable($blueprint);

        return 'alter table '.$table.' '.implode(', ', $columns);
    }

    /**
     * Compilez une commande de clé primaire drop.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    /**
     * Compilez un raccourci clavier unique.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "alter table {$table} drop index {$command->index}";
    }

    /**
     * Compilez une commande drop index.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "alter table {$table} drop index {$command->index}";
    }

    /**
     * Compilez une commande de clé étrangère drop.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        return "alter table {$table} drop foreign key {$command->index}";
    }

    /**
     * Compilez une commande de renommage de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "rename table {$from} to ".$this->wrapTable($command->to);
    }

    /**
     * Créez la définition de colonne pour un type char.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    /**
     * Créez la définition de colonne pour un type de chaîne.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "varchar({$column->length})";
    }

    /**
     * Créez la définition de colonne pour un type de texte.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Créez la définition de colonne pour un type de texte moyen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'mediumtext';
    }

    /**
     * Créez la définition de colonne pour un type de texte long.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'longtext';
    }

    /**
     * Créez la définition de colonne pour un type grand entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    /**
     * Créez la définition de colonne pour un type entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Créez la définition de colonne pour un type entier moyen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'mediumint';
    }

    /**
     * Créez la définition de colonne pour un type entier minuscule.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Créez la définition de colonne pour un petit type entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Créez la définition de colonne pour un type float.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return "float({$column->total}, {$column->places})";
    }

    /**
     * Créez la définition de colonne pour un type double.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        if ($column->total && $column->places)
        {
            return "double({$column->total}, {$column->places})";
        }

        return 'double';
    }

    /**
     * Créez la définition de colonne pour un type décimal.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Créez la définition de colonne pour un type booléen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'tinyint(1)';
    }

    /**
     * Créez la définition de colonne pour un type enum.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return "enum('".implode("', '", $column->allowed)."')";
    }

    /**
     * Créez la définition de colonne pour un type de date.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Créez la définition de colonne pour un type date-heure.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return 'datetime';
    }

    /**
     * Créez la définition de colonne pour un type d'heure.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'time';
    }

    /**
     * Créez la définition de colonne pour un type d'horodatage.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        if (! $column->nullable) return 'timestamp default CURRENT_TIMESTAMP';

        return 'timestamp';
    }

    /**
     * Créez la définition de colonne pour un type binaire.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne non signé.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyUnsigned(Blueprint $blueprint, Fluent $column)
    {
        if ($column->unsigned) return ' unsigned';
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne nullable.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne par défaut.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->default))  {
            return " default ".$this->getDefaultValue($column->default);
        }
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne à incrémentation automatique.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' auto_increment primary key';
        }
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne "après".
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyAfter(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->after)) {
            return ' after '.$this->wrap($column->after);
        }
    }

    /**
     * Obtenez le SQL pour un modificateur de colonne "commentaire".
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string|null
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->comment)) {
            return ' comment "'.$column->comment.'"';
        }
    }

    /**
     * Enveloppez une seule chaîne dans des identifiants de mots clés.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') return $value;

        return '`'.str_replace('`', '``', $value).'`';
    }

}
