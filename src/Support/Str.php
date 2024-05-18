<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Support;

use RuntimeException;

use Two\Support\Traits\MacroableTrait;
use Two\Support\Pluralizer;

use Stringy\StaticStringy;


class Str
{

    use MacroableTrait;


    /**
     * Le cache des mots serpent-cas.
     *
     * @var array
     */
    protected static $snakeCache = array();

    /**
     * Le cache des mots en casse chameau.
     *
     * @var array
     */
    protected static $camelCache = array();

    /**
     * Le cache des mots casqués.
     *
     * @var array
     */
    protected static $studlyCache = array();


    /**
     * Translittérer une valeur UTF-8 en ASCII.
     *
     * @param  string  $value
     * @return string
     */
    public static function ascii($value)
    {
        return StaticStringy::toAscii($value);
    }

    /**
     * Convertir une valeur en cas de chameau.
     *
     * @param  string  $value
     * @return string
     */
    public static function camel($value)
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Détermine si une chaîne donnée contient une sous-chaîne donnée.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    public static function contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if (($needle != '') && (mb_strpos($haystack, $needle) !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si une chaîne donnée se termine par une sous-chaîne donnée.
     *
     * @param string  $haystack
     * @param string|array  $needles
     * @return bool
     */
    public static function endsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if (substr($haystack, -strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param  string  $value
     * @param  string  $cap
     * @return string
     */
    public static function finish($value, $cap)
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:'.$quoted.')+$/', '', $value).$cap;
    }

    /**
     * Détermine si une chaîne donnée correspond à un modèle donné.
     *
     * @param  string  $pattern
     * @param  string  $value
     * @return bool
     */
	public static function is($pattern, $value)
    {
        $value = (string) $value;

        if (! is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $pattern) {
            $pattern = (string) $pattern;

            // If the given value is an exact match we can of course return true right
            // from the beginning. Otherwise, we will translate asterisks and do an
            // actual pattern match against the two strings to see if they match.
            if ($pattern === $value) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            // Asterisks are translated into zero-or-more regular expression wildcards
            // to make it convenient to check if the strings starts with the given
            // pattern such as "library/*", making any string check convenient.
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^'.$pattern.'\z#u', $value) === 1) {
                return true;
            }
        }

        return false;


    }

    /**
     * Renvoie la longueur de la chaîne donnée.
     *
     * @param  string  $value
     * @return int
     */
    public static function length($value)
    {
        return mb_strlen($value);
    }

    /**
     * Limiter le nombre de caractères dans une chaîne.
     *
     * @param  string  $value
     * @param  int     $limit
     * @param  string  $end
     * @return string
     */
    public static function limit($value, $limit = 100, $end = '...')
    {
        if (mb_strlen($value) <= $limit) return $value;

        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')).$end;
    }

    /**
     * Convertir la chaîne donnée en minuscules.
     *
     * @param  string  $value
     * @return string
     */
    public static function lower($value)
    {
        return mb_strtolower($value);
    }

    /**
     * Limiter le nombre de mots dans une chaîne.
     *
     * @param  string  $value
     * @param  int     $words
     * @param  string  $end
     * @return string
     */
    public static function words($value, $words = 100, $end = '...')
    {
        preg_match('/^\s*+(?:\S++\s*+){1,'.$words.'}/u', $value, $matches);

        if (! isset($matches[0]) || (static::length($value) === static::length($matches[0]))) {
            return $value;
        }

        return rtrim($matches[0]) .$end;
    }

    /**
     * Analyser un rappel de style Class@method en classe et méthode.
     *
     * @param  string  $callback
     * @param  string  $default
     * @return array
     */
    public static function parseCallback($callback, $default = null)
    {
        return static::contains($callback, '@') ? explode('@', $callback, 2) : array($callback, $default);
    }

    /**
     * Obtenez la forme plurielle d'un mot anglais.
     *
     * @param  string  $value
     * @param  int  $count
     * @return string
     */
    public static function plural($value, $count = 2)
    {
        return Pluralizer::plural($value, $count);
    }

    /**
     * Générer une chaîne alphanumérique plus véritablement "aléatoire".
     *
     * @param  int $length
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function random($length = 16)
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;

            $bytes = static::randomBytes($size);

            $string .= substr(str_replace(array('/', '+', '='), '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Générer des octets plus véritablement "aléatoires".
     *
     * @param  int $length
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function randomBytes($length = 16)
    {
        if (PHP_MAJOR_VERSION >= 7) {
            $bytes = random_bytes($length);
        } else if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = \openssl_random_pseudo_bytes($length, $strong);

            if (($bytes === false) || ($strong === false)) {
                throw new RuntimeException('Unable to generate random string.');
            }
        } else {
            throw new RuntimeException('OpenSSL extension is required for PHP 5.');
        }

        return $bytes;
    }

    /**
     * Générer une chaîne alphanumérique "aléatoire".
     *
     * Ne devrait pas être considéré comme suffisant pour la cryptographie, etc.
     *
     * @param  int     $length
     * @return string
     */
    public static function quickRandom($length = 16)
    {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
    }

    /**
     * Compare deux chaînes à l'aide d'un algorithme à temps constant.
     *
     * @param  string $knownString
     * @param  string $userInput
     * @return bool
     */
    public static function equals($knownString, $userInput)
    {
        if (! is_string($knownString)) {
            $knownString = (string) $knownString;
        }

        if (! is_string($userInput)) {
            $userInput = (string) $userInput;
        }

        if (function_exists('hash_equals')) {
            return hash_equals($knownString, $userInput);
        }

        $knownLength = mb_strlen($knownString);

        if (mb_strlen($userInput) !== $knownLength) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $knownLength; ++$i) {
            $result |= (ord($knownString[$i]) ^ ord($userInput[$i]));
        }

        return (0 === $result);
    }

