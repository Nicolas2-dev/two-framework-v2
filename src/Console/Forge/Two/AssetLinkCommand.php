<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;


class AssetLinkCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'asset:link';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a symbolic link from "webroot/assets" to the "assets" folder';

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (file_exists($publicPath = public_path('assets'))) {
            return $this->error('The "webroot/assets" directory already exists.');
        }

        $assetsPath = $this->container['config']->get('routing.assets.path', base_path('assets'));

        $this->container->make('files')->link(
            $assetsPath, $publicPath
        );

        $this->info('The [webroot/assets] directory has been linked.');
    }
}
