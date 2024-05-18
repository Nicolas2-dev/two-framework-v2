<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation\Contracts;


interface ValidatesWhenResolvedInterface
{
    /**
     * Validez l’instance de classe donnée.
     *
     * @return void
     */
    public function validate();
}
