<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages\Providers;

use Two\Packages\Providers\PackageServiceProvider as ServiceProvider;


class ThemeServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez les actifs du package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @param  string  $path
     * @return void
     */
    protected function registerPackageAssets($package, $namespace, $path)
    {
        $assets = $path .DS .'Assets';

        if ($this->app['files']->isDirectory($assets)) {
            $namespace = 'themes/' .str_replace('_', '-', $namespace);

            $this->app['assets.dispatcher']->package($package, $assets, $namespace);
        }
    }
}
