<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Container;


class NamespacedItemResolver
{

    /**
     * Un cache des éléments analysés.
     *
     * @var array
     */
    protected $parsed = array();


    /**
     * Analyser une clé en espace de noms, groupe et élément.
     *
     * @param  string  $key
     * @return array
     */
    public function parseKey($key)
    {
        // Si nous avons déjà analysé la clé donnée, nous renverrons la version en cache que nous
        // déjà, car cela nous épargnera du temps de traitement. Nous mettons en cache chaque clé 
        // que nous analysons afin de pouvoir la renvoyer rapidement à toutes les requêtes ultérieures.
        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        // Si la clé ne contient pas de double-virgule, cela signifie que la clé n'est pas dans un
        // espace de noms, et n'est qu'un élément de configuration normal. Les espaces de noms sont un
        // outil pour organiser les éléments de configuration pour des éléments tels que les modules.
        if (strpos($key, '::') === false) {
            $segments = explode('.', $key);

            $parsed = $this->parseBasicSegments($segments);
        } else {
            $parsed = $this->parseNamespacedSegments($key);
        }

        // Une fois que nous avons le tableau analysé des éléments de cette clé, tels que ses groupes
        // et l'espace de noms, nous mettrons en cache chaque tableau dans une simple liste contenant
        // la clé et le tableau analysé pour des recherches rapides pour des requêtes ultérieures.
        return $this->parsed[$key] = $parsed;
    }

    /**
     * Parse an array of basic segments.
     *
     * @param  array  $segments
     * @return array
     */
    protected function parseBasicSegments(array $segments)
    {
        // Le premier segment d'un tableau de base sera toujours le groupe, nous pouvons donc aller
        // avancez et saisissez ce segment. S'il n'y a qu'un seul segment total, nous sommes
        // extrait simplement un groupe entier du tableau et pas un seul élément.
        $group = $segments[0];

        if (count($segments) == 1) {
            return array(null, $group, null);
        }

        // S'il y a plus d'un segment dans ce groupe, cela signifie que nous tirons
        // un élément spécifique d'un groupe et devra renvoyer le nom de l'élément
        // ainsi que le groupe afin que nous sachions quel élément extraire des tableaux.
        else {
            $item = implode('.', array_slice($segments, 1));

            return array(null, $group, $item);
        }
    }

    /**
     * Analyser un tableau de segments d'espace de noms.
     *
     * @param  string  $key
     * @return array
     */
    protected function parseNamespacedSegments($key)
    {
        list($namespace, $item) = explode('::', $key);

        // Nous allons d'abord exploser le premier segment pour obtenir l'espace de noms et le groupe
        // puisque l'élément doit être dans les segments restants. Une fois que nous avons ces
        // deux éléments de données, nous pouvons procéder à l'analyse de la valeur de l'élément.
        $itemSegments = explode('.', $item);

        $groupAndItem = array_slice($this->parseBasicSegments($itemSegments), 1);

        return array_merge(array($namespace), $groupAndItem);
    }

    /**
     * Définissez la valeur analysée d'une clé.
     *
     * @param  string  $key
     * @param  array   $parsed
     * @return void
     */
    public function setParsedKey($key, $parsed)
    {
        $this->parsed[$key] = $parsed;
    }

}
