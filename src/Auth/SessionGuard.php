<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use RuntimeException;

use Two\Cookie\CookieJar;
use Two\Events\Dispatcher;
use Two\Auth\Contracts\UserInterface;
use Two\Auth\Contracts\GuardInterface;
use Two\Auth\Traits\GuardHelpersTrait;
use Two\Session\Store as SessionStore;

use Symfony\Component\HttpFoundation\Request;
use Two\Auth\Contracts\UserProviderInterface;
use Symfony\Component\HttpFoundation\Response;


class SessionGuard implements GuardInterface
{
    use GuardHelpersTrait;

    /**
     * Le nom de la Garde. Généralement « session ».
     *
     * Correspond au nom du pilote dans la configuration d'authentification.
     *
     * @var string
     */
    protected $name;

    /**
     * L'utilisateur que nous avons tenté de récupérer pour la dernière fois.
     *
     * @var \Two\Auth\Contracts\UserInterface
     */
    protected $lastAttempted;

    /**
     * Indique si l'utilisateur a été authentifié via un cookie de rappel.
     *
     * @var bool
     */
    protected $viaRemember = false;

    /**
     * Le magasin de sessions utilisé par le gardien.
     *
     * @var \Two\Session\Store
     */
    protected $session;

    /**
     * Le service de création de deux cookies.
     *
     * @var \Two\Cookie\CookieJar
     */
    protected $cookie;

    /**
     * L’instance de requête.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * L'instance du répartiteur d'événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * Indique si la méthode de déconnexion a été appelée.
     *
     * @var bool
     */
    protected $loggedOut = false;

    /**
     * Indique si une récupération de jeton utilisateur a été tentée.
     *
     * @var bool
     */
    protected $tokenRetrievalAttempted = false;


    /**
     * Créez un nouveau garde d'authentification.
     *
     * @param  \Two\Auth\Contracts\UserProviderInterface  $provider
     * @param  \Two\Session\Store  $session
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return void
     */
    public function __construct($name,
                                UserProviderInterface $provider,
                                SessionStore $session,
                                Request $request = null)
    {
        $this->name = $name;

        $this->session  = $session;
        $this->request  = $request;
        $this->provider = $provider;
    }

    /**
     * Obtenez l'utilisateur actuellement authentifié.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function user()
    {
        if ($this->loggedOut) {
            return;
        }

        // Si nous avons déjà récupéré l'utilisateur pour la requête en cours, nous pouvons simplement
        // le renvoie immédiatement. Nous ne voulons pas extraire les données utilisateur à chaque fois.
        // requête dans la méthode car cela ralentirait considérablement une application.
        if (! is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session->get($this->getName());

        // Nous allons d'abord essayer de charger l'utilisateur en utilisant l'identifiant dans la session si
        // il en existe un. Sinon, nous rechercherons un cookie "se souvenir de moi" dans ce
        // demande, et s'il en existe une, tente de récupérer l'utilisateur en l'utilisant.
        $user = null;

        if (! is_null($id)) {
            $user = $this->provider->retrieveByID($id);
        }

        // Si l'utilisateur est nul, mais que nous déchiffrons un cookie "rappelant", nous pouvons tenter de
        // récupère les données utilisateur sur ce cookie qui sert de cookie de mémorisation sur
        // L'application. Une fois que nous avons un utilisateur, nous pouvons le renvoyer à l'appelant.
        $recaller = $this->getRecaller();

        if (is_null($user) && ! is_null($recaller)) {
            $user = $this->getUserByRecaller($recaller);
        }

        return $this->user = $user;
    }

    /**
     * Obtenez l'ID de l'utilisateur actuellement authentifié.
     *
     * @return int|null
     */
    public function id()
    {
        if ($this->loggedOut) {
            return;
        }

        $id = $this->session->get($this->getName(), $this->getRecallerId());

        if (is_null($id) && ! is_null($user = $this->user())) {
            $id = $user->getAuthIdentifier();
        }

        return $id;
    }

