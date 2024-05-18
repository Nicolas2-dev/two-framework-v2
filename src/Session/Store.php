<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use SessionHandlerInterface;
use InvalidArgumentException;

use Two\Session\Contracts\SessionInterface;
use Two\Session\Contracts\ExistenceAwareInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;


class Store implements SessionInterface
{
    /**
     * L'identifiant de la session.
     *
     * @var string
     */
    protected $id;

    /**
     * Le nom de la session.
     *
     * @var string
     */
    protected $name;

    /**
     * Les attributs de la session.
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * Les sacs de séance.
     *
     * @var array
     */
    protected $bags = array();

    /**
     * L'instance du sac de métadonnées.
     *
     * @var \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag
     */
    protected $metaBag;

    /**
     * Copies locales des données du sac de session.
     *
     * @var array
     */
    protected $bagData = array();

    /**
     * L’implémentation du gestionnaire de session.
     *
     * @var \SessionHandlerInterface
     */
    protected $handler;

    /**
     * Statut démarré du magasin de sessions.
     *
     * @var bool
     */
    protected $started = false;

    /**
     * Créez une nouvelle instance de session.
     *
     * @param  string  $name
     * @param  \SessionHandlerInterface  $handler
     * @param  string|null  $id
     * @return void
     */
    public function __construct($name, SessionHandlerInterface $handler, $id = null)
    {
        $this->setId($id);

        $this->name = $name;
        $this->handler = $handler;

        $this->metaBag = new MetadataBag;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $this->loadSession();

        if (! $this->has('_token')) $this->regenerateToken();

        return $this->started = true;
    }

    /**
     * Chargez les données de session à partir du gestionnaire.
     *
     * @return void
     */
    protected function loadSession()
    {
        $this->attributes = $this->readFromHandler();

        foreach (array_merge($this->bags, array($this->metaBag)) as $bag)
        {
            $this->initializeLocalBag($bag);

            $bag->initialize($this->bagData[$bag->getStorageKey()]);
        }
    }

    /**
     * Lisez les données de session du gestionnaire.
     *
     * @return array
     */
    protected function readFromHandler()
    {
        $data = $this->handler->read($this->getId());

        return $data ? unserialize($data) : array();
    }

    /**
     * Initialisez un sac en stockage s'il n'existe pas.
     *
     * @param  \Symfony\Component\HttpFoundation\Session\SessionBagInterface  $bag
     * @return void
     */
    protected function initializeLocalBag($bag)
    {
        $this->bagData[$bag->getStorageKey()] = $this->pull($bag->getStorageKey(), array());
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        if (! $this->isValidId($id)) {
            $id = $this->generateSessionId();
        }

        $this->id = $id;
    }

    /**
     * Déterminez s’il s’agit d’un ID de session valide.
     *
     * @param  string  $id
     * @return bool
     */
    public function isValidId($id)
    {
        return (is_string($id) && preg_match('/^[a-f0-9]{40}$/', $id));
    }

