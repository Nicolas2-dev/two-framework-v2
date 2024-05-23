<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Packages;

use InvalidArgumentException;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Collection\Collection;
use Two\Filesystem\Filesystem;
use Two\Config\Repository as Config;
use Two\Filesystem\Exception\FileNotFoundException;


class Repository
{
    /**
     * @var \Two\Config\Repository
     */
    protected $config;

    /**
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var \Two\Collection\Collection|null
     */
    protected static $packages;


    /**
     * Créez une nouvelle instance du gestionnaire de packages.
     *
     * @param \Two\Application\Two $app
     */
    public function __construct(Config $config, Filesystem $files)
    {
        $this->config = $config;

        $this->files = $files;
    }

    /**
     * Obtenez tous les slugs du module.
     *
     * @return Collection
     */
    public function slugs()
    {
        $slugs = collect();

        $this->all()->each(function ($item) use ($slugs)
        {
            $slugs->push($item['slug']);
        });

        return $slugs;
    }

    public function all()
    {
        return $this->getPackagesCached()->sortBy('order');
    }

    /**
     * Obtenez des packages en fonction de la clause Where.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return Collection
     */
    public function where($key, $value)
    {
        return collect($this->all()->where($key, $value)->first());
    }

    /**
     * Trier les modules par clé donnée dans l'ordre croissant.
     *
     * @param string $key
     *
     * @return Collection
     */
    public function sortBy($key)
    {
        return $this->getPackagesCached()->sortBy($key);
    }

    /**
     * Trier les modules par clé donnée dans l'ordre croissant.
     *
     * @param string $key
     *
     * @return Collection
     */
    public function sortByDesc($key)
    {
        return $this->getPackagesCached()->sortByDesc($key);
    }

    /**
     * Détermine si le module donné existe.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function exists($slug)
    {
        $slug = (Str::length($slug) <= 3) ? Str::lower($slug) : Str::snake($slug);

        $slugs = $this->slugs()->toArray();

        return in_array($slug, $slugs);
    }

    /**
     * Renvoie le nombre de tous les modules.
     *
     * @return int
     */
    public function count()
    {
        return $this->all()->count();
    }

    /**
     * Obtenez tous les modules activés.
     *
     * @return Collection
     */
    public function enabled()
    {
        return $this->all()->where('enabled', true);
    }

    /**
     * Obtenez tous les modules désactivés.
     *
     * @return Collection
     */
    public function disabled()
    {
        return $this->all()->where('enabled', false);
    }

    /**
     * Vérifiez si le module spécifié est activé.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function isEnabled($slug)
    {
        $package = $this->where('slug', $slug);

        return ($package['enabled'] === true);
    }

    /**
     * Vérifiez si le module spécifié est désactivé.
     *
     * @param string $slug
     *
     * @return bool
     */
    public function isDisabled($slug)
    {
        $package = $this->where('slug', $slug);

        return ($package['enabled'] === false);
    }

    /**
     * Obtenez le chemin local du package spécifié.
     *
     * @param string $slug
     *
     * @return string
     */
    public function getPackagePath($slug)
    {
        $package = (Str::length($slug) <= 3) ? Str::upper($slug) : Str::studly($slug);

        return $this->getPackagesPath() .DS .$package .DS;
    }

    /**
     * Obtenez le chemin (local) des packages.
     *
     * @return string
     */
    public function getPackagesPath()
    {
        return base_path('packages');
    }

    /**
     * Obtenez l’espace de noms des packages.
     *
     * @return string
     */
    public function getPackagesNamespace()
    {
        return '';
    }

    /**
     * Obtenez le chemin du module spécifié.
     *
     * @param string $slug
     *
     * @return string
     */
    public function getModulePath($slug)
    {
        $module = (Str::length($slug) <= 3) ? Str::upper($slug) : Str::studly($slug);

        return $this->getModulesPath() .DS .$module .DS;
    }

    /**
     * Obtenez le chemin des modules.
     *
     * @return string
     */
    public function getModulesPath()
    {
        return $this->config->get('packages.modules.path', BASEPATH .'modules');
    }

    /**
     * Obtenez l'espace de noms des modules.
     *
     * @return string
     */
    public function getModulesNamespace()
    {
        $namespace = $this->config->get('packages.modules.namespace', 'Modules\\');

        return rtrim($namespace, '/\\');
    }

    /**
     * Obtenez le chemin du thème spécifié.
     *
     * @param string $slug
     *
     * @return string
     */
    public function getThemePath($slug)
    {
        $theme = (Str::length($slug) <= 3) ? Str::upper($slug) : Str::studly($slug);

        return $this->getThemesPath() .DS .$theme .DS;
    }

    /**
     * Obtenez le chemin des modules.
     *
     * @return string
     */
    public function getThemesPath()
    {
        return $this->config->get('packages.themes.path', BASEPATH .'themes');
    }

    /**
     * Obtenez l'espace de noms des modules.
     *
     * @return string
     */
    public function getThemesNamespace()
    {
        $namespace = $this->config->get('packages.themes.namespace', 'Themes\\');

        return rtrim($namespace, '/\\');
    }

    /**
     * Mettre à jour le référentiel mis en cache des informations sur les packages.
     *
     * @return bool
     */
    public function optimize()
    {
        $path = $this->getCachePath();

        $this->writeCache($path, $this->getPackages());
    }

