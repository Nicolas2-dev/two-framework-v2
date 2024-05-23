<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Closure;
use Exception;
use ArrayAccess;
use BadMethodCallException;

use Two\Support\Arr;
use Two\Support\Str;
use Two\View\Factory;
use Two\Support\MessageBag;
use Two\View\Contracts\Engines\EngineInterface;
use Two\Application\Contracts\ArrayableInterface as Arrayable;
use Two\Application\Contracts\RenderableInterface as Renderable;
use Two\Application\Contracts\MessageProviderInterface as MessageProvider;


/**
 * Classe View pour charger les fichiers de modèles et de vues.
 */
class View implements ArrayAccess, Renderable
{
    /**
     * L’instance de View Factory.
     *
     * @var \Two\View\Factory
     */
    protected $factory;

    /**
     * L'instance du moteur de visualisation.
     *
     * @var \Two\View\Contracts\Engines\EngineInterface
     */
    protected $engine;

    /**
     * Le nom de la vue donné.
     * 
     * @var string 
     */
    protected $view = null;

    /**
     * Le chemin d'accès au fichier View sur le disque.
     * 
     * @var string 
     */
    protected $path = null;

    /**
     * Tableau de données locales.
     * 
     * @var array 
     */
    protected $data = array();

    /**
     * Que la vue soit ou non une mise en page.
     * 
     * @var bool  
     */
    protected $layout = false;


    /**
     * Constructeur
     * @param mixed $path
     * @param array $data
     */
    public function __construct(Factory $factory, EngineInterface $engine, $view, $path, $data = array())
    {
        $this->factory = $factory;
        $this->engine  = $engine;

        //
        $this->view = $view;
        $this->path = $path;

        $this->data = ($data instanceof Arrayable) ? $data->toArray() : (array) $data;
    }

    /**
     * Obtenez le contenu de la chaîne de la vue.
     *
     * @param  \Closure  $callback
     * @return string
     */
    public function render(Closure $callback = null)
    {
        try {
            $contents = $this->renderContents();

            $response = isset($callback) ? $callback($this, $contents) : null;

            // Une fois que nous avons le contenu de la vue, nous viderons les sections si nous sommes
            // terminé le rendu de toutes les vues afin qu'il ne reste plus rien en suspens lorsque
            // une autre vue sera rendue ultérieurement par le développeur de l'application.
            $this->factory->flushSectionsIfDoneRendering();

            return $response ?: $contents;
        }
        catch (Exception $e) {
            $this->factory->flushSections();

            throw $e;
        }
    }

    /**
     * Rendre la vue et renvoyer le résultat.
     *
     * @return string
     */
    public function renderContents()
    {
        // Nous garderons une trace du nombre de vues rendues afin de pouvoir vider
        // la section après que l'opération de rendu complète soit terminée. Cette volonté
        // efface les sections pour toutes les vues distinctes qui peuvent être rendues.
        $this->factory->incrementRender();

        $this->factory->callComposer($this);

        $contents = $this->getContents();

        // Une fois que nous aurons fini de rendre la vue, nous diminuerons le nombre de rendus
        // pour que chaque section soit vidée la prochaine fois qu'une vue est créée et
        // aucune ancienne section ne reste dans la mémoire d'un environnement.
        $this->factory->decrementRender();

        return $contents;
    }

    /**
     * Obtenez les sections de la vue rendue.
     *
     * @return array
     */
    public function renderSections()
    {
        return $this->render(function ($view)
        {
            return $this->factory->getSections();
        });
    }

    /**
     * Obtenez le contenu évalué de la vue.
     *
     * @return string
     */
    protected function getContents()
    {
        return $this->engine->get($this->path, $this->gatherData());
    }

    /**
     * Renvoie toutes les variables stockées sur les données locales et partagées.
     *
     * @return array
     */
    public function gatherData()
    {
        $data = array_merge($this->factory->getShared(), $this->data);

        return array_map(function ($value)
        {
            return ($value instanceof Renderable) ? $value->render() : $value;

        }, $data);
    }

    /**
     * Ajoutez une instance de vue aux données de vue.
     *
     * <code>
     *     // Add a View instance to a View's data
     *     $view = View::make('foo')->nest('footer', 'Partials/Footer');
     *
     *     // Equivalent functionality using the "with" method
     *     $view = View::make('foo')->with('footer', View::make('Partials/Footer'));
     * </code>
     *
     * @param  string  $key
     * @param  string  $view
     * @param  array   $data
     * @return View
     */
    public function nest($key, $view, array $data = array())
    {
        // L'instance de View imbriquée hérite des données parent si aucune n'est donnée.
        if (empty($data)) {
            $data = $this->getData();
        }

        return $this->with($key, $this->factory->make($view, $data));
    }

    /**
     * Ajoutez une paire clé/valeur aux données de la vue.
     *
     * Les données liées seront disponibles dans la vue sous forme de variables.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Ajoutez des erreurs de validation à la vue.
     *
     * @param  \Two\Application\Contracts\MessageProviderInterface|array  $provider
     * @return \Two\View\View
     */
    public function withErrors($provider)
    {
        if ($provider instanceof MessageProvider) {
            $this->with('errors', $provider->getMessageBag());
        } else {
            $this->with('errors', new MessageBag((array) $provider));
        }

        return $this;
    }

    /**
     * Ajoutez une paire clé/valeur aux données de la vue partagée.
     *
     * Les données de vue partagées sont accessibles à chaque vue créée par l'application.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return View
     */
    public function shares($key, $value)
    {
        $this->factory->share($key, $value);

        return $this;
    }

    /**
     * Définir/obtenir l’indicateur de mise en page sur cette instance de vue.
     *
     * @param  bool|null  $value
     * @return View|bool
     */
    public function layout($value = null)
    {
        if (is_null($value)) {
            return $this->layout;
        }

        $this->layout = (bool) $value;

        return $this;
    }

    /**
     * Obtenez l’instance View Factory.
     *
     * @return \Two\View\Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Obtenez le nom de la vue.
     *
     * @return string
     */
    public function getName()
    {
        return $this->view;
    }

    /**
     * Obtenez le tableau des données de vue.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Obtenez le chemin d'accès au fichier de vue.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Définissez le chemin d'accès à la vue.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Implémentation de la méthode ArrayAccess offsetExists.
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Implémentation de la méthode ArrayAccess offsetGet.
     */
    public function offsetGet($offset): mixed
    {
        return Arr::get($this->data, $offset);
    }

    /**
     * Implémentation de la méthode ArrayAccess offsetSet.
     */
    public function offsetSet($offset, $value): void
    {
        $this->data[$offset] = $value;
    }

    /**
     * Implémentation de la méthode ArrayAccess offsetUnset.
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Méthode magique pour gérer l’accès dynamique aux données.
     */
    public function __get($key)
    {
        return Arr::get($this->data, $key);
    }

    /**
     * Méthode magique pour gérer le paramétrage dynamique des données.
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Méthode magique pour vérifier les données définies dynamiquement.
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Méthode magique pour gérer les fonctions dynamiques.
     *
     * @param  string  $method
     * @param  array   $params
     * @return \Two\View\View|static|void
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $params)
    {
        // Ajoutez la prise en charge des méthodes dynamiques withX.
        if (Str::startsWith($method, 'with')) {
            $name = Str::camel(substr($method, 4));

            return $this->with($name, array_shift($params));
        }

        throw new BadMethodCallException("Method [$method] does not exist on " .get_class($this));
    }


    /**
     * Obtenez le contenu de la chaîne évaluée de la vue.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return '';
        }
    }

}
