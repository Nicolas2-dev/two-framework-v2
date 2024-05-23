<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Exception;


class ModelNotFoundException extends \RuntimeException
{
    /**
     * Nom du modèle ORM concerné.
     *
     * @var string
     */
    protected $model;

    /**
     * Définissez le modèle ORM concerné.
     *
     * @param  string   $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;

        $this->message = "No query results for model [{$model}].";

        return $this;
    }

    /**
     * Obtenez le modèle ORM concerné.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

}
