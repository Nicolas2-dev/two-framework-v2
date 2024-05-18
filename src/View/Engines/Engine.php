<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Engines;


abstract class Engine
{

    /**
     * La vue qui a été rendue en dernier lieu.
     *
     * @var string
     */
    protected $lastRendered;

    
    /**
     * Obtenez la dernière vue rendue.
     *
     * @return string
     */
    public function getLastRendered()
    {
        return $this->lastRendered;
    }

}
