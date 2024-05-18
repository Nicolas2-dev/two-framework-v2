<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Filesystem;

use FilesystemIterator;

use Two\Filesystem\Exception\FileNotFoundException;

use Symfony\Component\Finder\Finder;


class Filesystem
{

    /**
     * Déterminer si un fichier existe.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists($path)
    {
        return file_exists($path);
    }

    /**
     * Obtenir le contenu d'un fichier.
     *
     * @param  string  $path
     * @param  bool  $lock
     * @return string
     *
     * @throws FileNotFoundException
     */
    public function get($path, $lock = false)
    {
        if ($this->isFile($path)) {
            return $lock ? $this->sharedGet($path) : file_get_contents($path);
        }

        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Obtenir le contenu d'un fichier avec accès partagé.
     *
     * @param  string  $path
     * @return string
     */
    public function sharedGet($path)
    {
        $contents = '';

        $handle = fopen($path, 'rb');

        if (! is_null($handle)) {
            try {
                if (flock($handle, LOCK_SH)) {
                    clearstatcache(true, $path);

                    $contents = fread($handle, $this->size($path) ?: 1);

                    flock($handle, LOCK_UN);
                }
            }
            finally {
                fclose($handle);
            }
        }

        return $contents;
    }

    /**
     * Obtenir la valeur renvoyée d'un fichier.
     *
     * @param  string  $path
     * @return mixed
     *
     * @throws FileNotFoundException
     */
    public function getRequire($path)
    {
        if ($this->isFile($path)) return require $path;

        throw new FileNotFoundException("File does not exist at path {$path}");
    }

    /**
     * Exiger le fichier donné une fois.
     *
     * @param  string  $file
     * @return mixed
     */
    public function requireOnce($file)
    {
        require_once $file;
    }

    /**
     * Ecrire le contenu d'un fichier.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  bool  $lock
     * @return int
     */
    public function put($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Ajouter au début d'un fichier.
     *
     * @param  string  $path
     * @param  string  $data
     * @return int
     */
    public function prepend($path, $data)
    {
        if ($this->exists($path)) {
            return $this->put($path, $data.$this->get($path));
        }

        return $this->put($path, $data);
    }

    /**
     * Ajouter à un fichier.
     *
     * @param  string  $path
     * @param  string  $data
     * @return int
     */
    public function append($path, $data)
    {
        return file_put_contents($path, $data, FILE_APPEND);
    }

    /**
     * Supprimer le fichier à un chemin donné.
     *
     * @param  string|array  $paths
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        $success = true;

        foreach ($paths as $path) {
            if (! @unlink($path)) $success = false;
        }

        return $success;
    }

    /**
     * Déplacer un fichier vers un nouvel emplacement.
     *
     * @param  string  $path
     * @param  string  $target
     * @return bool
     */
    public function move($path, $target)
    {
        return rename($path, $target);
    }

    /**
     * Copiez un fichier vers un nouvel emplacement.
     *
     * @param  string  $path
     * @param  string  $target
     * @return bool
     */
    public function copy($path, $target)
    {
        return copy($path, $target);
    }

    /**
     * Créez un lien physique vers le fichier ou le répertoire cible.
     *
     * @param  string  $target
     * @param  string  $link
     * @return void
     */
    public function link($target, $link)
    {
        if (! windows_os()) {
            return symlink($target, $link);
        }

        $mode = $this->isDirectory($target) ? 'J' : 'H';

        exec("mklink /{$mode} \"{$link}\" \"{$target}\"");
    }

    /**
     * Extrayez le nom du fichier à partir d'un chemin de fichier.
     *
     * @param  string  $path
     * @return string
     */
    public function name($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Extraire l'extension de fichier à partir d'un chemin de fichier.
     *
     * @param  string  $path
     * @return string
     */
    public function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Obtenir le type de fichier d'un fichier donné.
     *
     * @param  string  $path
     * @return string
     */
    public function type($path)
    {
        return filetype($path);
    }

    /**
     * Obtenir la taille de fichier d'un fichier donné.
     *
     * @param  string  $path
     * @return int
     */
    public function size($path)
    {
        return filesize($path);
    }

    /**
     * Obtenez l'heure de la dernière modification du fichier.
     *
     * @param  string  $path
     * @return int
     */
    public function lastModified($path)
    {
        return filemtime($path);
    }

    /**
     * Déterminez si le chemin donné est un répertoire.
     *
     * @param  string  $directory
     * @return bool
     */
    public function isDirectory($directory)
    {
        return is_dir($directory);
    }

    /**
     * Déterminez si le chemin donné est accessible en écriture.
     *
     * @param  string  $path
     * @return bool
     */
    public function isWritable($path)
    {
        return is_writable($path);
    }

    /**
     * Déterminez si le chemin donné est un fichier.
     *
     * @param  string  $file
     * @return bool
     */
    public function isFile($file)
    {
        return is_file($file);
    }

    /**
     * Trouver des noms de chemin correspondant à un modèle donné.
     *
     * @param  string  $pattern
     * @param  int     $flags
     * @return array
     */
    public function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }

    /**
     * Obtenez un tableau de tous les fichiers dans un répertoire.
     *
     * @param  string  $directory
     * @return array
     */
    public function files($directory)
    {
        $glob = glob($directory .'/*');

        if ($glob === false) return array();

        // Pour obtenir les fichiers appropriés, nous allons simplement glob le répertoire et filtrer
        // supprime tous les "fichiers" qui ne sont pas vraiment des fichiers afin que nous ne 
        // nous retrouvions pas avec répertoires dans notre liste, mais uniquement les vrais fichiers
        // dans le répertoire.
        return array_filter($glob, function($file)
        {
            return filetype($file) == 'file';
        });
    }

    /**
     * Récupère tous les fichiers du répertoire donné (récursif).
     *
     * @param  string  $directory
     * @return array
     */
    public function allFiles($directory)
    {
        return iterator_to_array(Finder::create()->files()->in($directory), false);
    }

    /**
     * Obtenez tous les répertoires dans un répertoire donné.
     *
     * @param  string  $directory
     * @return array
     */
    public function directories($directory)
    {
        $directories = array();

        foreach (Finder::create()->in($directory)->directories()->depth(0) as $dir) {
            $directories[] = $dir->getPathname();
        }

        return $directories;
    }

    /**
     * Créer un répertoire.
     *
     * @param  string  $path
     * @param  int     $mode
     * @param  bool    $recursive
     * @param  bool    $force
     * @return bool
     */
    public function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Copier un répertoire d'un emplacement à un autre.
     *
     * @param  string  $directory
     * @param  string  $destination
     * @param  int     $options
     * @return bool
     */
    public function copyDirectory($directory, $destination, $options = null)
    {
        if (! $this->isDirectory($directory)) return false;

        $options = $options ?: FilesystemIterator::SKIP_DOTS;

        // Si le répertoire de destination n'existe pas réellement, nous continuerons et
        // le crée de manière récursive, ce qui prépare simplement la destination à copier
        // les fichiers dessus. Une fois que nous aurons créé le répertoire, nous procéderons
        // à la copie.
        if (! $this->isDirectory($destination)) {
            $this->makeDirectory($destination, 0777, true);
        }

        $items = new FilesystemIterator($directory, $options);

        foreach ($items as $item) {
            // Au fur et à mesure que nous parcourrons les éléments, nous vérifierons si le fichier
            // actuel est réellement un répertoire ou un fichier. Lorsqu'il s'agit en fait d'un
            // répertoire, nous devrons appeler retour dans cette fonction de manière récursive 
            // pour continuer à copier ces dossiers imbriqués.
            $target = $destination.'/'.$item->getBasename();

            if ($item->isDir()) {
                $path = $item->getPathname();

                if (! $this->copyDirectory($path, $target, $options)) return false;
            }

            // Si les éléments actuels ne sont qu'un fichier normal, nous le copierons simplement 
            // dans le nouveau emplacement et continuez à boucler. Si pour une raison quelconque la
            // copie échoue, nous renflouerons et renvoie false, afin que le développeur sache que 
            // le processus de copie a échoué.
            else {
                if (! $this->copy($item->getPathname(), $target)) return false;
            }
        }

        return true;
    }

    /**
     * Supprimer récursivement un répertoire.
     *
     * Le répertoire lui-même peut éventuellement être conservé.
     *
     * @param  string  $directory
     * @param  bool    $preserve
     * @return bool
     */
    public function deleteDirectory($directory, $preserve = false)
    {
        if (! $this->isDirectory($directory)) return false;

        $items = new FilesystemIterator($directory);

        foreach ($items as $item) {
            // Si l'élément est un répertoire, nous pouvons simplement revenir en arrière dans la
            // fonction et supprimer ce sous-répertoire sinon nous supprimerons simplement le 
            // fichier et continuez à parcourir chaque fichier jusqu'à ce que le répertoire soit
            // nettoyé.
            if ($item->isDir()) {
                $this->deleteDirectory($item->getPathname());
            }

            // Si l'élément n'est qu'un fichier, nous pouvons continuer et le supprimer puisque nous
            // sommes juste en boucle et en cirant tous les fichiers de ce répertoire et en appelant
            //  les répertoires de manière récursive, nous supprimons donc le vrai chemin.
            else {
                $this->delete($item->getPathname());
            }
        }

        if (! $preserve) @rmdir($directory);

        return true;
    }

    /**
     * Videz le répertoire spécifié de tous les fichiers et dossiers.
     *
     * @param  string  $directory
     * @return bool
     */
    public function cleanDirectory($directory)
    {
        return $this->deleteDirectory($directory, true);
    }

}
