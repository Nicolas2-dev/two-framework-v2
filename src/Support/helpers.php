<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */

use Two\Support\Arr;
use Two\Support\Str;
use Two\Bus\Dispatcher;
use Two\View\Expression;
use Two\Collection\Collection;
use Two\Support\Debug\Dumper;
use Two\Broadcasting\BroadcastManager;


//--------------------------------------------------------------------------
// Fonction helpers Url.
//--------------------------------------------------------------------------

if (! function_exists('url'))
{
    /**
     * Générez une URL pour l'application.
     *
     * @param  string  $path
     * @param  mixed   $parameters
     * @param  bool    $secure
     * @return string
     */
    function url($path = null, $parameters = array(), $secure = null)
    {
        return app('url')->to($path, $parameters, $secure);
    }
}

if (! function_exists('site_url'))
{
    /**
     * Assistant d'URL de site
     *
     * @return string
     */
    function site_url()
    {
        if (empty($parameters = func_get_args())) {
            return url('/');
        }

        $path = array_shift($parameters);

        $result = preg_replace_callback('#\{(\d+)\}#', function ($matches) use ($parameters)
        {
            list ($value, $key) = $matches;

            return isset($parameters[$key]) ? $parameters[$key] : $value;

        }, $path);

        return url($result);
    }
}

if (! function_exists('asset_url'))
{
    /**
     * Assistant d'URL d'élément
     *
     * @param string $path
     * @param string|null $package
     * @return string
     */
    function asset_url($path, $package = null)
    {
        $path = ltrim($path, '/');

        if (is_null($package)) {
            return url('assets/' .$path);
        }

        $path = sprintf('packages/%s/%s', str_replace('_', '-', $package), $path);

        return url($path);
    }
}

if (! function_exists('vendor_url'))
{
    /**
     * Assistant d'URL de fournisseur
     *
     * @param string $path
     * @param string $vendor
     * @return string
     */
    function vendor_url($path, $vendor)
    {
        $path = sprintf('vendor/%s/%s', $vendor, $path);

        return url($path);
    }
}

if (! function_exists('shared_url'))
{
    /**
     * Assistant d'URL de fournisseur
     *
     * @param string $path
     * @param string $vendor
     * @return string
     */
    function shared_url($path, $vendor)
    {
        $path = sprintf('shared/%s/%s', $vendor, $path);

        return url($path);
    }
}

if (! function_exists('action'))
{
    /**
     * Générez une URL vers une action de contrôleur.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @return string
     */
    function action($name, $parameters = array())
    {
        return app('url')->action($name, $parameters);
    }
}

