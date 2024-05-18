<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support;

use Closure;

use Two\Collection\Collection;
use Two\Support\Traits\MacroableTrait;


class Arr
{
    use MacroableTrait;


    /**
     * Ajouter un élément à un tableau en utilisant la notation "point" s'il n'existe pas.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $value
     * @return array
     */
    public static function add($array, $key, $value)
    {
        if (is_null(static::get($array, $key)))
        {
            static::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Construire un nouveau tableau à l'aide d'un rappel.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    public static function build($array, Closure $callback)
    {
        $results = array();

        foreach ($array as $key => $value)
        {
            list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

            $results[$innerKey] = $innerValue;
        }

        return $results;
    }

    /**
     * Diviser un tableau en deux tableaux. L'un avec des clés et l'autre avec des valeurs.
     *
     * @param  array  $array
     * @return array
     */
    public static function divide($array)
    {
        return array(array_keys($array), array_values($array));
    }

    /**
     * Aplatir un tableau associatif multidimensionnel avec des points.
     *
     * @param  array   $array
     * @param  string  $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = array();

        foreach ($array as $key => $value)
        {
            if (is_array($value))
            {
                $results = array_merge($results, static::dot($value, $prepend.$key.'.'));
            }
            else
            {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Récupère tout le tableau donné à l'exception d'un tableau d'éléments spécifié.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    public static function except($array, $keys)
    {
        return array_diff_key($array, array_flip((array) $keys));
    }

    /**
     * Récupérer un tableau aplati d'un élément de tableau imbriqué.
     *
     * @param  array   $array
     * @param  string  $key
     * @return array
     */
    public static function fetch($array, $key)
    {
        foreach (explode('.', $key) as $segment)
        {
            $results = array();

            foreach ($array as $value)
            {
                if (array_key_exists($segment, $value = (array) $value))
                {
                    $results[] = $value[$segment];
                }
            }

            $array = array_values($results);
        }

        return array_values($results);
    }

    /**
     * Renvoie le premier élément d'un tableau réussissant un test de vérité donné.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @param  mixed     $default
     * @return mixed
     */
    public static function first($array, $callback, $default = null)
    {
        foreach ($array as $key => $value)
        {
            if (call_user_func($callback, $key, $value)) return $value;
        }

        return value($default);
    }

    /**
     * Renvoie le dernier élément d'un tableau passant un test de vérité donné.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @param  mixed     $default
     * @return mixed
     */
    public static function last($array, $callback, $default = null)
    {
        return static::first(array_reverse($array), $callback, $default);
    }

    /**
     * Aplatir un tableau multidimensionnel en un seul niveau.
     *
     * @param  array  $array
     * @return array
     */
    public static function flatten($array)
    {
        $return = array();

        array_walk_recursive($array, function($x) use (&$return) { $return[] = $x; });

        return $return;
    }

    /**
     * Supprimez un ou plusieurs éléments de tableau d'un tableau donné en utilisant la notation "point".
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return void
     */
    public static function forget(&$array, $keys)
    {
        $original =& $array;

        foreach ((array) $keys as $key)
        {
            $parts = explode('.', $key);

            while (count($parts) > 1)
            {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part]))
                {
                    $array =& $array[$part];
                }
            }

            unset($array[array_shift($parts)]);

            // nettoyer après chaque passage
            $array =& $original;
        }
    }

    /**
     * Obtenez un élément d'un tableau en utilisant la notation "dot".
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (is_null($key)) return $array;

        if (isset($array[$key])) return $array[$key];

        foreach (explode('.', $key) as $segment)
        {
            if (! is_array($array) || ! array_key_exists($segment, $array))
            {
                return value($default);
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Vérifiez si un élément existe dans un tableau en utilisant la notation "dot".
     *
     * @param  array   $array
     * @param  string  $key
     * @return bool
     */
    public static function has($array, $key)
    {
        if (empty($array) || is_null($key)) return false;

        if (array_key_exists($key, $array)) return true;

        foreach (explode('.', $key) as $segment)
        {
            if (! is_array($array) || ! array_key_exists($segment, $array))
            {
                return false;
            }

            $array = $array[$segment];
        }

        return true;
    }

    /**
     * Obtenez un sous-ensemble des éléments du tableau donné.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    public static function only($array, $keys)
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Cueillir un tableau de valeurs à partir d'un tableau.
     *
     * @param  array   $array
     * @param  string  $value
     * @param  string  $key
     * @return array
     */
    public static function pluck($array, $value, $key = null)
    {
        $results = array();

        foreach ($array as $item) {
            $itemValue = is_object($item) ? $item->{$value} : $item[$value];

            // Si la clé est "null", nous ajouterons simplement la valeur au tableau et conserverons
            // boucle. Sinon, nous clérons le tableau en utilisant la valeur de la clé que nous
            // reçu du développeur. Ensuite, nous renverrons la forme finale du tableau.
            if (is_null($key)) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? $item->{$key} : $item[$key];

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Obtenez une valeur du tableau et supprimez-la.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = static::get($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * Définissez un élément de tableau sur une valeur donnée en utilisant la notation "point".
     *
     * Si aucune clé n'est donnée à la méthode, le tableau entier sera remplacé.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $value
     * @return array
     */
    public static function set(&$array, $key, $value)
    {
        if (is_null($key)) return $array = $value;

        $keys = explode('.', $key);

        while (count($keys) > 1)
        {
            $key = array_shift($keys);

            // Si la clé n'existe pas à cette profondeur, nous allons simplement créer un tableau vide
            // pour contenir la valeur suivante, nous permettant de créer les tableaux pour contenir 
            // final valeurs à la bonne profondeur. Ensuite, nous continuerons à creuser 
            // dans le tableau.
            if (! isset($array[$key]) || ! is_array($array[$key]))
            {
                $array[$key] = array();
            }

            $array =& $array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Trier le tableau en utilisant la fermeture donnée.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    public static function sort($array, Closure $callback)
    {
        return Collection::make($array)->sortBy($callback)->all();
    }

    /**
     * Filtrer le tableau en utilisant la fermeture donnée.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    public static function where($array, Closure $callback)
    {
        $filtered = array();

        foreach ($array as $key => $value)
        {
            if (call_user_func($callback, $key, $value)) $filtered[$key] = $value;
        }

        return $filtered;
    }

}
