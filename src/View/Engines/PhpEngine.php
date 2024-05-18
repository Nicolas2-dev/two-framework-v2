<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Engines;

use Exception;
use Throwable;

use Two\View\Contracts\Engines\EngineInterface;


class PhpEngine implements EngineInterface
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
        return $this->evaluatePath($path, $data);
    }

    /**
     * Obtenez le contenu évalué de la vue au chemin donné.
     *
     * @param  string  $__path
     * @param  array   $__data
     * @return string
     */
    protected function evaluatePath($__path, $__data)
    {
        $obLevel = ob_get_level();

        //
        ob_start();

        // Extrayez les variables de rendu.
        foreach ($__data as $__variable => $__value) {
            if (in_array($__variable, array('__path', '__data'))) {
                continue;
            }

            ${$__variable} = $__value;
        }

        // Entretien ménager...
        unset($__data, $__variable, $__value);

        // Nous évaluerons le contenu de la vue à l'intérieur d'un bloc try/catch afin de pouvoir
        // élimine toute sortie parasite qui pourrait sortir avant qu'une erreur ne se produise ou
        // une exception est levée. Cela empêche toute fuite de vues partielles.
        try {
            include $__path;
        }
        catch (Exception $e) {
            $this->handleViewException($e, $obLevel);
        }
        catch (Throwable $e) {
            $this->handleViewException($e, $obLevel);
        }

        return ltrim(ob_get_clean());
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
        while (ob_get_level() > $obLevel) {
            ob_end_clean();
        }

        throw $e;
    }

}
