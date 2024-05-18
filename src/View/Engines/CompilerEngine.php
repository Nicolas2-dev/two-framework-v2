<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Engines;

use Two\Exceptions\Exception\FatalThrowableError;
use Two\View\Contracts\Compilers\CompilerInterface;


class CompilerEngine extends PhpEngine
{
    /**
     * L'instance du compilateur Axe.
     *
     * @var \Two\View\Contracts\Compilers\CompilerInterface
     */
    protected $compiler;

    /**
     * Une pile des derniers modèles compilés.
     *
     * @var array
     */
    protected $lastCompiled = array();

    /**
     * Créez une nouvelle instance de moteur de vue Axe.
     *
     * @param  \Two\View\Contracts\Compilers\CompilerInterface  $compiler
     * @return void
     */
    public function __construct(CompilerInterface $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Obtenez le contenu évalué de la vue.
     *
     * @param  string  $path
     * @param  array   $data
     * @return string
     */
    public function get($path, array $data = array())
    {
        $this->lastCompiled[] = $path;

        // Si cette vue donnée a expiré, cela signifie qu'elle a simplement été modifiée depuis
        // il a été compilé pour la dernière fois, nous allons recompiler les vues afin de pouvoir évaluer un
        // nouvelle copie de la vue. Nous transmettrons au compilateur le chemin de la vue.
        if ($this->compiler->isExpired($path)) {
            $this->compiler->compile($path);
        }

        $compiled = $this->compiler->getCompiledPath($path);

        // Une fois que nous aurons le chemin d'accès au fichier compilé, nous évaluerons les chemins avec
        // PHP typique comme n'importe quel autre modèle. Nous conservons également une pile de vues
        // qui ont été rendus pour que les messages d'exception de droit soient générés.
        $results = $this->evaluatePath($compiled, $data);

        array_pop($this->lastCompiled);

        return $results;
    }

    /**
     * Gérer une exception de vue.
     *
     * @param  \Exception  $e
     * @param  int  $obLevel
     * @return void
     *
     * @throws $e
     */
    protected function handleViewException($e, $obLevel)
    {
        if (! $e instanceof \Exception) {
            $e = new FatalThrowableError($e);
        }

        $e = new \ErrorException($this->getMessage($e), 0, 1, $e->getFile(), $e->getLine(), $e);

        parent::handleViewException($e, $obLevel);
    }

    /**
     * Obtenez le message d'exception pour une exception.
     *
     * @param  \Exception  $e
     * @return string
     */
    protected function getMessage($e)
    {
        $path = last($this->lastCompiled);

        return $e->getMessage() .' (View: ' .realpath($path) .')';
    }

    /**
     * Obtenez l'implémentation du compilateur.
     *
     * @return \Two\View\Contracts\Compilers\CompilerInterface
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

}
