<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Schema;

use Two\Support\Fluent;
use Two\Database\Connection;
use Two\Database\Query\Expression;
use Two\Database\Schema\Blueprint;
use Two\Database\Grammar as BaseGrammar;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;


abstract class Grammar extends BaseGrammar
{
    /**
     * Compilez une commande de renommage de colonne.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @param  \Two\Database\Connection  $connection
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $schema = $connection->getDoctrineSchemaManager();

        $table = $this->getTablePrefix().$blueprint->getTable();

        $column = $connection->getDoctrineColumn($table, $command->from);

        $tableDiff = $this->getRenamedDiff($blueprint, $command, $column, $schema);

        return (array) $schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
    }

    /**
     * Obtenez une nouvelle instance de colonne avec le nouveau nom de colonne.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $command
     * @param  \Doctrine\DBAL\Schema\Column  $column
     * @param  \Doctrine\DBAL\Schema\AbstractSchemaManager  $schema
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected function getRenamedDiff(Blueprint $blueprint, Fluent $command, Column $column, SchemaManager $schema)
    {
        $tableDiff = $this->getDoctrineTableDiff($blueprint, $schema);

        return $this->setRenamedColumns($tableDiff, $command, $column);
    }

    /**
     * Définissez les colonnes renommées sur la table diff.
     *
     * @param  \Doctrine\DBAL\Schema\TableDiff  $tableDiff
     * @param  \Two\Support\Fluent  $command
     * @param  \Doctrine\DBAL\Schema\Column  $column
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected function setRenamedColumns(TableDiff $tableDiff, Fluent $command, Column $column)
    {
        $newColumn = new Column($command->to, $column->getType(), $column->toArray());

        $tableDiff->renamedColumns = array($command->from => $newColumn);

        return $tableDiff;
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
        $table = $this->wrapTable($blueprint);

        $on = $this->wrapTable($command->on);

        // Nous devons préparer plusieurs éléments de la définition de la clé étrangère
        // avant de pouvoir créer le SQL, comme encapsuler les tables et convertir
        // un tableau de colonnes en chaînes délimitées par des virgules pour les requêtes SQL.
        $columns = $this->columnize($command->columns);

        $onColumns = $this->columnize((array) $command->references);

        $sql = "alter table {$table} add constraint {$command->index} ";

        $sql .= "foreign key ({$columns}) references {$on} ({$onColumns})";

        // Une fois que nous avons construit l'instruction de base de création de clé étrangère, nous pouvons
        // construit la syntaxe de ce qui devrait se passer lors d'une mise à jour ou d'une suppression de
        // les colonnes affectées, qui obtiendront quelque chose comme "cascade", etc.
        if (! is_null($command->onDelete)) {
            $sql .= " on delete {$command->onDelete}";
        }

        if (! is_null($command->onUpdate)) {
            $sql .= " on update {$command->onUpdate}";
        }

        return $sql;
    }

    /**
     * Compilez les définitions de colonnes du plan.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @return array
     */
    protected function getColumns(Blueprint $blueprint)
    {
        $columns = array();

        foreach ($blueprint->getColumns() as $column) {
            // Chacun des types de colonnes possède ses propres fonctions de compilateur qui sont chargées
            // en transformant la définition de colonne dans son format SQL pour cette plateforme
            // utilisé par la connexion. Les modificateurs de la colonne sont compilés et ajoutés.
            $sql = $this->wrap($column).' '.$this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * Ajoutez les modificateurs de colonne à la définition.
     *
     * @param  string  $sql
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function addModifiers($sql, Blueprint $blueprint, Fluent $column)
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($blueprint, $column);
            }
        }

        return $sql;
    }

    /**
     * Obtenez la commande clé primaire si elle existe sur le plan.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  string  $name
     * @return \Two\Support\Fluent|null
     */
    protected function getCommandByName(Blueprint $blueprint, $name)
    {
        $commands = $this->getCommandsByName($blueprint, $name);

        if (count($commands) > 0) {
            return reset($commands);
        }
    }

    /**
     * Obtenez toutes les commandes avec un nom donné.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  string  $name
     * @return array
     */
    protected function getCommandsByName(Blueprint $blueprint, $name)
    {
        return array_filter($blueprint->getCommands(), function($value) use ($name)
        {
            return $value->name == $name;
        });
    }

    /**
     * Obtenez le SQL pour le type de données de la colonne.
     *
     * @param  \Two\Support\Fluent  $column
     * @return string
     */
    protected function getType(Fluent $column)
    {
        return $this->{"type".ucfirst($column->type)}($column);
    }

    /**
     * Ajoutez un préfixe à un tableau de valeurs.
     *
     * @param  string  $prefix
     * @param  array   $values
     * @return array
     */
    public function prefixArray($prefix, array $values)
    {
        return array_map(function($value) use ($prefix)
        {
            return $prefix.' '.$value;

        }, $values);
    }

    /**
     * Enveloppez un tableau dans des identifiants de mots-clés.
     *
     * @param  mixed   $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ($table instanceof Blueprint) $table = $table->getTable();

        return parent::wrapTable($table);
    }

    /**
     * Enveloppez une valeur dans des identifiants de mots-clés.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($value instanceof Fluent) $value = $value->name;

        return parent::wrap($value);
    }

    /**
     * Formatez une valeur afin qu'elle puisse être utilisée dans les clauses "par défaut".
     *
     * @param  mixed   $value
     * @return string
     */
    protected function getDefaultValue($value)
    {
        if ($value instanceof Expression) return $value;

        if (is_bool($value)) return "'".(int) $value."'";

        return "'".strval($value)."'";
    }

    /**
     * Créez un Doctrine DBAL TableDiff vide à partir du Blueprint.
     *
     * @param  \Two\Database\Schema\Blueprint  $blueprint
     * @param  \Doctrine\DBAL\Schema\AbstractSchemaManager  $schema
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected function getDoctrineTableDiff(Blueprint $blueprint, SchemaManager $schema)
    {
        $table = $this->getTablePrefix().$blueprint->getTable();

        $tableDiff = new TableDiff($table);

        $tableDiff->fromTable = $schema->listTableDetails($table);

        return $tableDiff;
    }

}