    /**
     * Validez les informations d'identification d'un utilisateur.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array())
    {
        return $this->attempt($credentials, false, false);
    }

    /**
     * Extrayez un utilisateur du référentiel par son ID de rappel.
     *
     * @param  string  $recaller
     * @return mixed
     */
    protected function getUserByRecaller($recaller)
    {
        if ($this->validRecaller($recaller) && ! $this->tokenRetrievalAttempted) {
            $this->tokenRetrievalAttempted = true;

            list($id, $token) = explode('|', $recaller, 2);

            $this->viaRemember = ! is_null($user = $this->provider->retrieveByToken($id, $token));

            return $user;
        }
    }

    /**
     * Obtenez le cookie de rappel déchiffré pour la demande.
     *
     * @return string|null
     */
    protected function getRecaller()
    {
        $name = $this->getRecallerName();

        return $this->request->cookies->get($name);
    }

    /**
     * Obtenez l'ID utilisateur du cookie de rappel.
     *
     * @return string
     */
    protected function getRecallerId()
    {
        if ($this->validRecaller($recaller = $this->getRecaller())) {
            $segments = explode('|', $recaller);

            return head($segments);
        }
    }

    /**
     * Déterminez si le cookie de rappel est dans un format valide.
     *
     * @param  string  $recaller
     * @return bool
     */
    protected function validRecaller($recaller)
    {
        if (! is_string($recaller) || ! str_contains($recaller, '|')) {
            return false;
        }

        $segments = explode('|', $recaller);

        return (count($segments) == 2) && (trim($segments[0]) !== '') && (trim($segments[1]) !== '');
    }

    /**
     * Connectez un utilisateur à l'application sans sessions ni cookies.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function once(array $credentials = array())
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);

            return true;
        }

        return false;
    }

    /**
     * Essayez de vous authentifier à l'aide de HTTP Basic Auth.
     *
     * @param  string  $field
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public function basic($field = 'email', Request $request = null)
    {
        if ($this->check()) {
            return;
        }

        $request = $request ?: $this->getRequest();

        // Si un nom d'utilisateur est défini sur la requête HTTP de base, nous reviendrons sans
        // interrompant le cycle de vie de la requête. Sinon, nous devrons générer un
        // requête indiquant que les informations d'identification fournies n'étaient pas valides pour la connexion.
        if ($this->attemptBasic($request, $field)) {
            return;
        }

        return $this->getBasicResponse();
    }

    /**
     * Effectuez une tentative de connexion HTTP Basic sans état.
     *
     * @param  string  $field
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public function onceBasic($field = 'email', Request $request = null)
    {
        $request = $request ?: $this->getRequest();

        if (! $this->once($this->getBasicCredentials($request, $field))) {
            return $this->getBasicResponse();
        }
    }

    /**
     * Essayez de vous authentifier à l'aide de l'authentification de base.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  string  $field
     * @return bool
     */
    protected function attemptBasic(Request $request, $field)
    {
        if (! $request->getUser()) {
            return false;
        }

        return $this->attempt($this->getBasicCredentials($request, $field));
    }

    /**
     * Obtenez le tableau d’informations d’identification pour une requête HTTP Basic.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  string  $field
     * @return array
     */
    protected function getBasicCredentials(Request $request, $field)
    {
        return array($field => $request->getUser(), 'password' => $request->getPassword());
    }

    /**
     * Obtenez la réponse pour l’authentification de base.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getBasicResponse()
    {
        $headers = array('WWW-Authenticate' => 'Basic');

        return new Response('Invalid credentials.', 401, $headers);
    }

    /**
     * Tentative d'authentifier un utilisateur à l'aide des informations d'identification fournies.
     *
     * @param  array  $credentials
     * @param  bool   $remember
     * @param  bool   $login
     * @return bool
     */
    public function attempt(array $credentials = array(), $remember = false, $login = true)
    {
        $this->fireAttemptEvent($credentials, $remember, $login);

        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        // Si une implémentation de UserInterface a été renvoyée, nous demanderons au fournisseur
        // pour valider l'utilisateur par rapport aux informations d'identification données, et s'il est dans
        // fait valide, nous connecterons les utilisateurs à l'application et retournerons vrai.
        if (! $this->hasValidCredentials($user, $credentials)) {
            return false;
        }

        if ($login) {
            $this->login($user, $remember);
        }

        return true;
    }

