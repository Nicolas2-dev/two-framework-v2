<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use ArrayAccess;
use Closure;
use RuntimeException;
use SplFileInfo;

use Two\Support\Str;
use Two\Support\Arr;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;


class Request extends SymfonyRequest implements ArrayAccess
{

    /**
     * Le contenu JSON décodé pour la requête.
     *
     * @var string
     */
    protected $json;

    /**
     * Tous les fichiers convertis pour la requête.
     *
     * @var array
     */
    protected $convertedFiles;

    /**
     * Le rappel du résolveur utilisateur.
     *
     * @var \Closure
     */
    protected $userResolver;

    /**
     * Le rappel du résolveur de route.
     *
     * @var \Closure
     */
    protected $routeResolver;

    /**
     * L'implémentation du magasin de session Two.
     *
     * @var \Two\Session\Store
     */
    protected $sessionStore;


    /**
     * Renvoyer l'instance Request.
     *
     * @return $this
     */
    public function instance()
    {
        return $this;
    }

    /**
     * Obtenez la méthode de requête.
     *
     * @return string
     */
    public function method()
    {
        return $this->getMethod();
    }

    /**
     * Obtenez l'URL racine de l'application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost().$this->getBaseUrl(), '/');
    }

    /**
     * Obtenez l'URL (pas de chaîne de requête) pour la demande.
     *
     * @return string
     */
    public function url()
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /**
     * Obtenez l'URL complète de la demande.
     *
     * @return string
     */
    public function fullUrl()
    {
        $query = $this->getQueryString();

        return $query ? $this->url().'?'.$query : $this->url();
    }

    /**
     * Obtenez les informations de chemin actuelles pour la demande.
     *
     * @return string
     */
    public function path()
    {
        $pattern = trim($this->getPathInfo(), '/');

        return $pattern == '' ? '/' : $pattern;
    }

    /**
     * Obtenez les informations de chemin encodées actuelles pour la demande.
     *
     * @return string
     */
    public function decodedPath()
    {
        return rawurldecode($this->path());
    }

    /**
     * Obtenir un segment de l'URI (1 index basé).
     *
     * @param  string  $index
     * @param  mixed   $default
     * @return string
     */
    public function segment($index, $default = null)
    {
        return array_get($this->segments(), $index - 1, $default);
    }

    /**
     * Obtenez tous les segments pour le chemin de la requête.
     *
     * @return array
     */
    public function segments()
    {
        $segments = explode('/', $this->path());

        return array_values(array_filter($segments, function($v) { return $v != ''; }));
    }

