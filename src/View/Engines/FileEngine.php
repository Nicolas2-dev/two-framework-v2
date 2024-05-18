<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Engines;

use Two\View\Contracts\Engines\EngineInterface;


class FileEngine implements EngineInterface
{
    /**
     * Obtenez le contenu évalué de la vue.
     *
     * @param  string  $path
     * @param  array   $data
     * @return string
     */
    public function get($path, array $data = array())
    {
        return file_get_contents($path);
    }
}
