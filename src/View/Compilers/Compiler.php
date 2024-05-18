<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Compilers;

use Two\Filesystem\Filesystem;


abstract class Compiler
{

    /**
     * L'instance du système de fichiers.
     *
     * @var \Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Obtenez le chemin du cache pour les vues compilées.
     *
     * @var string
     */
    protected $cachePath;


    /**
     * Créez une nouvelle instance de compilateur.
     *
     * @param  \Two\Filesystem\Filesystem  $files
     * @param  string  $cachePath
     * @return void
     */
    public function __construct(Filesystem $files, $cachePath)
    {
        $this->files = $files;

        $this->cachePath = $cachePath;
    }

    /**
     * Obtenez le chemin d'accès à la version compilée d'une vue.
     *
     * @param  string  $path
     * @return string
     */
    public function getCompiledPath($path)
    {
        return $this->cachePath .DS .sha1($path) .'.php';
    }

    /**
     * Déterminez si la vue sur le chemin donné a expiré.
     *
     * @param  string  $path
     * @return bool
     */
    public function isExpired($path)
    {
        $compiled = $this->getCompiledPath($path);

        // Si le fichier compilé n'existe pas, nous indiquerons que la vue est expirée afin qu'elle puisse être recompilée.
        // Sinon, nous vérifierons que la dernière modification des vues est inférieure aux heures de modification des vues compilées.
        if (is_null($this->cachePath) || ! $this->files->exists($compiled)) {
            return true;
        }

        $lastModified = $this->files->lastModified($path);

        if ($lastModified >= $this->files->lastModified($compiled)) {
            return true;
        }

        return false;
    }

}
