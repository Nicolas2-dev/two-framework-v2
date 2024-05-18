<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\file;

use Exception;

use Two\Filesystem\Filesystem;
use Two\Cache\Contracts\StoreInterface;


class FileStore implements StoreInterface
{
    /**
     * L'instance de deux systèmes de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Le répertoire du cache de fichiers
     *
     * @var string
     */
    protected $directory;


    /**
     * Créez une nouvelle instance de magasin de cache de fichiers.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $directory
     * @return void
     */
    public function __construct(Filesystem $files, $directory)
    {
        $this->files = $files;

        $this->directory = $directory;
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        return array_get($this->getPayload($key), 'data');
    }

    /**
     * Récupérez un élément et son heure d'expiration du cache par clé.
     *
     * @param  string  $key
     * @return array
     */
    protected function getPayload($key)
    {
        $path = $this->path($key);

        // Si le fichier n'existe pas, nous ne pouvons évidemment pas renvoyer le cache donc nous le ferons
        // renvoie simplement null. Sinon, nous obtiendrons le contenu du fichier et obtiendrons
        // les horodatages UNIX d'expiration à partir du début du contenu du fichier.
        if (! $this->files->exists($path)) {
            return array('data' => null, 'time' => null);
        }

        try
        {
            $expire = substr($contents = $this->files->get($path, true), 0, 10);
        }
        catch (Exception $e) {
            return array('data' => null, 'time' => null);
        }

        // Si l'heure actuelle est supérieure aux horodatages d'expiration, nous supprimerons
        // le fichier et renvoie null. Cela aide à nettoyer les anciens fichiers et à conserver
        // ce répertoire est beaucoup plus propre pour nous car les anciens fichiers ne traînent pas.
        if (time() >= $expire) {
            $this->forget($key);

            return array('data' => null, 'time' => null);
        }

        $data = unserialize(substr($contents, 10));

        // Ensuite, nous extrairons le nombre de minutes restantes pour un cache
        // afin que nous puissions conserver correctement le temps pour des choses comme l'incrément
        // opération pouvant être effectuée sur le cache. Nous allons compléter cela.
        $time = ceil(($expire - time()) / 60);

        return compact('data', 'time');
    }

    /**
     * Stockez un élément dans le cache pendant un nombre de minutes donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $minutes
     * @return void
     */
    public function put($key, $value, $minutes)
    {
        $value = $this->expiration($minutes) .serialize($value);

        $this->createCacheDirectory($path = $this->path($key));

        $this->files->put($path, $value);
    }

    /**
     * Créez le répertoire de cache de fichiers si nécessaire.
     *
     * @param  string  $path
     * @return void
     */
    protected function createCacheDirectory($path)
    {
        try
        {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        } catch (Exception $e) {
            //
        }
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        $raw = $this->getPayload($key);

        $int = ((int) $raw['data']) + $value;

        $this->put($key, $int, (int) $raw['time']);

        return $int;
    }

    /**
     * Décrémenter la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key)
    {
        $file = $this->path($key);

        if ($this->files->exists($file))
        {
            $this->files->delete($file);
        }
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        if (! $this->files->isDirectory($this->directory)) {
            return;
        }

        foreach ($this->files->directories($this->directory) as $directory) {
            $this->files->deleteDirectory($directory);
        }
    }

    /**
     * Obtenez le chemin complet de la clé de cache donnée.
     *
     * @param  string  $key
     * @return string
     */
    protected function path($key)
    {
        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);

        return $this->directory .DS .implode(DS, $parts) .DS .$hash .'.cache';
    }

    /**
     * Obtenez le délai d'expiration en fonction des minutes données.
     *
     * @param  int  $minutes
     * @return int
     */
    protected function expiration($minutes)
    {
        if ($minutes === 0) return 9999999999;

        return time() + ($minutes * 60);
    }

    /**
     * Obtenez l'instance du système de fichiers.
     *
     * @return \Two\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Obtenez le répertoire de travail du cache.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Obtenez le préfixe de la clé de cache.
     *
     * @return string
     */
    public function getPrefix()
    {
        return '';
    }

}
