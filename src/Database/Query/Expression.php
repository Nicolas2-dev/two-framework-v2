<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\Query;


class Expression
{
    /**
     * La valeur de l'expression.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Créez une nouvelle expression de requête brute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Obtenez la valeur de l'expression.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Obtenez la valeur de l'expression.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getValue();
    }

}
