<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Localization;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Localization\Language;
use Two\Application\Two;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Carbon\CarbonInterval;
use Carbon\CarbonImmutable;


class LanguageManager
{
    /**
     * L'instance d'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Paramètres régionaux par défaut utilisés par le traducteur.
     *
     * @var string
     */
    protected $locale;

    /**
     * Les langues connues.
     *
     * @var array
     */
    protected $languages = array();

    /**
     * Les instances de langue actives.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * Tous les indices de chemin nommés.
     *
     * @var array
     */
    protected $hints = array();


    /**
     * Créez une nouvelle instance de Language Manager.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    function __construct(Two $app, $locale)
    {
        $this->app = $app;

        $this->locale = $locale;

        // Configurez les langues connues.
        $this->languages = $app['config']['languages'];

        // Configurez les indications de chemin par défaut.
        $this->hints = array(
            // Espace de noms pour le chemin du Framework.
            'Two' => dirname(__DIR__) .DS .'Language',

            // Espace de noms pour le chemin de l’application.
            'app' => APPPATH .'Language',

            // Espace de noms pour le chemin partagé.
            'shared' => BASEPATH .'shared' .DS .'Language',
        );
    }

    /**
     * Obtenez une instance de Language avec le domaine et le code (facultatif).
     * @param string $domain Domaine personnalisé facultatif
     * @param string $code Code de langue personnalisé en option.
     * @return Language
     */
    public function instance($domain = 'app', $locale = null)
    {
        $locale = $locale ?: $this->locale;

        // Le code d'identification ressemble à : 'en/system', 'en/app' ou 'en/file_manager'
        $id = $locale .'/' .$domain;

        // Renvoie l'instance du domaine Language, si elle existe déjà.
        if (isset($this->instances[$id])) return $this->instances[$id];

        return $this->instances[$id] = new Language($this, $domain, $locale);
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

        $this->addNamespace($namespace, $hint);
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
        if (! is_null($namespace)) {
            return $namespace;
        }

        list ($vendor, $namespace) = explode('/', $package);

        return Str::snake($namespace);
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
        $this->hints[$namespace] = $hint;
    }

    /**
     * Renvoie tous les espaces de noms enregistrés avec le chargeur de configuration.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->hints;
    }

    /**
     * Apprenez à connaître les langues.
     *
     * @return string
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * Obtenez les paramètres régionaux par défaut utilisés.
     *
     * @return string
     */
    public function locale()
    {
        return $this->getLocale();
    }

    /**
     * Obtenez les paramètres régionaux par défaut utilisés.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Définissez les paramètres régionaux par défaut.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        // Configurez les paramètres régionaux du Framework.
        $this->locale = $locale;

        // Configurez les paramètres régionaux Carbon.
        Carbon::setLocale($locale);

        CarbonImmutable::setLocale($locale);
        CarbonPeriod::setLocale($locale);
        CarbonInterval::setLocale($locale);

        // Récupérez les paramètres régionaux qualifiés complets dans la liste des langues.
        $locale = Str::finish(
            Arr::get($this->languages, "{$locale}.locale", 'en_US'), '.utf8'
        );

        // Configurez les paramètres régionaux d'heure de PHP.
        setlocale(LC_TIME, $locale);
    }

    /**
     * Transmettez dynamiquement les méthodes à l’instance par défaut.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->instance(), $method), $parameters);
    }

}
