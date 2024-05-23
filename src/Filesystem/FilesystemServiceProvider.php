<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Filesystem;

use Two\Filesystem\Filesystem;

use Two\Application\Providers\ServiceProvider;


class FilesystemServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('files', function()
        {
            return new Filesystem();
        });
    }

}