    /**
     * Déterminez si l'URI de la demande actuelle correspond à un modèle.
     *
     * @param  mixed  string
     * @return bool
     */
    public function is()
    {
        foreach (func_get_args() as $pattern) {
            if (str_is($pattern, urldecode($this->path()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déterminez si la demande est le résultat d'un appel AJAX.
     *
     * @return bool
     */
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * Déterminez si la demande est le résultat d'un appel PJAX.
     *
     * @return bool
     */
    public function pjax()
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /**
     * Déterminez si la demande est via HTTPS.
     *
     * @return bool
     */
    public function secure()
    {
        return $this->isSecure();
    }

    /**
     * Renvoie l'adresse IP du client.
     *
     * @return string
     */
    public function ip()
    {
        return $this->getClientIp();
    }

    /**
     * Renvoie les adresses IP des clients.
     *
     * @return array
     */
    public function ips()
    {
        return $this->getClientIps();
    }

    /**
     * Déterminez si la demande contient une clé d'élément d'entrée donnée.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function exists($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        $input = $this->all();

        foreach ($keys as $value) {
            if (! array_key_exists($value, $input)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Détermine si la requête contient une valeur non vide pour un élément d'entrée.
     *
     * @param  string|array  $key
     * @return bool
     */
    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if ($this->isEmptyString($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Détermine si la clé d'entrée donnée est une chaîne vide pour "has".
     *
     * @param  string  $key
     * @return bool
     */
    protected function isEmptyString($key)
    {
        $boolOrArray = is_bool($this->input($key)) || is_array($this->input($key));

        return ! $boolOrArray && (trim((string) $this->input($key)) === '');
    }

    /**
     * Obtenez toutes les entrées et les fichiers de la demande.
     *
     * @return array
     */
    public function all()
    {
        return array_replace_recursive( (array) $this->input(), $this->allFiles());
    }

    /**
     * Récupérer un élément d'entrée de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    public function input($key = null, $default = null)
    {
        $input = $this->getInputSource()->all() + $this->query->all();

        return array_get($input, $key, $default);
    }

    /**
     * Obtenez un sous-ensemble des éléments à partir des données d'entrée.
     *
     * @param  array  $keys
     * @return array
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = [];

        $input = $this->all();

        foreach ($keys as $key) {
            array_set($results, $key, array_get($input, $key));
        }

        return $results;
    }

    /**
     * Obtenez toutes les entrées à l'exception d'un tableau d'éléments spécifié.
     *
     * @param  array  $keys
     * @return array
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $results = $this->all();

        array_forget($results, $keys);

        return $results;
    }

    /**
     * Récupérer un élément de chaîne de requête à partir de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string|array
     */
    public function query($key = null, $default = null)
    {
        return $this->retrieveItem('query', $key, $default);
    }

    /**
     * Déterminez si un cookie est défini sur la demande.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasCookie($key)
    {
        return ! is_null($this->cookie($key));
    }

    /**
     * Récupérer un cookie de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    public function cookie($key = null, $default = null)
    {
        return $this->retrieveItem('cookies', $key, $default);
    }

    /**
     * Obtenez un tableau de tous les fichiers de la demande.
     *
     * @return array
     */
    public function allFiles()
    {
        $files = $this->files->all();

        if (is_null($this->convertedFiles)) {
            $this->convertedFiles = $this->convertUploadedFiles($files);
        }

        return $this->convertedFiles;
    }

    /**
     * Convertir le tableau donné de Symfony UploadedFiles en Two UploadedFiles personnalisé.
     *
     * @param  array  $files
     * @return array
     */
    protected function convertUploadedFiles(array $files)
    {
        return array_map(function ($file)
        {
            if (is_null($file) || (is_array($file) && empty(array_filter($file)))) {
                return $file;
            }

            if (is_array($file)) {
                return $this->convertUploadedFiles($file);
            }

            return UploadedFile::createFromBase($file);

        }, $files);
    }

    /**
     * Récupérer un fichier à partir de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return \Two\Http\UploadedFile|array
     */
    public function file($key = null, $default = null)
    {
        return array_get($this->allFiles(), $key, $default);
    }

    /**
     * Déterminez si les données téléchargées contiennent un fichier.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasFile($key)
    {
        if (! is_array($files = $this->file($key))) {
            $files = array($files);
        }

        foreach ($files as $file) {
            if ($this->isValidFile($file)) return true;
        }

        return false;
    }

    /**
     * Vérifiez que le fichier donné est une instance de fichier valide.
     *
     * @param  mixed  $file
     * @return bool
     */
    protected function isValidFile($file)
    {
        return ($file instanceof SplFileInfo) && ($file->getPath() != '');
    }

    /**
     * Récupérer un en-tête de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    public function header($key = null, $default = null)
    {
        return $this->retrieveItem('headers', $key, $default);
    }

    /**
     * Récupérer une variable serveur à partir de la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    public function server($key = null, $default = null)
    {
        return $this->retrieveItem('server', $key, $default);
    }

    /**
     * Récupérer un ancien élément d'entrée.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function old($key = null, $default = null)
    {
        return $this->session()->getOldInput($key, $default);
    }

    /**
     * Flashez l'entrée de la requête en cours à la session.
     *
     * @param  string  $filter
     * @param  array   $keys
     * @return void
     */
    public function flash($filter = null, $keys = array())
    {
        $flash = (! is_null($filter)) ? $this->$filter($keys) : $this->input();

        $this->session()->flashInput($flash);
    }

    /**
     * Ne flashez qu'une partie des entrées de la session.
     *
     * @param  mixed  string
     * @return void
     */
    public function flashOnly($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return $this->flash('only', $keys);
    }

    /**
     * Ne flashez qu'une partie des entrées de la session.
     *
     * @param  mixed  string
     * @return void
     */
    public function flashExcept($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return $this->flash('except', $keys);
    }

    /**
     * Videz toutes les anciennes entrées de la session.
     *
     * @return void
     */
    public function flush()
    {
        $this->session()->flashInput(array());
    }

    /**
     * Récupérer un élément de paramètre à partir d'une source donnée.
     *
     * @param  string  $source
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    protected function retrieveItem($source, $key, $default)
    {
        if (is_null($key)) {
            return $this->$source->all();
        }

        return $this->$source->get($key, $default, true);
    }

    /**
     * Fusionner la nouvelle entrée dans le tableau d'entrée de la demande actuelle.
     *
     * @param  array  $input
     * @return void
     */
    public function merge(array $input)
    {
        $this->getInputSource()->add($input);
    }

    /**
     * Remplacer l'entrée de la requête en cours.
     *
     * @param  array  $input
     * @return void
     */
    public function replace(array $input)
    {
        $this->getInputSource()->replace($input);
    }

    /**
     * Obtenez la charge utile JSON pour la requête.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function json($key = null, $default = null)
    {
        if (! isset($this->json))
        {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }

        if (is_null($key)) return $this->json;

        return array_get($this->json->all(), $key, $default);
    }

    /**
     * Obtenez la source d'entrée de la requête.
     *
     * @return \Symfony\Component\HttpFoundation\ParameterBag
     */
    protected function getInputSource()
    {
        if ($this->isJson()) return $this->json();

        return ($this->getMethod() == 'GET') ? $this->query : $this->request;
    }

    /**
     * Déterminez si la requête envoie du JSON.
     *
     * @return bool
     */
    public function isJson()
    {
        return str_contains($this->header('CONTENT_TYPE'), '/json');
    }

    /**
     * Déterminez si la demande actuelle demande JSON en retour.
     *
     * @return bool
     */
    public function wantsJson()
    {
        $acceptable = $this->getAcceptableContentTypes();

        return isset($acceptable[0]) && ($acceptable[0] == 'application/json');
    }

    /**
     * Obtenez le format de données attendu dans la réponse.
     *
     * @param  string  $default
     * @return string
     */
    public function format($default = 'html')
    {
        foreach ($this->getAcceptableContentTypes() as $type) {
            if ($format = $this->getFormat($type)) return $format;
        }

        return $default;
    }

    /**
     * Obtenez le jeton du porteur à partir des en-têtes de requête.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        $header = $this->header('Authorization', '');

        if (Str::startsWith($header, 'Bearer ')) {
            return Str::substr($header, 7);
        }
    }

    /**
     * Créez une requête Two à partir d'une instance Symfony.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Two\Http\Request
     */
    public static function createFromBase(SymfonyRequest $request)
    {
        if ($request instanceof static) return $request;

        $content = $request->content;

        $request = (new static)->duplicate(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all()
        );

        $request->content = $content;

        $request->request = $request->getInputSource();

        return $request;
    }

    /**
     * Obtenez la session associée à la demande.
     *
     * @return \Two\Session\Store
     *
     * @throws \RuntimeException
     */
    public function session()
    {
        if (! $this->hasSession()) {
            throw new \RuntimeException("Session store not set on request.");
        }

        return $this->getSession();
    }

    /**
     * Obtenez l'utilisateur faisant la demande.
     *
     * @return mixed
     */
    public function user()
    {
        return call_user_func($this->getUserResolver());
    }

    /**
     * Obtenez l'itinéraire traitant la demande.
     *
     * @param string|null $param
     *
     * @return \Two\Routing\Route|object|string
     */
    public function route($param = null)
    {
        $route = call_user_func($this->getRouteResolver());

        if (is_null($route) || is_null($param)) {
            return $route;
        } else {
            return $route->parameter($param);
        }
    }

    /**
     * Obtenez une empreinte digitale unique pour la requête / l'itinéraire / l'adresse IP.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public function fingerprint()
    {
        if (is_null($route = $this->route())) {
            throw new RuntimeException('Unable to generate fingerprint. Route unavailable.');
        }

        return sha1(implode('|', array_merge(
            $route->methods(), array($route->domain(), $route->uri(), $this->ip())
        )));
    }

    /**
     * Obtenez le rappel du résolveur d'utilisateur.
     *
     * @return \Closure
     */
    public function getUserResolver()
    {
        return $this->userResolver ?: function()
        {
            //
        };
    }

    /**
     * Définissez le rappel du résolveur utilisateur.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function setUserResolver(Closure $callback)
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * Obtenez le rappel du résolveur de route.
     *
     * @return \Closure
     */
    public function getRouteResolver()
    {
        return $this->routeResolver ?: function()
        {
            //
        };
    }

    /**
     * Définissez le rappel du résolveur de route.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function setRouteResolver(Closure $callback)
    {
        $this->routeResolver = $callback;

        return $this;
    }

    /**
     * Déterminez si le décalage donné existe.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->all());
    }

    /**
     * Obtenir la valeur au décalage donné.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return Arr::get($this->all(), $offset);
    }

    /**
     * Définissez la valeur au décalage donné.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->getInputSource()->set($offset, $value);
    }

    /**
     * Supprimer la valeur au décalage donné.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->getInputSource()->remove($offset);
    }

    /**
     * Vérifiez si un élément d'entrée est défini sur la demande.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return ! is_null($this->__get($key));
    }

    /**
     * Obtenir un élément d'entrée à partir de la requête.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        } else {
            return $this->route($key);
        }
    }
}	