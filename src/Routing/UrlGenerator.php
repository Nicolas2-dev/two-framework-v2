<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing;

use InvalidArgumentException;

use Two\Http\Request;
use Two\Support\Arr;
use Two\Support\Str;


class UrlGenerator
{
    /**
     * La collection d'itinéraires.
     *
     * @var \Two\Routing\RouteCollection
     */
    protected $routes;

    /**
     * L’instance de requête.
     *
     * @var \Two\Http\Request
     */
    protected $request;

    /**
     * La racine de l’URL de force.
     *
     * @var string
     */
    protected $forcedRoot;

    /**
     * Le schéma forcé pour les URL.
     *
     * @var string
     */
    protected $forceSchema;

    /**
     * Caractères qui ne doivent pas être codés en URL.
     *
     * @var array
     */
    protected $dontEncode = array(
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',
    );

    protected $sessionResolver;

    /**
     * Créez une nouvelle instance de générateur d'URL.
     *
     * @param  \Two\Routing\RouteCollection  $routes
     * @param  \Symfony\Component\HttpFoundation\Request   $request
     * @return void
     */
    public function __construct(RouteCollection $routes, Request $request)
    {
        $this->routes = $routes;

        $this->setRequest($request);
    }

    /**
     * Obtenez l'URL complète de la demande en cours.
     *
     * @return string
     */
    public function full()
    {
        return $this->request->fullUrl();
    }

    /**
     * Obtenez l'URL actuelle de la demande.
     *
     * @return string
     */
    public function current()
    {
        return $this->to($this->request->getPathInfo());
    }

    /**
     * Obtenez l'URL de la demande précédente.
     *
     * @return string
     */
    public function previous()
    {
        $referrer = $this->request->headers->get('referer');

        $url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

        return $url ?: $this->to('/');
    }

    /**
     * Générez une URL absolue vers le chemin donné.
     *
     * @param  string  $path
     * @param  mixed  $extra
     * @param  bool|null  $secure
     * @return string
     */
    public function to($path, $extra = array(), $secure = null)
    {
        // Nous allons d’abord vérifier si l’URL est déjà une URL valide. Si c'est le cas, nous ne le ferons pas
        // essaie d'en générer une nouvelle mais renverra simplement l'URL telle quelle, ce qui est
        // pratique puisque les développeurs n'ont pas toujours besoin de vérifier si c'est valide.
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $scheme = $this->getScheme($secure);

        $tail = implode('/', array_map('rawurlencode', (array) $extra));

        // Une fois que nous aurons le schéma, nous compilerons la « queue » en réduisant les valeurs
        // en une seule chaîne délimitée par des barres obliques. Cela rend les choses plus pratiques
        // pour transmettre le tableau de paramètres à cette URL sous forme de liste de segments.

        $root = $this->getRootUrl($scheme);

        return $this->trimUrl($root, $path, $tail);
    }

    /**
     * Générez une URL sécurisée et absolue vers le chemin donné.
     *
     * @param  string  $path
     * @param  array   $parameters
     * @return string
     */
    public function secure($path, $parameters = array())
    {
        return $this->to($path, $parameters, true);
    }

    /**
     * Générez une URL vers un actif d'application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    public function asset($path, $secure = null)
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        // Une fois que nous aurons obtenu l’URL racine, nous vérifierons si elle contient un index.php
        // fichier dans les chemins. Si c'est le cas, nous le supprimerons car il n'est pas nécessaire
        // pour les chemins d'accès aux ressources, mais uniquement pour les routes vers les points de terminaison dans l'application.
        $root = $this->getRootUrl($this->getScheme($secure));

        return $this->removeIndex($root) .'/' .trim($path, '/');
    }

    /**
     * Supprimez le fichier index.php d'un chemin.
     *
     * @param  string  $root
     * @return string
     */
    protected function removeIndex($root)
    {
        $index = 'index.php';

        return Str::contains($root, $index) ? str_replace('/' .$index, '', $root) : $root;
    }

    /**
     * Générez une URL vers un actif sécurisé.
     *
     * @param  string  $path
     * @return string
     */
    public function secureAsset($path)
    {
        return $this->asset($path, true);
    }

    /**
     * Obtenez le schéma d'une URL brute.
     *
     * @param  bool|null  $secure
     * @return string
     */
    protected function getScheme($secure)
    {
        if (is_null($secure)) {
            return $this->forceSchema ?: $this->request->getScheme() .'://';
        }

        return $secure ? 'https://' : 'http://';
    }

