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


class SQLiteGrammar extends Grammar
{
    /**
     * Les modificateurs de colonnes possibles.
     *
     * @var array
     */
    protected $modifiers = array('Nullable', 'Default', 'Increment');

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
        return "select * from sqlite_master where type = 'table' and name = ?";
    }

    /**
     * Compilez la requête pour déterminer la liste des colonnes.
     *
     * @param  string  $table
     * @return string
     */
    public function compileColumnExists($table)
    {
        return 'pragma table_info('.str_replace('.', '__', $table).')';
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

        $sql = 'create table '.$this->wrapTable($blueprint)." ($columns";

        // SQLite force l'ajout des clés primaires lors de la création initiale de la table
        // nous devrons donc rechercher une commande de clé primaire et ajouter les colonnes
        // à la déclaration de la table ici afin qu'elles puissent être créées sur les tables.
        $sql .= (string) $this->addForeignKeys($blueprint);

        $sql .= (string) $this->addPrimaryKeys($blueprint);

        return $sql .= ')';
    }

    /**
     * Obtenez la syntaxe de clé étrangère pour une instruction de création de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addForeignKeys(Blueprint $blueprint)
    {
        $sql = '';

        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        // Une fois que nous avons toutes les commandes de clé étrangère pour l'instruction de création de table
        // nous allons parcourir chacun d'eux et les ajouter à la table de création SQL que nous
        // sont en cours de construction, puisque SQLite a besoin de clés étrangères pour la création des tables.
        foreach ($foreigns as $foreign) {
            $sql .= $this->getForeignKey($foreign);

            if (! is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }

            if (! is_null($foreign->onUpdate)) {
                $sql .= " on update {$foreign->onUpdate}";
            }
        }

        return $sql;
    }

    /**
     * Obtenez le SQL pour la clé étrangère.
     *
     * @param  \Two\Support\Fluent  $foreign
     * @return string
     */
    protected function getForeignKey($foreign)
    {
        $on = $this->wrapTable($foreign->on);

        // Nous devons créer une colonne dans les colonnes pour lesquelles la clé étrangère est définie.
        // pour qu'il s'agisse d'une liste correctement formatée. Une fois que nous avons fait cela, nous pouvons
        // renvoie la déclaration SQL de clé étrangère à la méthode appelante pour utilisation.
        $columns = $this->columnize($foreign->columns);

        $onColumns = $this->columnize((array) $foreign->references);

        return ", foreign key($columns) references $on($onColumns)";
    }

    /**
     * Obtenez la syntaxe de clé primaire pour une instruction de création de table.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        $primary = $this->getCommandByName($blueprint, 'primary');

        if (! is_null($primary)) {
            $columns = $this->columnize($primary->columns);

            return ", primary key ({$columns})";
        }
    }

    /**
     * Compilez les commandes alter table pour ajouter des colonnes
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return array
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $table = $this->wrapTable($blueprint);

        $columns = $this->prefixArray('add column', $this->getColumns($blueprint));

        $statements = array();

        foreach ($columns as $column) {
            $statements[] = 'alter table '.$table.' '.$column;
        }

        return $statements;
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
     * Compilez une commande de clé étrangère.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @return string
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        // Géré lors de la création de la table...
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
     * @param  \Two\Database\Connection  $connection
     * @return array
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $schema = $connection->getDoctrineSchemaManager();

        $tableDiff = $this->getDoctrineTableDiff($blueprint, $schema);

        foreach ($command->columns as $name) {
            $column = $connection->getDoctrineColumn($blueprint->getTable(), $name);

            $tableDiff->removedColumns[$name] = $column;
        }

        return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
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
        return "drop index {$command->index}";
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
        return 'varchar';
    }

    /**
     * Créez la définition de colonne pour un type de chaîne.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return 'varchar';
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
        return 'integer';
    }

    /**
     * Créez la définition de colonne pour un type grand entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'integer';
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
        return 'integer';
    }

    /**
     * Créez la définition de colonne pour un petit type entier.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'integer';
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
        return 'float';
    }

    /**
     * Créez la définition de colonne pour un type booléen.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Créez la définition de colonne pour un type enum.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return 'varchar';
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
        return 'blob';
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
            return ' primary key autoincrement';
        }
    }

}