if (! function_exists('route'))
{
    /**
     * Générez une URL vers une route nommée.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @param  bool  $absolute
     * @param  \Two\Routing\Route $route
     * @return string
     */
    function route($name, $parameters = array(), $absolute = true, $route = null)
    {
        return app('url')->route($name, $parameters, $absolute, $route);
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Field.
//--------------------------------------------------------------------------

if (! function_exists('method_field')) {
    /**
     * Générez un champ de formulaire pour usurper le verbe HTTP utilisé par les formulaires.
     *
     * @param  string  $method
     * @return \Two\View\Expression
     */
    function method_field($method)
    {
        return new Expression('<input type="hidden" name="_method" value="' .$method .'">');
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Language.
//--------------------------------------------------------------------------

if (! function_exists('__'))
{
    /**
     * Récupérez le message formaté et traduit.
     *
     * @param string $message English default message
     * @param mixed $args
     * @return string|void
     */
    function __($message, $args = null)
    {
        if (! $message) return '';

        //
        $params = (func_num_args() === 2) ? (array)$args : array_slice(func_get_args(), 1);

        return app('language')->instance('app')->translate($message, $params);
    }
}

if (! function_exists('__d'))
{
    /**
     * Récupérez le message formaté et traduit avec Domain.
     *
     * @param string $domain
     * @param string $message
     * @param mixed $args
     * @return string|void
     */
    function __d($domain, $message, $args = null)
    {
        if (! $message) return '';

        //
        $params = (func_num_args() === 3) ? (array)$args : array_slice(func_get_args(), 2);

        return app('language')->instance($domain)->translate($message, $params);
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Application.
//--------------------------------------------------------------------------

if (! function_exists('app'))
{
    /**
     * Obtenez l'instance d'application Facade racine.
     *
     * @param  string  $make
     * @return mixed
     */
    function app($make = null)
    {
        if (! is_null($make))
        {
            return app()->make($make);
        }

        return Two\Support\Facades\Facade::getFacadeApplication();
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Path.
//--------------------------------------------------------------------------


if (! function_exists('app_path'))
{
    /**
     * Obtenez le chemin d'accès au dossier de l'application.
     *
     * @param  string  $path
     * @return string
     */
    function app_path($path = '')
    {
        return app('path') .(! empty($path) ? DS .$path : $path);
    }
}

if (! function_exists('base_path'))
{
        /**
         * Obtenez le chemin d'accès à la base de l'installation.
         *
         * @param  string  $path
         * @return string
         */
        function base_path($path = '')
        {
                return app()->make('path.base') .(! empty($path) ? DS .$path : $path);
        }
}

if (! function_exists('storage_path'))
{
    /**
     * Obtenez le chemin d'accès au dossier de stockage.
     *
     * @param   string  $path
     * @return  string
     */
    function storage_path($path = '')
    {
        return app('path.storage') .(! empty($path) ? DS .$path : $path);
    }
}

if (! function_exists('public_path'))
{
    /**
     * Obtenez le chemin d'accès au dossier public.
     *
     * @param  string  $path
     * @return string
     */
    function public_path($path = '')
    {
        return app()->make('path.public') .(! empty($path) ? DS .$path : $path);
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Arr.
//--------------------------------------------------------------------------

if (! function_exists('array_add'))
{
    /**
     * Ajoutez un élément à un tableau en utilisant la notation "point" s'il n'existe pas.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $value
     * @return array
     */
    function array_add($array, $key, $value)
    {
        return Arr::add($array, $key, $value);
    }
}

if (! function_exists('array_build'))
{
    /**
     * Construisez un nouveau tableau à l'aide d'un rappel.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    function array_build($array, Closure $callback)
    {
        return Arr::build($array, $callback);
    }
}

if (! function_exists('array_divide'))
{
    /**
     * Diviser un tableau en deux tableaux. L'un avec des clés et l'autre avec des valeurs.
     *
     * @param  array  $array
     * @return array
     */
    function array_divide($array)
    {
        return Arr::divide($array);
    }
}

if (! function_exists('array_dot'))
{
    /**
     * Aplatir un tableau associatif multidimensionnel avec des points.
     *
     * @param  array   $array
     * @param  string  $prepend
     * @return array
     */
    function array_dot($array, $prepend = '')
    {
        return Arr::dot($array, $prepend);
    }
}

if (! function_exists('array_except'))
{
    /**
     * Obtient tout le tableau donné à l'exception d'un tableau d'éléments spécifié.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    function array_except($array, $keys)
    {
        return Arr::except($array, $keys);
    }
}

if (! function_exists('array_fetch'))
{
    /**
     * Récupère un tableau aplati d'un élément de tableau imbriqué.
     *
     * @param  array   $array
     * @param  string  $key
     * @return array
     */
    function array_fetch($array, $key)
    {
        return Arr::fetch($array, $key);
    }
}

if (! function_exists('array_first'))
{
    /**
     * Renvoie le premier élément d'un tableau réussissant un test de vérité donné.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @param  mixed     $default
     * @return mixed
     */
    function array_first($array, $callback, $default = null)
    {
        return Arr::first($array, $callback, $default);
    }
}

if (! function_exists('array_last'))
{
    /**
     * Renvoie le dernier élément d'un tableau réussissant un test de vérité donné.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @param  mixed     $default
     * @return mixed
     */
    function array_last($array, $callback, $default = null)
    {
        return Arr::last($array, $callback, $default);
    }
}

if (! function_exists('array_flatten'))
{
    /**
     * Aplatir un tableau multidimensionnel en un seul niveau.
     *
     * @param  array  $array
     * @return array
     */
    function array_flatten($array)
    {
        return Arr::flatten($array);
    }
}

if (! function_exists('array_forget'))
{
    /**
     * Supprimez un ou plusieurs éléments de tableau d'un tableau donné en utilisant 
     * la notation "point".
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return void
     */
    function array_forget(&$array, $keys)
    {
        return Arr::forget($array, $keys);
    }
}

if (! function_exists('array_get'))
{
    /**
     * Récupère un élément d'un tableau en utilisant la notation "point".
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function array_get($array, $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
}

if (! function_exists('array_has'))
{
    /**
     * Vérifiez si un élément existe dans un tableau en utilisant la notation "point".
     *
     * @param  array   $array
     * @param  string  $key
     * @return bool
     */
    function array_has($array, $key)
    {
        return Arr::has($array, $key);
    }
}

if (! function_exists('array_only'))
{
    /**
     * Récupère un sous-ensemble des éléments du tableau donné.
     *
     * @param  array  $array
     * @param  array|string  $keys
     * @return array
     */
    function array_only($array, $keys)
    {
        return Arr::only($array, $keys);
    }
}

if (! function_exists('array_pluck'))
{
    /**
     * Extraire un tableau de valeurs d'un tableau.
     *
     * @param  array   $array
     * @param  string  $value
     * @param  string  $key
     * @return array
     */
    function array_pluck($array, $value, $key = null)
    {
        return Arr::pluck($array, $value, $key);
    }
}

if (! function_exists('array_pull'))
{
    /**
     * Obtenez une valeur du tableau et supprimez-la.
     *
     * @param  array   $array
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function array_pull(&$array, $key, $default = null)
    {
        return Arr::pull($array, $key, $default);
    }
}

if (! function_exists('array_set'))
{
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
    function array_set(&$array, $key, $value)
    {
        return Arr::set($array, $key, $value);
    }
}

if (! function_exists('array_sort'))
{
    /**
     * Triez le tableau en utilisant la Closure donnée.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    function array_sort($array, Closure $callback)
    {
        return Arr::sort($array, $callback);
    }
}

if (! function_exists('array_where'))
{
    /**
     * Filtrez le tableau en utilisant la Closure donnée.
     *
     * @param  array     $array
     * @param  \Closure  $callback
     * @return array
     */
    function array_where($array, Closure $callback)
    {
        return Arr::where($array, $callback);
    }
}

if (! function_exists('head'))
{
    /**
     * Récupère le premier élément d'un tableau. Utile pour le chaînage de méthodes.
     *
     * @param  array  $array
     * @return mixed
     */
    function head($array)
    {
        return reset($array);
    }
}

if (! function_exists('last'))
{
    /**
     * Récupère le dernier élément d'un tableau.
     *
     * @param  array  $array
     * @return mixed
     */
    function last($array)
    {
        return end($array);
    }
}

if (! function_exists('data_get'))
{
    /**
     * Récupère un élément d'un tableau ou d'un objet en utilisant la notation "point".
     *
     * @param  mixed   $target
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) return $target;

        foreach (explode('.', $key) as $segment) {
            if (is_array($target)) {
                if (! array_key_exists($segment, $target)) {
                    return value($default);
                }

                $target = $target[$segment];
            } else if (is_object($target)) {
                if (! isset($target->{$segment})) {
                    return value($default);
                }

                $target = $target->{$segment};
            } else {
                return value($default);
            }
        }

        return $target;
    }
}

//--------------------------------------------------------------------------
// Fonction helpers Str.
//--------------------------------------------------------------------------

if (! function_exists('camel_case'))
{
    /**
     * Convertir une valeur en camel case.
     *
     * @param  string  $value
     * @return string
     */
    function camel_case($value)
    {
        return Str::camel($value);
    }
}

if (! function_exists('str_is'))
{
    /**
     * Détermine si une chaîne donnée correspond à un modèle donné.
     *
     * @param  string  $pattern
     * @param  string  $value
     * @return bool
     */
    function str_is($pattern, $value)
    {
        return Str::is($pattern, $value);
    }
}

if (! function_exists('secure_url'))
{
    /**
     * Générez une URL HTTPS pour l'application.
     *
     * @param  string  $path
     * @param  mixed   $parameters
     * @return string
     */
    function secure_url($path, $parameters = array())
    {
        return url($path, $parameters, true);
    }
}

if (! function_exists('snake_case'))
{
    /**
     * Convertir une chaîne en cas de serpent.
     *
     * @param  string  $value
     * @param  string  $delimiter
     * @return string
     */
    function snake_case($value, $delimiter = '_')
    {
        return Str::snake($value, $delimiter);
    }
}

if (! function_exists('starts_with'))
{
    /**
     * Détermine si une chaîne donnée commence par une sous-chaîne donnée.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    function starts_with($haystack, $needles)
    {
        return Str::startsWith($haystack, $needles);
    }
}

if (! function_exists('str_contains'))
{
    /**
     * Détermine si une chaîne donnée contient une sous-chaîne donnée.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    function str_contains($haystack, $needles)
    {
        return Str::contains($haystack, $needles);
    }
}

if (! function_exists('str_finish'))
{
    /**
     * Cap une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param  string  $value
     * @param  string  $cap
     * @return string
     */
    function str_finish($value, $cap)
    {
        return Str::finish($value, $cap);
    }
}

if (! function_exists('str_limit'))
{
    /**
     * Limitez le nombre de caractères dans une chaîne.
     *
     * @param  string  $value
     * @param  int     $limit
     * @param  string  $end
     * @return string
     */
    function str_limit($value, $limit = 100, $end = '...')
    {
        return Str::limit($value, $limit, $end);
    }
}

if (! function_exists('str_plural'))
{
    /**
     * Obtenir la forme plurielle d'un mot anglais.
     *
     * @param  string  $value
     * @param  int     $count
     * @return string
     */
    function str_plural($value, $count = 2)
    {
        return Str::plural($value, $count);
    }
}

if (! function_exists('str_random'))
{
    /**
     * Génère une chaîne alphanumérique plus "aléatoire".
     *
     * @param  int  $length
     * @return string
     *
     * @throws \RuntimeException
     */
    function str_random($length = 16)
    {
        return Str::random($length);
    }
}

if (! function_exists('str_replace_array'))
{
    /**
     * Remplacez séquentiellement une valeur donnée dans la chaîne par un tableau.
     *
     * @param  string  $search
     * @param  array   $replace
     * @param  string  $subject
     * @return string
     */
    function str_replace_array($search, array $replace, $subject)
    {
        foreach ($replace as $value)
        {
            $subject = preg_replace('/'.$search.'/', $value, $subject, 1);
        }

        return $subject;
    }
}

if (! function_exists('str_singular'))
{
    /**
     * Obtenir la forme singulière d'un mot anglais.
     *
     * @param  string  $value
     * @return string
     */
    function str_singular($value)
    {
        return Str::singular($value);
    }
}

if (! function_exists('studly_case'))
{
    /**
     * Convertir une valeur en cas de majuscules studly.
     *
     * @param  string  $value
     * @return string
     */
    function studly_case($value)
    {
        return Str::studly($value);
    }
}

if (! function_exists('ends_with'))
{
    /**
     * Détermine si une chaîne donnée se termine par une sous-chaîne donnée.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    function ends_with($haystack, $needles)
    {
        return Str::endsWith($haystack, $needles);
    }
}

//--------------------------------------------------------------------------
// Fonction Csrf.
//--------------------------------------------------------------------------

if (! function_exists('csrf_field')) {
    /**
     * Générez un champ de formulaire de jeton CSRF.
     *
     * @return string
     */
    function csrf_field()
    {
        return new Expression('<input type="hidden" name="_token" value="' .csrf_token() .'" />');
    }
}

if (! function_exists('csrf_token'))
{
    /**
     * Obtenez la valeur du jeton CSRF.
     *
     * @return string
     *
     * @throws RuntimeException
     */
    function csrf_token()
    {
        $session = app('session');

        if (isset($session)) {
            return $session->token();
        }

        throw new RuntimeException("Application session store not set.");
    }
}

//--------------------------------------------------------------------------
// Fonction Cookie.
//--------------------------------------------------------------------------

if (! function_exists('cookie')) {
    /**
     * Créez une nouvelle instance de cookie.
     *
     * @param  string  $name
     * @param  string  $value
     * @param  int     $minutes
     * @param  string  $path
     * @param  string  $domain
     * @param  bool    $secure
     * @param  bool    $httpOnly
     *
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    function cookie($name = null, $value = null, $minutes = 0, $path = null, $domain = null, $secure = false, $httpOnly = true)
    {
        $cookie = app('cookie');

        if (is_null($name)) {
            return $cookie;
        }

        return $cookie->make($name, $value, $minutes, $path, $domain, $secure, $httpOnly);
    }
}

//--------------------------------------------------------------------------
// Fonction Config.
//--------------------------------------------------------------------------

if (! function_exists('append_config'))
{
    /**
     * Attribuez des ID numériques élevés à un élément de configuration pour forcer l'ajout.
     *
     * @param  array  $array
     * @return array
     */
    function append_config(array $array)
    {
        $start = 9999;

        foreach ($array as $key => $value) {
            if (is_numeric($key)) {
                $start++;

                $array[$start] = array_pull($array, $key);
            }
        }

        return $array;
    }
}

if (! function_exists('config')) {
    /**
     * Obtenir/définir la valeur de configuration spécifiée.
     *
     * Si un tableau est passé comme clé, nous supposerons que vous voulez définir un 
     * tableau de valeurs.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('config');
        } else if (is_array($key)) {
            return app('config')->set($key);
        }

        return app('config')->get($key, $default);
    }
}

//--------------------------------------------------------------------------
// Fonction Crypt.
//--------------------------------------------------------------------------

if (! function_exists('encrypt'))
{
    /**
     * Crypte la valeur donnée.
     *
     * @param  string  $value
     * @return string
     */
    function encrypt($value)
    {
        return app('encrypter')->encrypt($value);
    }
}

if (! function_exists('decrypt'))
{
    /**
     * Déchiffrer la valeur donnée.
     *
     * @param  string  $value
     * @return string
     */
    function decrypt($value)
    {
        return app('encrypter')->decrypt($value);
    }
}

//--------------------------------------------------------------------------
// Fonction Hach.
//--------------------------------------------------------------------------


if (! function_exists('bcrypt'))
{
    /**
     * Hachez la valeur donnée.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    function bcrypt($value, $options = array())
    {
        return app('hash')->make($value, $options);
    }
}

//--------------------------------------------------------------------------
// Fonction Assets.
//--------------------------------------------------------------------------

if (! function_exists('asset'))
{
    /**
     * Générez un chemin d'accès à l'actif pour l'application.
     *
     * @param  string  $path
     * @param  bool    $secure
     * @return string
     */
    function asset($path, $secure = null)
    {
        return app('url')->asset($path, $secure);
    }
}

if (! function_exists('secure_asset'))
{
    /**
     * Générer un chemin d'accès sécuriser à l'actif pour l'application.
     *
     * @param  string  $path
     * @return string
     */
    function secure_asset($path)
    {
        return asset($path, true);
    }
}

//--------------------------------------------------------------------------
// Fonction Link.
//--------------------------------------------------------------------------

if (! function_exists('link_to'))
{
    /**
     * Générez un lien HTML.
     *
     * @param  string  $url
     * @param  string  $title
     * @param  array   $attributes
     * @param  bool    $secure
     * @return string
     */
    function link_to($url, $title = null, $attributes = array(), $secure = null)
    {
        return app('html')->link($url, $title, $attributes, $secure);
    }
}

if (! function_exists('link_to_asset'))
{
    /**
     * Générez un lien HTML vers un élément.
     *
     * @param  string  $url
     * @param  string  $title
     * @param  array   $attributes
     * @param  bool    $secure
     * @return string
     */
    function link_to_asset($url, $title = null, $attributes = array(), $secure = null)
    {
        return app('html')->linkAsset($url, $title, $attributes, $secure);
    }
}

if (! function_exists('link_to_route'))
{
    /**
     * Générer un lien HTML vers une route nommée.
     *
     * @param  string  $name
     * @param  string  $title
     * @param  array   $parameters
     * @param  array   $attributes
     * @return string
     */
    function link_to_route($name, $title = null, $parameters = array(), $attributes = array())
    {
        return app('html')->linkRoute($name, $title, $parameters, $attributes);
    }
}

if (! function_exists('link_to_action'))
{
    /**
     * Génère un lien HTML vers une action du contrôleur.
     *
     * @param  string  $action
     * @param  string  $title
     * @param  array   $parameters
     * @param  array   $attributes
     * @return string
     */
    function link_to_action($action, $title = null, $parameters = array(), $attributes = array())
    {
        return app('html')->linkAction($action, $title, $parameters, $attributes);
    }
}

//--------------------------------------------------------------------------
// Fonction Event.
//--------------------------------------------------------------------------

if (! function_exists('event')) {
    /**
     * Déclenchez un événement et appelez les écouteurs.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    function event($event, $payload = array(), $halt = false)
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}

if (! function_exists('broadcast'))
{
    /**
     * Commencez à diffuser un événement.
     *
     * @param  mixed|null  $event
     * @return \Two\Broadcasting\PendingBroadcast|void
     */
    function broadcast($event = null)
    {
        return app(BroadcastManager::class)->event($event);
    }
}

//--------------------------------------------------------------------------
// Fonction Dispatch.
//--------------------------------------------------------------------------

if (! function_exists('dispatch'))
{
    /**
     * Distribuez un travail à son gestionnaire approprié.
     *
     * @param  mixed  $job
     * @return mixed
     */
    function dispatch($job)
    {
        return app(Dispatcher::class)->dispatch($job);
    }
}

//--------------------------------------------------------------------------
// Fonction Class.
//--------------------------------------------------------------------------

if (! function_exists('class_basename'))
{
    /**
     * Récupère la classe "basename" de l'objet/classe donné.
     *
     * @param  string|object  $class
     * @return string
     */
    function class_basename($class)
    {
        $className = is_object($class) ? get_class($class) : $class;

        return basename(
            str_replace('\\', '/', $className)
        );
    }
}

if (! function_exists('class_uses_recursive'))
{
    /**
     * Renvoie tous les traits utilisés par une classe, ses sous-classes et le trait de leurs traits
     *
     * @param  string  $class
     * @return array
     */
    function class_uses_recursive($class)
    {
        $results = array();

        foreach (array_merge(array($class => $class), class_parents($class)) as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

//--------------------------------------------------------------------------
// Fonction Collection.
//--------------------------------------------------------------------------

if (! function_exists('collect'))
{
    /**
     * Créez une collection à partir de la valeur donnée.
     *
     * @param  mixed  $value
     * @return \Two\Collection\Collection
     */
    function collect($value = null)
    {
        return Collection::make($value);
    }
}

//--------------------------------------------------------------------------
// Fonction Html.
//--------------------------------------------------------------------------

if (! function_exists('e'))
{
    /**
     * Échappez les entités HTML dans une chaîne.
     *
     * @param  string  $value
     * @return string
     */
    function e($value)
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }
}

if (! function_exists('preg_replace_sub'))
{
    /**
     * Remplacez un motif donné par chaque valeur du tableau de manière séquentielle.
     *
     * @param  string  $pattern
     * @param  array   $replacements
     * @param  string  $subject
     * @return string
     */
    function preg_replace_sub($pattern, &$replacements, $subject)
    {
        return preg_replace_callback($pattern, function($match) use (&$replacements)
        {
            return array_shift($replacements);

        }, $subject);
    }
}

//--------------------------------------------------------------------------
// Fonction Environment.
//--------------------------------------------------------------------------

if (! function_exists('env')) {
    /**
     * Obtient la valeur d'une variable d'environnement. Prend en charge les valeurs booléennes, 
     * vides et nulles.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return value($default);
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'empty':
            case '(empty)':
                return '';

            case 'null':
            case '(null)':
                return null;
        }

        if ((strlen($value) > 1) && Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (! function_exists('windows_os')) {
    /**
     * Déterminez si l'environnement actuel est basé sur Windows.
     *
     * @return bool
     */
    function windows_os()
    {
        return strtolower(substr(PHP_OS, 0, 3)) === 'win';
    }
}

//--------------------------------------------------------------------------
// Fonction Divers.
//--------------------------------------------------------------------------

if (! function_exists('object_get'))
{
    /**
     * Obtenir un élément d'un objet en utilisant la notation "point".
     *
     * @param  object  $object
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function object_get($object, $key, $default = null)
    {
        if (is_null($key) || trim($key) == '') return $object;

        foreach (explode('.', $key) as $segment) {
            if (! is_object($object) || ! isset($object->{$segment})) {
                return value($default);
            }

            $object = $object->{$segment};
        }

        return $object;
    }
}

if (! function_exists('trait_uses_recursive'))
{
    /**
     * Renvoie tous les traits utilisés par un trait et ses traits
     *
     * @param  string  $trait
     * @return array
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait);

        foreach ($traits as $trait)
        {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (! function_exists('value'))
{
    /**
     * Renvoie la valeur par défaut de la valeur donnée.
     *
     * @param  mixed  $value
     * @return mixed
     */
    function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}

if (! function_exists('with'))
{
    /**
     * Renvoie l'objet donné. Utile pour enchaîner.
     *
     * @param  mixed  $object
     * @return mixed
     */
    function with($object)
    {
        return $object;
    }
}

//--------------------------------------------------------------------------
// Fonction Debug.
//--------------------------------------------------------------------------

if (! function_exists('dd'))
{
    /**
     * Videz les variables transmises et terminez le script.
     *
     * @param  mixed
     * @return void
     */
    function dd()
    {
        array_map(function ($value)
        {
            with(new Dumper)->dump($value);

        }, func_get_args());

        die (1);
    }
}


if (! function_exists('vd'))
{
    /**
     * Videz les variables transmises et continue le script.
     *
     * @param  mixed
     * @return void
     */
    function vd()
    {
        array_map(function ($value)
        {
            with(new Dumper)->dump($value);

        }, func_get_args());
    }
}