    /**
     * Forcez le schéma pour les URL.
     *
     * @param  string  $schema
     * @return void
     */
    public function forceSchema($schema)
    {
        $this->forceSchema = $schema .'://';
    }

    /**
     * Obtenez l'URL d'une route nommée.
     *
     * @param  string  $name
     * @param  mixed   $parameters
     * @param  bool  $absolute
     * @param  \Two\Routing\Route  $route
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function route($name, $parameters = array(), $absolute = true, $route = null)
    {
        $route = $route ?: $this->routes->getByName($name);

        $parameters = (array) $parameters;

        if (! is_null($route)) {
            return $this->toRoute($route, $parameters, $absolute);
        }

        throw new InvalidArgumentException("Route [{$name}] not defined.");
    }

    /**
     * Obtenez l'URL d'une instance de route donnée.
     *
     * @param  \Two\Routing\Route  $route
     * @param  array  $parameters
     * @param  bool  $absolute
     * @return string
     */
    protected function toRoute($route, array $parameters, $absolute)
    {
        $domain = $this->getRouteDomain($route, $parameters);

        $root = $this->replaceRoot($route, $domain, $parameters);

        $uri = strtr(rawurlencode($this->trimUrl(
            $root, $this->replaceRouteParameters($route->uri(), $parameters)

        )), $this->dontEncode) .$this->getRouteQueryString($parameters);

        return $absolute ? $uri : '/' .ltrim(str_replace($root, '', $uri), '/');
    }

    /**
     * Remplacez les paramètres sur le chemin racine.
     *
     * @param  \Two\Routing\Route  $route
     * @param  string  $domain
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRoot($route, $domain, &$parameters)
    {
        return $this->replaceRouteParameters($this->getRouteRoot($route, $domain), $parameters);
    }

    /**
     * Remplacez tous les paramètres génériques d’un chemin d’itinéraire.
     *
     * @param  string  $path
     * @param  array  $parameters
     * @return string
     */
    protected function replaceRouteParameters($path, array &$parameters)
    {
        if (count($parameters) > 0) {
            $path = preg_replace_sub(
                '/\{.*?\}/', $parameters, $this->replaceNamedParameters($path, $parameters)
            );
        }

        return trim(preg_replace('/\{.*?\?\}/', '', $path), '/');
    }

    /**
     * Remplacez tous les paramètres nommés dans le chemin.
     *
     * @param  string  $path
     * @param  array  $parameters
     * @return string
     */
    protected function replaceNamedParameters($path, &$parameters)
    {
        return preg_replace_callback('/\{(.*?)\??\}/', function ($match) use (&$parameters)
        {
            return isset($parameters[$match[1]]) ? Arr::pull($parameters, $match[1]) : $match[0];

        }, $path);
    }

    /**
     * Obtenez la chaîne de requête pour un itinéraire donné.
     *
     * @param  array  $parameters
     * @return string
     */
    protected function getRouteQueryString(array $parameters)
    {
        // Nous obtiendrons d’abord tous les paramètres de chaîne qui restent après avoir
        // ont remplacé les caractères génériques de route. Nous allons ensuite construire une chaîne de requête à partir de
        // ces paramètres de chaîne l'utilisent ensuite comme point de départ pour le reste.
        if (count($parameters) == 0) {
            return '';
        }

        $query = http_build_query(
            $keyed = $this->getStringParameters($parameters)
        );

        // Enfin, s'il reste encore des paramètres, nous récupérerons les valeurs numériques
        // les paramètres qui sont dans le tableau et les ajoutons à la chaîne de requête ou nous
        // créera la chaîne de requête initiale si elle n'a pas été démarrée avec des chaînes.
        if (count($keyed) < count($parameters)) {
            $query .= '&' .implode('&', $this->getNumericParameters($parameters));
        }

        return '?' .trim($query, '&');
    }

    /**
     * Récupère les paramètres de chaîne d’une liste donnée.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getStringParameters(array $parameters)
    {
        return Arr::where($parameters, function ($key, $value)
        {
            return is_string($key);
        });
    }

    /**
     * Obtenez les paramètres numériques d’une liste donnée.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getNumericParameters(array $parameters)
    {
        return Arr::where($parameters, function($key, $value)
        {
            return is_numeric($key);
        });
    }

    /**
     * Obtenez le domaine formaté pour un itinéraire donné.
     *
     * @param  \Two\Routing\Route  $route
     * @param  array  $parameters
     * @return string
     */
    protected function getRouteDomain($route, &$parameters)
    {
        return $route->domain() ? $this->formatDomain($route, $parameters) : null;
    }

