<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Assets;

use Closure;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Http\Request;
use Two\Http\Response;
use Two\Application\Two;
use Two\Http\JsonResponse;

use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class AssetDispatcher
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Toutes les routes d'actifs enregistrées.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Les chemins de fournisseur valides.
     * 
     * @var array
     */
    protected $paths;

    /**
     * Tous les indices de chemin nommés.
     *
     * @var array
     */
    protected $hints = array();

    /**
     * Les modèles génériques pris en charge par le routeur.
     *
     * @var array
     */
    public static $patterns = array(
        '(:any)' => '([^/]+)',
        '(:num)' => '([0-9]+)',
        '(:hex)' => '([a-fA-F0-9]+)',
        '(:all)' => '(.*)',
    );


    /**
     * Créez une nouvelle instance de répartiteur par défaut.
     *
     * @return void
     */
    public function __construct(Two $app)
    {
        $this->app = $app;
    }

    /**
     * Enregistrez une nouvelle Asset Route auprès du gestionnaire.
     *
     * @param  string  $pattern
     * @param  \Closure  $callback
     * @return void
     */
    public function route($pattern, $callback)
    {
        $this->routes[$pattern] = $callback;
    }

    /**
     * Envoyez une réponse au fichier d’actifs.
     *
     * Pour une diffusion correcte des actifs, l'URI du fichier doit être l'un des éléments suivants :
     *
     * /assets/css/style.css
     * /packages/modules/blog/css/style.css
     *
     * @param  \Two\Http\Request $request
     */
    public function dispatch(Request $request)
    {
        if (! in_array($request->method(), array('GET', 'HEAD', 'OPTIONS'))) {
            return;
        }

        $uri = $request->path();

        foreach ($this->routes as $route => $callback) {
            $pattern = static::compileRoutePattern($route);

            if (preg_match('#^' .$pattern .'$#s', $uri, $matches) === 1) {
                return $this->process($callback, $matches, $request);
            }
        }
    }

    protected static function compileRoutePattern($pattern)
    {
        return str_replace(
            array_keys(static::$patterns), array_values(static::$patterns), $pattern
        );
    }

    /**
     * Exécutez le rappel d'itinéraire donné et traitez la réponse.
     *
     * @param  \Closure  $callback
     * @param  array  $parameters
     * @param  \Two\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\SymfonyResponse
     */
    protected function process($callback, $parameters, Request $request)
    {
        // Nous remplacerons le premier élément (l’URI correspondant) par l’instance Request.
        $parameters[0] = $request; 

        //
        $response = call_user_func_array($callback, $parameters);

        if ($response instanceof SymfonyResponse) {
            return $response;
        }

        // La réponse n'est pas une instance de Symfony Response.
        else if (is_string($response) && ! empty($path = realpath($response))) {
            return $this->serve($path, $request);
        }

        return new Response('File Not Found', 404);
    }

    /**
     * Servir un fichier de package.
     *
     * @param  string  $namespace
     * @param  string  $path
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return  \Symfony\Component\HttpFoundation\Response
     */
    public function servePackageFile($namespace, $path, Request $request)
    {
        if (empty($basePath = $this->getPackagePath($namespace))) {
            return new Response('File Not Found', 404);
        }

        $path = $basePath .DS .str_replace('/', DS, $path);

        return $this->serve($path, $request);
    }

    /**
     * Servir un fichier de fournisseur.
     *
     * @param  string  $path
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return  \Symfony\Component\HttpFoundation\Response
     */
    public function serveVendorFile($path, Request $request)
    {
        $basePath = BASEPATH .'vendor';

        if (! Str::startsWith($path, $this->getVendorPaths())) {
            return new Response('File Not Found', 404);
        }

        $path = $basePath .DS .str_replace('/', DS, $path);

        return $this->serve($path, $request);
    }

    /**
     * Servez un fichier partagé.
     *
     * @param  string  $path
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return  \Symfony\Component\HttpFoundation\Response
     */
    public function serveSharedFile($path, Request $request)
    {
        $basePath = BASEPATH .'shared';

        if (! Str::startsWith($path, $this->getSharedPaths())) {
            return new Response('File Not Found', 404);
        }

        $path = $basePath .DS .str_replace('/', DS, $path);

        return $this->serve($path, $request);
    }

    /**
     * Servir un fichier.
     *
     * @param  string  $path
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  string  $disposition
     * @param  string|null  $fileName
     * @param  bool  $prepared
     *
     * @return  \Symfony\Component\HttpFoundation\Response
     */
    public function serve($path, SymfonyRequest $request, $disposition = 'inline', $fileName = null, $prepared = true)
    {
        if (! file_exists($path = realpath($path))) {
            return new Response('File Not Found', 404);
        } else if (! is_readable($path)) {
            return new Response('Unauthorized Access', 403);
        }

        // Créez une instance de réponse de fichier binaire.
        $headers = array(
            'Access-Control-Allow-Origin' => '*',
        );

        $mimeType = $this->guessMimeType($path);

        if ($request->getMethod() == 'OPTIONS') {
            $headers = array_merge($headers, array(
                'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, X-Auth-Token, Origin',
            ));

            return new Response('OK', 200, $headers);
        }

        // Pas une demande OPTIONS.
        else {
            $headers['Content-Type'] = $mimeType;
        }

        if ($mimeType !== 'application/json') {
            $response = new BinaryFileResponse($path, 200, $headers, true, $disposition, true, false);

            // Définissez la disposition du contenu.
            $response->setContentDisposition($disposition, $fileName ?: basename($path));

            // Configurez le contrôle de cache (navigateur).
            $this->setupCacheControl($response);

            // Configurez le paramètre Non modifié depuis...
            $response->isNotModified($request);
        } else {
            // Nous ferons un traitement spécial pour les fichiers JSON.
            $response = new JsonResponse(
                json_decode(file_get_contents($path), true), 200, $headers
            );
        }

        // Préparez la réponse contre l’instance de requête, si cela est demandé.
        if ($prepared) {
            return $response->prepare($request);
        }

        return $response;
    }

    protected function setupCacheControl(SymfonyResponse $response)
    {
        $options = $this->app['config']->get('routing.assets.cache', array());

        //
        $ttl    = array_get($options, 'ttl', 600);
        $maxAge = array_get($options, 'maxAge', 10800);

        $sharedMaxAge = array_get($options, 'sharedMaxAge', 600);

        //
        $response->setTtl($ttl);
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($sharedMaxAge);
    }

    protected function guessMimeType($path)
    {
        // Même la Fondation HTTP de Symfony a des problèmes avec les fichiers CSS et JS ?
        //
        // Codage en dur des types MIME corrects pour les extensions de fichiers actuellement nécessaires.

        switch ($fileExt = pathinfo($path, PATHINFO_EXTENSION)) {
            case 'css':
                return 'text/css';

            case 'js':
                return 'application/javascript';

            case 'json':
                return 'application/json';

            case 'svg':
                return 'image/svg+xml';

            default:
                break;
        }

        // Devinez le type Mime du chemin.
        $mimeTypes = new MimeTypes();
        return $mimeTypes->guessMimeType($path);

    }

    public function getVendorPaths()
    {
        if (isset($this->paths)) {
            return $this->paths;
        }

        $files = $this->app['files'];

        // Le chemin du fichier cache.
        $path = STORAGE_PATH .'framework' .DS .'assets' .DS .'vendor_paths.php';

        // Le chemin de configuration pour vérifier par rapport au fichier cache.
        $configPath = APPPATH .'Config' .DS .'Routing.php';

        $lastModified = $files->lastModified($configPath);

        if ($files->exists($path) && ! ($lastModified < $files->lastModified($path))) {
            return $this->paths = $files->getRequire($path);
        }

        $paths = array();

        $options = $this->app['config']->get('routing.assets.paths', array());

        foreach ($options as $vendor => $value) {
            $values = is_array($value) ? $value : array($value);

            $values = array_map(function($value) use ($vendor)
            {
                return $vendor .'/' .$value .'/';

            }, $values);

            $paths = array_merge($paths, $values);
        }

        $paths = array_unique($paths);

        // Enregistrez dans le cache.
        $files->makeDirectory(dirname($path), 0755, true, true);

        $content = "<?php\n\nreturn " .var_export($paths, true) .";\n";

        $files->put($path, $content);

        return $this->paths = $paths;
    }

    public function getSharedPaths()
    {
        if (isset($this->paths)) {
            return $this->paths;
        }

        $files = $this->app['files'];

        // Le chemin du fichier cache.
        $path = STORAGE_PATH .'framework' .DS .'assets' .DS .'shared_paths.php';

        // Le chemin de configuration pour vérifier par rapport au fichier cache.
        $configPath = APPPATH .'Config' .DS .'Routing.php';

        $lastModified = $files->lastModified($configPath);

        if ($files->exists($path) && ! ($lastModified < $files->lastModified($path))) {
            return $this->paths = $files->getRequire($path);
        }

        $paths = array();

        $options = $this->app['config']->get('routing.assets.paths', array());

        foreach ($options as $vendor => $value) {
            $values = is_array($value) ? $value : array($value);

            $values = array_map(function($value) use ($vendor)
            {
                return $vendor .'/' .$value .'/';

            }, $values);

            $paths = array_merge($paths, $values);
        }

        $paths = array_unique($paths);

        // Enregistrez dans le cache.
        $files->makeDirectory(dirname($path), 0755, true, true);

        $content = "<?php\n\nreturn " .var_export($paths, true) .";\n";

        $files->put($path, $content);

        return $this->paths = $paths;
    }

    /**
     * Enregistrez un package pour une configuration en cascade.
     *
     * @param  string  $package
     * @param  string  $hint
     * @param  string  $namespace
     * @return void
     */
    public function package($package, $hint, $namespace = null)
    {
        $namespace = $this->getPackageNamespace($package, $namespace);

        $this->addNamespace(str_replace('_', '-', $namespace), $hint);
    }

    /**
     * Renvoie true si l'indicateur d'espace de noms spécifié est présent sur le routeur.
     *
     * @param  string  $namespace
     * @return void
     */
    public function hasNamespace($namespace)
    {
        $namespace = str_replace('_', '-', $namespace);

        return isset($this->hints[$namespace]);
    }

    /**
     * Ajoutez un nouvel espace de noms au chargeur.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $namespace = str_replace('_', '-', $namespace);

        $this->hints[$namespace] = rtrim($hint, DS);
    }

    /**
     * Obtenez l’espace de noms de configuration pour un package.
     *
     * @param  string  $package
     * @param  string  $namespace
     * @return string
     */
    protected function getPackageNamespace($package, $namespace)
    {
        if (is_null($namespace)) {
            list ($vendor, $namespace) = explode('/', $package);

            return Str::snake($namespace);
        }

        return $namespace;
    }

    /**
     * Obtenez le chemin d’un espace de noms enregistré.
     *
     * @param  string  $namespace
     * @return string|null
     */
    public function getPackagePath($namespace)
    {
        $namespace = str_replace('_', '-', $namespace);

        return Arr::get($this->hints, $namespace);
    }

    /**
     * Renvoie tous les espaces de noms enregistrés auprès du routeur.
     *
     * @return array
     */
    public function getHints()
    {
        return $this->hints;
    }
}