    /**
     * Obtenez un nouvel identifiant de session aléatoire.
     *
     * @return string
     */
    protected function generateSessionId()
    {
        return sha1(uniqid('', true) .str_random(25) .microtime(true));
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate($lifetime = null): bool
    {
        $this->attributes = array();

        $this->migrate();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function migrate($destroy = false, $lifetime = null): bool
    {
        if ($destroy) $this->handler->destroy($this->getId());

        $this->setExists(false);

        $this->id = $this->generateSessionId(); return true;
    }

    /**
     * Générez un nouvel identifiant de session.
     *
     * @param  bool  $destroy
     * @return bool
     */
    public function regenerate($destroy = false)
    {
        return $this->migrate($destroy);
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->addBagDataToSession();

        $this->ageFlashData();

        $this->handler->write($this->getId(), serialize($this->attributes));

        $this->started = false;
    }

    /**
     * Fusionnez toutes les données du sac dans la session.
     *
     * @return void
     */
    protected function addBagDataToSession()
    {
        $bags = array_merge($this->bags, array($this->metaBag));

        foreach ($bags as $bag) {
            $name = $bag->getStorageKey();

            $this->put($name, $this->getBagData($name));
        }
    }

    /**
     * Faites vieillir les données flash de la session.
     *
     * @return void
     */
    public function ageFlashData()
    {
        foreach ($this->get('flash.old', array()) as $old) {
            $this->forget($old);
        }

        $this->put('flash.old', $this->get('flash.new', array()));

        $this->put('flash.new', array());
    }

    /**
     * {@inheritdoc}
     */
    public function has($name): bool
    {
        return ! is_null($this->get($name));
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, $default = null): mixed
    {
        return array_get($this->attributes, $name, $default);
    }

    /**
     * Obtenez la valeur d'une clé donnée, puis oubliez-la.
     *
     * @param  string  $key
     * @param  string  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return array_pull($this->attributes, $key, $default);
    }

    /**
     * Déterminez si la session contient d’anciennes entrées.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasOldInput($key = null)
    {
        $old = $this->getOldInput($key);

        return is_null($key) ? count($old) > 0 : ! is_null($old);
    }

    /**
     * Obtenez l'élément demandé à partir du tableau d'entrée flashé.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function getOldInput($key = null, $default = null)
    {
        $input = $this->get('_old_input', array());

        // Les entrées flashées dans la session peuvent être facilement récupérées par le
        // développeur, rendant le repeuplement d'anciens formulaires et autres bien plus
        // pratique, puisque l'entrée précédente de la requête est disponible.
        return array_get($input, $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        array_set($this->attributes, $name, $value);
    }

    /**
     * Mettez une paire clé/valeur ou un tableau de paires clé/valeur dans la session.
     *
     * @param  string|array  $key
     * @param  mixed|null       $value
     * @return void
     */
    public function put($key, $value = null)
    {
        if (! is_array($key)) $key = array($key => $value);

        foreach ($key as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }
    }

    /**
     * Poussez une valeur sur un tableau de session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function push($key, $value)
    {
        $array = $this->get($key, array());

        $array[] = $value;

        $this->put($key, $array);
    }

    /**
     * Flashez une paire clé/valeur dans la session.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function flash($key, $value)
    {
        $this->put($key, $value);

        $this->push('flash.new', $key);

        $this->removeFromOldFlashData(array($key));
    }

    /**
     * Flashez un tableau d’entrée dans la session.
     *
     * @param  array  $value
     * @return void
     */
    public function flashInput(array $value)
    {
        $this->flash('_old_input', $value);
    }

    /**
     * Reflashez toutes les données flash de la session.
     *
     * @return void
     */
    public function reflash()
    {
        $this->mergeNewFlashes($this->get('flash.old', array()));

        $this->put('flash.old', array());
    }

    /**
     * Reflasher un sous-ensemble des données flash actuelles.
     *
     * @param  array|mixed  $keys
     * @return void
     */
    public function keep($keys = null)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $this->mergeNewFlashes($keys);

        $this->removeFromOldFlashData($keys);
    }

    /**
     * Fusionnez les nouvelles clés flash dans la nouvelle matrice flash.
     *
     * @param  array  $keys
     * @return void
     */
    protected function mergeNewFlashes(array $keys)
    {
        $values = array_unique(array_merge($this->get('flash.new', array()), $keys));

        $this->put('flash.new', $values);
    }

    /**
     * Supprimez les clés données des anciennes données flash.
     *
     * @param  array  $keys
     * @return void
     */
    protected function removeFromOldFlashData(array $keys)
    {
        $this->put('flash.old', array_diff($this->get('flash.old', array()), $keys));
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function replace(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->put($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name): mixed
    {
        return array_pull($this->attributes, $name);
    }

    /**
     * Supprimer un élément de la session.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key)
    {
        array_forget($this->attributes, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->attributes = array();

        foreach ($this->bags as $bag) {
            $bag->clear();
        }
    }

    /**
     * Supprimez tous les éléments de la session.
     *
     * @return void
     */
    public function flush()
    {
        $this->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     */
    public function registerBag(SessionBagInterface $bag)
    {
        $this->bags[$bag->getStorageKey()] = $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function getBag($name): SessionBagInterface
    {
        return array_get($this->bags, $name, function()
        {
            throw new InvalidArgumentException("Bag not registered.");
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataBag(): MetadataBag
    {
        return $this->metaBag;
    }

    /**
     * Obtenez le tableau de données du sac brut pour un sac donné.
     *
     * @param  string  $name
     * @return array
     */
    public function getBagData($name)
    {
        return array_get($this->bagData, $name, array());
    }

    /**
     * Obtenez la valeur du jeton CSRF.
     *
     * @return string
     */
    public function token()
    {
        return $this->get('_token');
    }

    /**
     * Obtenez la valeur du jeton CSRF.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token();
    }

    /**
     * Régénérez la valeur du jeton CSRF.
     *
     * @return void
     */
    public function regenerateToken()
    {
        $this->put('_token', str_random(40));
    }

    /**
     * Obtenez l'URL précédente de la session.
     *
     * @return string|null
     */
    public function previousUrl()
    {
        return $this->get('_previous.url');
    }

    /**
     * Définissez l'URL "précédente" dans la session.
     *
     * @param  string  $url
     * @return void
     */
    public function setPreviousUrl($url)
    {
        return $this->put('_previous.url', $url);
    }

    /**
     * Définissez l'existence de la session sur le gestionnaire, le cas échéant.
     *
     * @param  bool  $value
     * @return void
     */
    public function setExists($value)
    {
        if ($this->handler instanceof ExistenceAwareInterface) {
            $this->handler->setExists($value);
        }
    }

    /**
     * Obtenez l’implémentation du gestionnaire de session sous-jacent.
     *
     * @return \SessionHandlerInterface
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Déterminez si le gestionnaire de session a besoin d’une demande.
     *
     * @return bool
     */
    public function handlerNeedsRequest()
    {
        return ($this->handler instanceof CookieSessionHandler);
    }

    /**
     * Définissez la requête sur l'instance du gestionnaire.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function setRequestOnHandler(Request $request)
    {
        if ($this->handlerNeedsRequest()) {
            $this->handler->setRequest($request);
        }
    }

}
