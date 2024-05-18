<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use Two\Support\Traits\MacroableTrait;

use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;


class UploadedFile extends SymfonyUploadedFile
{
    use MacroableTrait;

    /**
     * Obtenez le chemin complet du fichier.
     *
     * @return string
     */
    public function path()
    {
        return $this->getRealPath();
    }

    /**
     * Obtenez l'extension du fichier.
     *
     * @return string
     */
    public function extension()
    {
        return $this->guessExtension();
    }

    /**
     * Obtenez l'extension du fichier fournie par le client.
     *
     * @return string
     */
    public function clientExtension()
    {
        return $this->guessClientExtension();
    }

    /**
     * Obtenez un nom de fichier pour le fichier qui est le hachage MD5 du contenu.
     *
     * @param  string  $path
     * @return string
     */
    public function hashName($path = null)
    {
        if (! is_null($path)) {
            $path = rtrim($path, '/\\') .DS;
        }

        return $path .md5_file($this->path()) .'.' .$this->extension();
    }

    /**
     * Créez une nouvelle instance de fichier à partir d'une instance de base.
     *
     * @param  \Symfony\Component\HttpFoundation\File\UploadedFile  $file
     * @param  bool $test
     * @return static
     */
    public static function createFromBase(SymfonyUploadedFile $file, $test = false)
    {
        return ($file instanceof static) ? $file : new static(
            $file->getPathname(),
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            //$file->getClientSize(),
            $file->getError(),
            $test
        );
    }
}
