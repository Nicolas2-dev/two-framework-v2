<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Contracts;


interface MigrationRepositoryInterface
{
    /**
     * Obtenez les migrations exécutées pour un package donné.
     *
     * @return array
     */
    public function getRan();

    /**
     * Obtenez le dernier lot de migration.
     *
     * @param  string  $group
     *
     * @return array
     */
    public function getLast($group);

    /**
     * Enregistrez qu'une migration a été exécutée.
     *
     * @param  string  $file
     * @param  int     $batch
     * @param  string  $group
     * @return void
     */
    public function log($file, $batch, $group);

    /**
     * Supprimez une migration du journal.
     *
     * @param  object  $migration
     * @return void
     */
    public function delete($migration);

    /**
     * Obtenez le prochain numéro de lot de migration.
     *
     * @param  string  $group
     *
     * @return int
     */
    public function getNextBatchNumber($group);

    /**
     * Créez le magasin de données du référentiel de migration.
     *
     * @return void
     */
    public function createRepository();

    /**
     * Déterminez si le référentiel de migration existe.
     *
     * @return bool
     */
    public function repositoryExists();

    /**
     * Définissez la source d’informations pour collecter des données.
     *
     * @param  string  $name
     * @return void
     */
    public function setSource($name);

}