    protected function getPackages()
    {
        $packagesPath = base_path('vendor/Two-packages.php');

        try {
            $data = $this->files->getRequire($packagesPath);

        } catch (FileNotFoundException $e) {
            $data = array();
        }

        $items = Arr::get($data, 'packages', array());

        // Traitez les données des packages.
        $path = $this->getPackagesPath();

        $packages = collect();

        foreach ($items as $name => $packagePath) {
            $location = Str::startsWith($packagePath, $path) ? 'local' : 'vendor';

            $packages->put($name, array(
                'path' => Str::finish($packagePath, DS),

                //
                'location' => $location,
                'type'     => 'package',
            ));
        }

        // Processus pour les modules locaux.

        $path = $this->getModulesPath();

        if ($this->files->isDirectory($path)) {
            try {
                $paths = collect(
                    $this->files->directories($path)
                );
            }
            catch (InvalidArgumentException $e) {
                $paths = collect();
            }

            $namespace = $this->getModulesNamespace();

            $vendor = class_basename($namespace);

            $paths->each(function ($path) use ($packages, $vendor)
            {
                $name = $vendor .'/' .basename($path);

                $packages->put($name, array(
                    'path' => Str::finish($path, DS),

                    //
                    'location' => 'local',
                    'type'     => 'module',
                ));
            });
        }

        // Processus pour les thèmes locaux.

        $path = $this->getThemesPath();

        if ($this->files->isDirectory($path)) {
            try {
                $paths = collect(
                    $this->files->directories($path)
                );
            }
            catch (InvalidArgumentException $e) {
                $paths = collect();
            }

            $namespace = $this->getThemesNamespace();

            $vendor = class_basename($namespace);

            $paths->each(function ($path) use ($packages, $vendor)
            {
                $name = $vendor .'/' .basename($path);

                $packages->put($name, array(
                    'path' => Str::finish($path, DS),

                    //
                    'location' => 'local',
                    'type'     => 'theme',
                ));
            });
        }

        // Traitez les informations récupérées pour générer leurs enregistrements.

        $items = $packages->map(function ($properties, $name)
        {
            $basename = $this->getPackageName($name);

            $slug = (Str::length($basename) <= 3) ? Str::lower($basename) : Str::snake($basename);

            //
            $properties['name'] = $name;
            $properties['slug'] = $slug;

            $properties['namespace'] = str_replace('/', '\\', $name);

            $properties['basename'] = $basename;

            // Obtenez les options du package à partir de la configuration.
            $options = $this->config->get('packages.options.' .$slug, array());

            $properties['enabled'] = Arr::get($options, 'enabled', true);

            $properties['order'] = Arr::get($options, 'order', 9001);

            return $properties;
        });

        return $items->sortBy('basename');
    }

    /**
     * Obtenez le contenu du fichier cache.
     *
     * Le fichier cache répertorie tous les slugs de packages et leur statut activé ou désactivé.
     * Cela peut être utilisé pour filtrer les packages en fonction de leur statut.
     *
     * @return Collection
     */
    protected function getPackagesCached()
    {
        if (isset(static::$packages)) {
            return static::$packages;
        }

        $configPath = app_path('Config/Packages.php');

        $packagesPath = base_path('vendor/Two-packages.php');

        //
        $path = $this->getCachePath();

        if (! $this->isCacheExpired($path, $packagesPath) && ! $this->isCacheExpired($path, $configPath)) {
            $data = (array) $this->files->getRequire($path);

            return static::$packages = collect($data);
        }

        $this->writeCache($path, $packages = $this->getPackages());

        return static::$packages = $packages;
    }

    /**
     * Écrivez le fichier de cache de service sur le disque.
     *
     * @param  string $path
     * @param  array|Collection  $packages
     * @return void
     */
    protected function writeCache($path, $packages)
    {
        $data = array();

        foreach ($packages->all() as $key => $package) {
            $properties = ($package instanceof Collection) ? $package->all() : $package;

            // Normaliser sur les chemins * nix.
            $properties['path'] = str_replace('\\', '/', $properties['path']);

            //
            ksort($properties);

            $data[] = $properties;
        }

        //
        $data = var_export($data, true);

        $content = <<<PHP
<?php

return $data;

PHP;

        $this->files->put($path, $content);
    }

    /**
    * Déterminez si le fichier cache a expiré.
    *
    * @param  string  $cachePath
    * @param  string  $path
    * @return bool
    */
    protected function isCacheExpired($cachePath, $path)
    {
        if (! $this->files->exists($cachePath)) {
            return true;
        }

        $lastModified = $this->files->lastModified($path);

        if ($lastModified < $this->files->lastModified($cachePath)) {
            return false;
        }

        return true;
    }

    /**
     * Obtenez le chemin du cache des packages.
     *
     * @return string
     */
    public function getCachePath()
    {
        return $this->config->get('packages.cache', STORAGE_PATH .'framework' .DS .'packages.php');
    }

    /**
     * Obtenez le nom d’un package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @return string
     */
    protected function getPackageName($package)
    {
        if (strpos($package, '/') === false) {
            return $package;
        }

        list ($vendor, $namespace) = explode('/', $package);

        return $namespace;
    }
}
