<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Compilers;

use Two\View\Compilers\Compiler;
use Two\View\Contracts\Compilers\CompilerInterface;

use Parsedown;


class MarkdownCompiler extends Compiler implements CompilerInterface
{
    /**
     * Le fichier est en cours de compilation.
     *
     * @var string
     */
    protected $path;


    /**
     * Compilez la vue sur le chemin donné.
     *
     * @param  string  $path
     * @return void
     */
    public function compile($path = null)
    {
        if (! is_null($path)) {
            $this->setPath($path);
        }

        $contents = $this->compileString($this->files->get($path));

        if ( ! is_null($this->cachePath)) {
            $this->files->put($this->getCompiledPath($this->getPath()), $contents);
        }
    }

    /**
     * Obtenez le chemin en cours de compilation.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Définissez le chemin en cours de compilation.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Compilez le contenu du fichier Markdown donné.
     *
     * @param  string  $value
     * @return string
     */
    public function compileString($value)
    {
        $parsedown = new Parsedown();

        return $parsedown->text($value);
    }
}