    /**
     * Formatez le domaine et le port pour l'itinéraire et la demande.
     *
     * @param  \Two\Routing\Route  $route
     * @param  array  $parameters
     * @return string
     */
    protected function formatDomain($route, &$parameters)
    {
        return $this->addPortToDomain($this->getDomainAndScheme($route));
    }

    /**
     * Obtenez le domaine et le schéma de l'itinéraire.
     *
     * @param  \Two\Routing\Route  $route
     * @return string
     */
    protected function getDomainAndScheme($route)
    {
        return $this->getRouteScheme($route).$route->domain();
    }

    /**
     * Ajoutez le port au domaine si nécessaire.
     *
     * @param  string  $domain
     * @return string
     */
    protected function addPortToDomain($domain)
    {
        if (in_array($this->request->getPort(), array('80', '443'))) {
            return $domain;
        }

        return $domain .':' .$this->request->getPort();
    }

    /**
     * Obtenez la racine de l'URL de la route.
     *
     * @param  \Two\Routing\Route  $route
     * @param  string  $domain
     * @return string
     */
    protected function getRouteRoot($route, $domain)
    {
        return $this->getRootUrl($this->getRouteScheme($route), $domain);
    }

    /**
     * Obtenez le schéma pour l'itinéraire donné.
     *
     * @param  \Two\Routing\Route  $route
     * @return string
     */
    protected function getRouteScheme($route)
    {
        if ($route->httpOnly()) {
            return $this->getScheme(false);
        } else if ($route->httpsOnly()) {
            return $this->getScheme(true);
        }

        return $this->getScheme(null);
    }

    /**
     * Obtenez l'URL d'une action du contrôleur.
     *
     * @param  string  $action
     * @param  mixed   $parameters
     * @param  bool    $absolute
     * @return string
     */
    public function action($action, $parameters = array(), $absolute = true)
    {
        return $this->route($action, $parameters, $absolute, $this->routes->getByAction($action));
    }

    /**
     * Obtenez l'URL de base de la demande.
     *
     * @param  string  $scheme
     * @param  string  $root
     * @return string
     */
    protected function getRootUrl($scheme, $root = null)
    {
        if (is_null($root)) {
            $root = $this->forcedRoot ?: $this->request->root();
        }

        $start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

        return preg_replace('~' .$start .'~', $scheme, $root, 1);
    }

    /**
     * Définissez l'URL racine forcée.
     *
     * @param  string  $root
     * @return void
     */
    public function forceRootUrl($root)
    {
        $this->forcedRoot = $root;
    }

    /**
     * Déterminez si le chemin donné est une URL valide.
     *
     * @param  string  $path
     * @return bool
     */
    public function isValidUrl($path)
    {
        if (Str::startsWith($path, array('#', '//', 'mailto:', 'tel:', 'http://', 'https://'))) {
            return true;
        }

        return filter_var($path, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Formatez les segments d'URL donnés en une seule URL.
     *
     * @param  string  $root
     * @param  string  $path
     * @param  string  $tail
     * @return string
     */
    protected function trimUrl($root, $path, $tail = '')
    {
        return trim($root .'/' .trim($path .'/' .$tail, '/'), '/');
    }

    /**
     * Obtenez l’instance de requête.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Définissez l’instance de requête actuelle.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Obtenez l'URL précédente de la session si possible.
     *
     * @return string|null
     */
    protected function getPreviousUrlFromSession()
    {
        $session = $this->getSession();

        return $session ? $session->previousUrl() : null;
    }

    /**
     * Obtenez l'implémentation de la session à partir du résolveur.
     *
     * @return \Two\Session\Store|null
     */
    protected function getSession()
    {
        if (isset($this->sessionResolver)) {
            return call_user_func($this->sessionResolver);
        }
    }

    /**
     * Définissez le résolveur de session pour le générateur.
     *
     * @param  callable  $sessionResolver
     * @return $this
     */
    public function setSessionResolver(callable $sessionResolver)
    {
        $this->sessionResolver = $sessionResolver;

        return $this;
    }
}
