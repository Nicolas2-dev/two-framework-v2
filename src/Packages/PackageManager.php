<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages;

use Exception;
use LogicException;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Application\Two;
use Two\Packages\Repository;
use Two\Packages\Exception\ProviderMissingException;


class PackageManager
{
    /**
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * @var \Two\Packages\Repository
     */
    protected $repository;


    /**
     * Créez une nouvelle instance du gestionnaire de packages.
     *
     * @param \Two\Application\Two $app
     */
    public function __construct(Two $app, Repository $repository)
    {
        $this->app = $app;

        $this->repository = $repository;
    }

    /**
     * Enregistrez le fichier du fournisseur de services de package de tous les packages.
     *
     * @return mixed
     */
    public function register()
    {
        $packages = $this->repository->enabled();

        $packages->each(function ($properties)
        {
            try {
                $provider = $this->resolveServiceProvider($properties);

                $this->app->register($provider);
            }
            catch (Exception $e) {
                // Ne fais rien.
            }
        });
    }

    /**
     * Résolvez le nom de classe d’un fournisseur de services de package.
     *
     * @param array $properties
     *
     * @return string
     * @throws \LogicException|\Two\Packages\Exception\ProviderMissingException
     */
    protected function resolveServiceProvider(array $properties)
    {
        if (empty($name = Arr::get($properties, 'name'))) {
            throw new LogicException('Invalid Package properties');
        }

        $namespace = Arr::get($properties, 'namespace', str_replace('/', '\\', $name));

        // Le fournisseur de services par défaut d'un package doit être nommé comme :
        // AcmeCorp\Pages\Providers\PackageServiceProvider

        $type = Arr::get($properties, 'type', 'package');

        $provider = sprintf('%s\\Providers\\%sServiceProvider', $namespace, Str::studly($type));

        if (class_exists($provider)) {
            return $provider;
        }

        // Le fournisseur de services alternatif d'un package doit être nommé comme :
        // AcmeCorp\Pages\PageServiceProvider

        $basename = Arr::get($properties, 'basename', basename($name));

        $provider = sprintf('%s\%sServiceProvider', $namespace, Str::singular($basename));

        if (class_exists($provider)) {
            return $provider;
        }

        throw new ProviderMissingException('Package Service Provider not found');
    }

    /**
     * Résolvez le chemin correct des fichiers du package.
     *
     * @param array $properties
     *
     * @return string
     */
    public function resolveClassPath($properties)
    {
        $path = $properties['path'];

        if ($properties['type'] == 'package') {
            $path .= 'src' .DS;
        }

        return $path;
    }

    /**
     * Transmettez dynamiquement les méthodes au référentiel.
     *
     * @param string $method
     * @param mixed  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->repository, $method), $arguments);
    }
}
