<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Hashing\Contracts;


interface HasherInterface
{
    
    /**
     * Hachez la valeur donnée.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function make($value, array $options = array());

    /**
     * Vérifiez la valeur simple donnée par rapport à un hachage.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function check($value, $hashedValue, array $options = array());

    /**
     * Vérifiez si le hachage donné a été haché à l'aide des options données.
     *
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = array());

}
