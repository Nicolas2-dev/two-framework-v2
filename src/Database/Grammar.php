<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;


abstract class Grammar
{
    /**
     * Le préfixe de la table de grammaire.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Enveloppez un tableau de valeurs.
     *
     * @param  array  $values
     * @return array
     */
    public function wrapArray(array $values)
    {
        return array_map(array($this, 'wrap'), $values);
    }

    /**
     * Enveloppez un tableau dans des identifiants de mots-clés.
     *
     * @param  string  $table
     * @return string
     */
    public function wrapTable($table)
    {
        if ($this->isExpression($table)) return $this->getValue($table);

        return $this->wrap($this->tablePrefix.$table);
    }

    /**
     * Enveloppez une valeur dans des identifiants de mots-clés.
     *
     * @param  string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($this->isExpression($value)) return $this->getValue($value);

        if (strpos(strtolower($value), ' as ') !== false) {
            $segments = explode(' ', $value);

            return $this->wrap($segments[0]).' as '.$this->wrapValue($segments[2]);
        }

        $wrapped = array();

        $segments = explode('.', $value);

        foreach ($segments as $key => $segment) {
            if ($key == 0 && count($segments) > 1) {
                $wrapped[] = $this->wrapTable($segment);
            } else {
                $wrapped[] = $this->wrapValue($segment);
            }
        }

        return implode('.', $wrapped);
    }

    /**
     * Enveloppez une seule chaîne dans des identifiants de mots clés.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') return $value;

        return '"'.str_replace('"', '""', $value).'"';
    }

    /**
     * Convertit un tableau de noms de colonnes en une chaîne délimitée.
     *
     * @param  array   $columns
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map(array($this, 'wrap'), $columns));
    }

    /**
     * Créez des espaces réservés pour les paramètres de requête pour un tableau.
     *
     * @param  array   $values
     * @return string
     */
    public function parameterize(array $values)
    {
        return implode(', ', array_map(array($this, 'parameter'), $values));
    }

    /**
     * Obtenez l’espace réservé du paramètre de requête approprié pour une valeur.
     *
     * @param  mixed   $value
     * @return string
     */
    public function parameter($value)
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Obtenez la valeur d'une expression brute.
     *
     * @param  \Two\Database\Query\Expression  $expression
     * @return string
     */
    public function getValue($expression)
    {
        return $expression->getValue();
    }

    /**
     * Déterminez si la valeur donnée est une expression brute.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function isExpression($value)
    {
        return $value instanceof Query\Expression;
    }

    /**
     * Obtenez le format des dates stockées dans la base de données.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Obtenez le préfixe de la table de grammaire.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Définissez le préfixe de la table de grammaire.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        return $this;
    }

}
