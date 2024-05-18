<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;


class ModelIdentifier
{
    /**
     * Le nom de classe du modèle.
     *
     * @var string
     */
    public $class;

    /**
     * L'identifiant unique du modèle.
     *
     * @var mixed
     */
    public $id;

    /**
     * Créez un nouvel identifiant de modèle.
     *
     * @param  string  $class
     * @param  mixed  $id
     * @return void
     */
    public function __construct($class, $id)
    {
        $this->id = $id;
        $this->class = $class;
    }
}
