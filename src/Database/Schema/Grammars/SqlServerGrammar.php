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


class SqlServerGrammar extends Grammar
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
        return "select * from sysobjects where type = 'U' and name = ?";
    }

    /**
     * Compilez la requête pour déterminer la liste des colonnes.
     *
     * @param  string  $table
     * @return string
     */
    public function compileColumnExists($table)
    {
        return "select col.name from sys.columns as col
                join sys.objects as obj on col.object_id = obj.object_id
                where obj.type = 'U' and obj.name = '$table'";
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

        $columns = $this->getColumns($blueprint);

        return 'alter table '.$table.' add '.implode(', ', $columns);
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

        $table = $this->wrapTable($blueprint);

        return "alter table {$table} add constraint {$command->index} primary key ({$columns})";
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
        $columns = $this->columnize($command->columns);

        $table = $this->wrapTable($blueprint);

        return "create unique index {$command->index} on {$table} ({$columns})";
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

        $table = $this->wrapTable($blueprint);

        return "create index {$command->index} on {$table} ({$columns})";
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
     * Compilez une commande de suppression de colonne.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->wrapArray($command->columns);

        $table = $this->wrapTable($blueprint);

        return 'alter table '.$table.' drop column '.implode(', ', $columns);
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
        $table = $this->wrapTable($blueprint);

        return "alter table {$table} drop constraint {$command->index}";
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

        return "drop index {$command->index} on {$table}";
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

        return "drop index {$command->index} on {$table}";
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

        return "sp_rename {$from}, ".$this->wrapTable($command->to);
    }

    /**
     * Créez la définition de colonne pour un type char.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "nchar({$column->length})";
    }

    /**
     * Créez la définition de colonne pour un type de chaîne.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "nvarchar({$column->length})";
    }

    /**
     * Créez la définition de colonne pour un type de texte.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'nvarchar(max)';
    }

    /**
     * Créez la définition de colonne pour un type de texte moyen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'nvarchar(max)';
    }

    /**
     * Créez la définition de colonne pour un type de texte long.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'nvarchar(max)';
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
     * Créez la définition de colonne pour un type entier moyen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'int';
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
        return 'float';
    }

    /**
     * Créez la définition de colonne pour un type double.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return 'float';
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
        return 'bit';
    }

    /**
     * Créez la définition de colonne pour un type enum.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return 'nvarchar(255)';
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
        return 'datetime';
    }

    /**
     * Créez la définition de colonne pour un type binaire.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'varbinary(max)';
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
            return ' identity primary key';
        }
    }

}
