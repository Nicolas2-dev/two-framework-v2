<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema\Grammars;

use Two\Support\Fluent;
use Two\Database\Schema\Blueprint;
use Two\Database\Schema\Grammar;


class PostgresGrammar extends Grammar
{
    /**
     * Les modificateurs de colonnes possibles.
     *
     * @var array
     */
    protected $modifiers = array('Increment', 'Nullable', 'Default');

    /**
     * Les colonnes disponibles en série.
     *
     * @var array
     */
    protected $serials = array('bigInteger', 'integer');

    /**
     * Compilez la requête pour déterminer si une table existe.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return 'select * from information_schema.tables where table_name = ?';
    }

    /**
     * Compilez la requête pour déterminer la liste des colonnes.
     *
     * @param  string  $table
     * @return string
     */
    public function compileColumnExists($table)
    {
        return "select column_name from information_schema.columns where table_name = '$table'";
    }

    /**
     * Compilez une commande de création de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $columns = implode(', ', $this->getColumns($blueprint));

        return 'create table '.$this->wrapTable($blueprint)." ($columns)";
    }

    /**
     * Compilez une commande de création de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $columns = $this->prefixArray('add column', $this->getColumns($blueprint));

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
        $columns = $this->columnize($command->columns);

        return 'alter table '.$this->wrapTable($blueprint)." add primary key ({$columns})";
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
        $table = $this->wrapTable($blueprint);

        $columns = $this->columnize($command->columns);

        return "alter table $table add constraint {$command->index} unique ($columns)";
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
        $columns = $this->columnize($command->columns);

        return "create index {$command->index} on ".$this->wrapTable($blueprint)." ({$columns})";
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
        $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

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
        $table = $blueprint->getTable();

        return 'alter table '.$this->wrapTable($blueprint)." drop constraint {$table}_pkey";
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

        return "alter table {$table} drop constraint {$command->index}";
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
        return "drop index {$command->index}";
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

        return "alter table {$table} drop constraint {$command->index}";
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

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
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
        return 'text';
    }

    /**
     * Créez la définition de colonne pour un type de texte long.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Créez la définition de colonne pour un type entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return $column->autoIncrement ? 'serial' : 'integer';
    }

    /**
     * Créez la définition de colonne pour un type grand entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return $column->autoIncrement ? 'bigserial' : 'bigint';
    }

    /**
     * Créez la définition de colonne pour un type entier moyen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Créez la définition de colonne pour un type entier minuscule.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'smallint';
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
        return 'real';
    }

    /**
     * Créez la définition de colonne pour un type double.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return 'double precision';
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
        return 'boolean';
    }

    /**
     * Créez la définition de colonne pour un type enum.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        $allowed = array_map(function($a) { return "'".$a."'"; }, $column->allowed);

        return "varchar(255) check (\"{$column->name}\" in (".implode(', ', $allowed)."))";
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
        return 'timestamp';
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
        return 'timestamp';
    }

    /**
     *Créez la définition de colonne pour un type binaire. 
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'bytea';
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
        if (! is_null($column->default)) {
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
            return ' primary key';
        }
    }

}
