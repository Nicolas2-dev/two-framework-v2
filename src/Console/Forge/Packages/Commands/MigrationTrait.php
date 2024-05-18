<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use InvalidArgumentException;


trait MigrationTrait
{
    /**
     * Exiger (une fois) tous les fichiers de migration pour le package fourni.
     *
     * @param string $package
     */
    protected function requireMigrations($package)
    {
        $files = $this->container['files'];

        //
        $path = $this->getMigrationPath($package);

        $migrations = $files->glob($path.'*_*.php');

        foreach ($migrations as $migration) {
            $files->requireOnce($migration);
        }
    }

    /**
     * Obtenez le chemin du répertoire de migration.
     *
     * @param string $slug
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getMigrationPath($slug)
    {
        $packages = $this->container['packages'];

        //
        $package = $packages->where('slug', $slug);

        $path = $packages->resolveClassPath($package);

        return $path .'Database' .DS .'Migrations' .DS;
    }
}
