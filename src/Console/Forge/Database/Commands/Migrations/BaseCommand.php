<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Migrations;

use Two\Console\Commands\Command;


class BaseCommand extends Command
{

    /**
     * Obtenez le chemin d'accès au répertoire de migration.
     *
     * @return string
     */
    protected function getMigrationPath()
    {
        $path = $this->input->getOption('path');

        // Tout d’abord, nous vérifierons si une option de chemin a été définie. S'il a
        // nous utiliserons le chemin relatif à la racine de ce dossier d'installation
        // afin que les migrations puissent être exécutées pour n'importe quel chemin au sein des applications.
        if (! is_null($path)) {
            return $this->container['path.base'] .DS .$path;
        }

        return $this->container['path'] .DS .'Database' .DS .'Migrations';
    }
}