    /**
     * Convertit la chaîne donnée en majuscule.
     *
     * @param  string  $value
     * @return string
     */
    public static function upper($value)
    {
        return mb_strtoupper($value);
    }

    /**
     * Convertir la chaîne donnée en casse de titre.
     *
     * @param  string  $value
     * @return string
     */
    public static function title($value)
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Obtenez la forme singulière d'un mot anglais.
     *
     * @param  string  $value
     * @return string
     */
    public static function singular($value)
    {
        return Pluralizer::singular($value);
    }

    /**
     * Générer un "slug" convivial pour les URL à partir d'une chaîne donnée.
     *
     * @param  string  $title
     * @param  string  $separator
     * @return string
     */
    public static function slug($title, $separator = '-')
    {
        $title = static::ascii($title);

        // Convertit tous les tirets/traits de soulignement en séparateur
        $flip = $separator == '-' ? '_' : '-';

        $title = preg_replace('!['.preg_quote($flip).']+!u', $separator, $title);

        // Supprime tous les caractères autres que le séparateur, les lettres, les chiffres ou les espaces.
        $title = preg_replace('![^'.preg_quote($separator).'\pL\pN\s]+!u', '', mb_strtolower($title));

        // Remplace tous les caractères de séparation et les espaces blancs par un seul séparateur
        $title = preg_replace('!['.preg_quote($separator).'\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Convertir une chaîne en cas de serpent.
     *
     * @param  string  $value
     * @param  string  $delimiter
     * @return string
     */
    public static function snake($value, $delimiter = '_')
    {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (! ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', $value);
            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1'.$delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Détermine si une chaîne donnée commence par une sous-chaîne donnée.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    public static function startsWith($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if (($needle != '') && (substr($haystack, 0, strlen($needle)) === (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convertir une valeur en cas de majuscules studly.
     *
     * @param  string  $value
     * @return string
     */
    public static function studly($value)
    {
        $key = $value;

        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$key] = str_replace(' ', '', $value);
    }

    /**
     * Renvoie la portion de chaîne spécifiée par les paramètres de début et de longueur.
     *
     * @param  string  $string
     * @param  int  $start
     * @param  int|null  $length
     * @return string
     */
    public static function substr($string, $start, $length = null)
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Mettez le premier caractère d'une chaîne en majuscule.
     *
     * @param  string  $string
     * @return string
     */
    public static function ucfirst($string)
    {
        return static::upper(static::substr($string, 0, 1)) .static::substr($string, 1);
    }

}