    /**
     * Déterminez si l'utilisateur correspond aux informations d'identification.
     *
     * @param  mixed  $user
     * @param  array  $credentials
     * @return bool
     */
    protected function hasValidCredentials($user, $credentials)
    {
        return ! is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Déclenchez l'événement de tentative avec les arguments.
     *
     * @param  array  $credentials
     * @param  bool   $remember
     * @param  bool   $login
     * @return void
     */
    protected function fireAttemptEvent(array $credentials, $remember, $login)
    {
        if ($this->events) {
            $payload = array($credentials, $remember, $login);

            $this->events->dispatch('auth.attempt', $payload);
        }
    }

    /**
     * Enregistrez un écouteur d’événement de tentative d’authentification.
     *
     * @param  mixed  $callback
     * @return void
     */
    public function attempting($callback)
    {
        if ($this->events) {
            $this->events->listen('auth.attempt', $callback);
        }
    }

    /**
     * Connectez un utilisateur à l'application.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  bool  $remember
     * @return void
     */
    public function login(UserInterface $user, $remember = false)
    {
        $this->updateSession($user->getAuthIdentifier());

        // Si l'utilisateur doit être "mémorisé" en permanence par l'application, nous le ferons
        // met en file d'attente un cookie permanent qui contient la copie cryptée de l'utilisateur
        // identifiant. Nous le décrypterons ensuite plus tard pour récupérer les utilisateurs.
        if ($remember) {
            $this->createRememberTokenIfDoesntExist($user);

            $this->queueRecallerCookie($user);
        }

        // Si nous avons une instance de répartiteur d'événements définie, nous déclencherons un événement afin que
        // tous les auditeurs se connecteront aux événements d'authentification et exécuteront des actions
        // basé sur les événements de connexion et de déconnexion déclenchés par les instances de garde.
        if (isset($this->events)) {
            $this->events->dispatch('auth.login', array($user, $remember));
        }

        $this->setUser($user);
    }

    /**
     * Mettez à jour la session avec l'ID donné.
     *
     * @param  string  $id
     * @return void
     */
    protected function updateSession($id)
    {
        $this->session->put($this->getName(), $id);

        $this->session->migrate(true);
    }

    /**
     * Connectez-vous à l'ID utilisateur donné dans l'application.
     *
     * @param  mixed  $id
     * @param  bool   $remember
     * @return \Two\Auth\Contracts\UserInterface
     */
    public function loginUsingId($id, $remember = false)
    {
        $this->session->put($this->getName(), $id);

        $user = $this->provider->retrieveById($id);

        $this->login($user, $remember);

        return $user;
    }

    /**
     * Connectez-vous à l'ID utilisateur donné dans l'application sans sessions ni cookies.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function onceUsingId($id)
    {
        $user = $this->provider->retrieveById($id);

        $this->setUser($user);

        return ($user instanceof UserInterface);
    }

    /**
     * Mettez le cookie de rappel en file d'attente dans le pot à biscuits.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function queueRecallerCookie(UserInterface $user)
    {
        $value = $user->getAuthIdentifier() .'|' .$user->getRememberToken();

        $cookie = $this->createRecaller($value);

        $this->getCookieJar()->queue($cookie);
    }

    /**
     * Créez un cookie Remember Me pour un identifiant donné.
     *
     * @param  string  $value
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    protected function createRecaller($value)
    {
        $cookies = $this->getCookieJar();

        return $cookies->forever($this->getRecallerName(), $value);
    }

    /**
     * Déconnectez l’utilisateur de l’application.
     *
     * @return void
     */
    public function logout()
    {
        $user = $this->user();

        // Si nous avons une instance de répartiteur d'événements, nous pouvons déclencher l'événement de déconnexion
        // afin que tout traitement ultérieur puisse être effectué. Cela permet au développeur d'être
        // écoute à chaque fois qu'un utilisateur se déconnecte manuellement de cette application.
        $this->clearUserDataFromStorage();

        if (! is_null($this->user)) {
            $this->refreshRememberToken($user);
        }

        if (isset($this->events)) {
            $this->events->dispatch('auth.logout', array($user));
        }

        // Une fois que nous aurons déclenché l'événement de déconnexion, nous effacerons la mémoire des utilisateurs.
        // ils ne sont donc plus disponibles car l'utilisateur n'est plus considéré comme
        // étant connecté à cette application et ne devrait pas être disponible ici.
        $this->user = null;

        $this->loggedOut = true;
    }

    /**
     * Supprimez les données utilisateur de la session et des cookies.
     *
     * @return void
     */
    protected function clearUserDataFromStorage()
    {
        $this->session->forget($this->getName());

        $recaller = $this->getRecallerName();

        $this->getCookieJar()->queue($this->getCookieJar()->forget($recaller));
    }

    /**
     * Actualisez le jeton de mémorisation pour l'utilisateur.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function refreshRememberToken(UserInterface $user)
    {
        $user->setRememberToken($token = str_random(60));

        $this->provider->updateRememberToken($user, $token);
    }

    /**
     * Créez un nouveau jeton de mémorisation pour l'utilisateur s'il n'en existe pas déjà un.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    protected function createRememberTokenIfDoesntExist(UserInterface $user)
    {
        $rememberToken = $user->getRememberToken();

        if (empty($rememberToken)) {
            $this->refreshRememberToken($user);
        }
    }

    /**
     * Obtenez l'instance de créateur de cookie utilisée par le gardien.
     *
     * @return \Two\Cookie\CookieJar
     *
     * @throws \RuntimeException
     */
    public function getCookieJar()
    {
        if (! isset($this->cookie)) {
            throw new RuntimeException("Cookie jar has not been set.");
        }

        return $this->cookie;
    }

    /**
     * Définissez l'instance de créateur de cookie utilisée par le gardien.
     *
     * @param  \Two\Cookie\CookieJar  $cookie
     * @return void
     */
    public function setCookieJar(CookieJar $cookie)
    {
        $this->cookie = $cookie;
    }

    /**
     * Obtenez l’instance du répartiteur d’événements.
     *
     * @return \Two\Events\Dispatcher
     */
    public function getDispatcher()
    {
        return $this->events;
    }

    /**
     * Définissez l’instance du répartiteur d’événements.
     *
     * @param  \Two\Events\Dispatcher
     * @return void
     */
    public function setDispatcher(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Obtenez le magasin de sessions utilisé par le garde.
     *
     * @return \Two\Session\Store
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Obtenez le fournisseur d'utilisateurs utilisé par le gardien.
     *
     * @return \Two\Auth\Contracts\UserProviderInterface
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Définissez le fournisseur d'utilisateurs utilisé par le gardien.
     *
     * @param  \Two\Auth\Contracts\UserProviderInterface  $provider
     * @return void
     */
    public function setProvider(UserProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Renvoie l'utilisateur actuellement mis en cache de l'application.
     *
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Définissez l'utilisateur actuel de l'application.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @return void
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        $this->loggedOut = false;

        return $this;
    }

    /**
     * Obtenez l’instance de requête actuelle.
     *
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->request ?: Request::createFromGlobals();
    }

    /**
     * Définissez l’instance de requête actuelle.
     *
     * @param  \Symfony\Component\HttpFoundation\Request
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Obtenez le dernier utilisateur que nous avons tenté de nous authentifier.
     *
     * @return \Two\Auth\Contracts\UserInterface
     */
    public function getLastAttempted()
    {
        return $this->lastAttempted;
    }

    /**
     * Obtenez un identifiant unique pour la valeur de la session d'authentification.
     *
     * @return string
     */
    public function getName()
    {
        return 'login_' .$this->name .'_' .md5(get_class($this));
    }

    /**
     * Obtenez le nom du cookie utilisé pour stocker le « rappel ».
     *
     * @return string
     */
    public function getRecallerName()
    {
        return PREFIX .'remember_' .$this->name .'_' .md5(get_class($this));
    }

    /**
     * Déterminez si l'utilisateur a été authentifié via le cookie « se souvenir de moi ».
     *
     * @return bool
     */
    public function viaRemember()
    {
        return $this->viaRemember;
    }

}
