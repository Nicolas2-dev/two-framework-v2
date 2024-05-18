<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Bus\Traits;

use Two\Bus\PendingDispatch;


trait DispatchableTrait
{

    /**
     * Distribuez le travail avec les arguments donnés.
     *
     * @return \Two\Bus\PendingDispatch
     */
    public static function dispatch()
    {
        $args = func_get_args();

        return new PendingDispatch(new static(...$args));
    }
}
