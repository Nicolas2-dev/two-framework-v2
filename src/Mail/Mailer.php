<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Mail;

use Swift_Mailer;
use Swift_Message;
use Closure;

use Two\Log\Writer;
use Two\View\Factory;
use Two\Events\Dispatcher;
use Two\Container\Container;
use Two\Queue\QueueManager;
use Two\Support\Arr;

use SuperClosure\Serializer;


class Mailer
{
    /**
     * L'instance de fabrique de vues.
     *
     * @var \Two\View\Factory
     */
    protected $views;

    /**
     * L'instance Swift Mailer.
     *
     * @var \Swift_Mailer
     */
    protected $swift;

    /**
     * L'instance du répartiteur d'événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'adresse et le nom global de l'expéditeur.
     *
     * @var array
     */
    protected $from;

    /**
     * Instance de l'auteur de journal.
     *
     * @var \Two\Log\Writer
     */
    protected $logger;

    /**
     * L'instance de conteneur IoC.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * L'instance de QueueManager.
     *
     * @var \Two\Queue\QueueManager
     */
    protected $queue;

    /**
     * Indique si l'envoi réel est désactivé.
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * Tableau de destinataires ayant échoué.
     *
     * @var array
     */
    protected $failedRecipients = array();

    /**
     * Tableau de vues analysées contenant le nom de la vue HTML et texte.
     *
     * @var array
     */
    protected $parsedViews = array();


    /**
     * Créez une nouvelle instance de Mailer.
     *
     * @param  \Two\View\Factory  $views
     * @param  \Swift_Mailer  $swift
     * @param  \Two\Events\Dispatcher  $events
     * @return void
     */
    public function __construct(Factory $views, Swift_Mailer $swift, Dispatcher $events = null)
    {
        $this->views = $views;
        $this->swift = $swift;
        $this->events = $events;
    }

    /**
     * Définissez l’adresse et le nom global de.
     *
     * @param  string  $address
     * @param  string  $name
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Envoyez un nouveau message lorsqu'il ne s'agit que d'une partie de texte brut.
     *
     * @param  string  $text
     * @param  mixed  $callback
     * @return int
     */
    public function raw($text, $callback)
    {
        return $this->send(array('raw' => $text), array(), $callback);
    }

    /**
     * Envoyez un nouveau message lorsqu'il ne s'agit que d'une partie simple.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  mixed   $callback
     * @return int
     */
    public function plain($view, array $data, $callback)
    {
        return $this->send(array('text' => $view), $data, $callback);
    }

    /**
     * Envoyez un nouveau message en utilisant une vue.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return void
     */
    public function send($view, array $data, $callback)
    {
        // Nous devons d’abord analyser la vue, qui peut être une chaîne ou un tableau.
        // contenant à la fois une version HTML et une version texte brut de la vue qui devrait
        // être utilisé lors de l'envoi d'un e-mail. Nous allons les extraire tous les deux ici.
        list($view, $plain, $raw) = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        $this->callMessageBuilder($callback, $message);

        // Une fois que nous aurons récupéré le contenu de l'affichage de l'e-mail, nous définirons le corps
        // de ce message en utilisant le type HTML, qui fournira un simple wrapper
        // pour créer des e-mails basés sur des vues capables de recevoir des tableaux de données.
        $this->addContent($message, $view, $plain, $raw, $data);

        $message = $message->getSwiftMessage();

        $this->sendSwiftMessage($message);
    }

    /**
     * Mettez en file d'attente un nouveau message électronique pour l'envoi.
     *
     * @param  string|array  $view
     * @param  array   $data
     * @param  \Closure|string  $callback
     * @param  string  $queue
     * @return mixed
     */
    public function queue($view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        return $this->queue->push('mailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Mettre en file d'attente un nouveau message électronique à envoyer dans la file d'attente donnée.
     *
     * @param  string  $queue
     * @param  string|array  $view
     * @param  array   $data
     * @param  \Closure|string  $callback
     * @return mixed
     */
    public function queueOn($queue, $view, array $data, $callback)
    {
        return $this->queue($view, $data, $callback, $queue);
    }

    /**
     * Mettez en file d'attente un nouveau message électronique à envoyer après (n) secondes.
     *
     * @param  int  $delay
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @param  string  $queue
     * @return mixed
     */
    public function later($delay, $view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        return $this->queue->later($delay, 'mailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Mettre en file d'attente un nouveau message électronique à envoyer après (n) secondes dans la file d'attente donnée.
     *
     * @param  string  $queue
     * @param  int  $delay
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return mixed
     */
    public function laterOn($queue, $delay, $view, array $data, $callback)
    {
        return $this->later($delay, $view, $data, $callback, $queue);
    }

    /**
     * Créez l'appelable pour une tâche de courrier électronique en file d'attente.
     *
     * @param  mixed  $callback
     * @return mixed
     */
    protected function buildQueueCallable($callback)
    {
        if ( ! $callback instanceof Closure) return $callback;

        return (new Serializer)->serialize($callback);
    }

    /**
     * Gérer un travail de messagerie électronique en file d'attente.
     *
     * @param  \Two\Queue\Jobs\Job  $job
     * @param  array  $data
     * @return void
     */
    public function handleQueuedMessage($job, $data)
    {
        $this->send($data['view'], $data['data'], $this->getQueuedCallable($data));

        $job->delete();
    }

    /**
     * Obtenez le véritable appelable pour un message électronique en file d'attente.
     *
     * @param  array  $data
     * @return mixed
     */
    protected function getQueuedCallable(array $data)
    {
        if (str_contains($data['callback'], 'SerializableClosure')) {
            return with(unserialize($data['callback']))->getClosure();
        }

        return $data['callback'];
    }

    /**
     * Ajoutez le contenu à un message donné.
     *
     * @param  \Two\Mail\Message  $message
     * @param  string  $view
     * @param  string  $plain
     * @param  string  $raw
     * @param  array   $data
     * @return void
     */
    protected function addContent($message, $view, $plain, $raw, $data)
    {
        if (isset($view)) {
            $message->setBody($this->getView($view, $data), 'text/html');
        }

        if (isset($plain)) {
            $message->addPart($this->getView($plain, $data), 'text/plain');
        }

        if (isset($raw)) {
            $method = (isset($view) || isset($plain)) ? 'addPart' : 'setBody';

            call_user_func(array($message, $method), $raw, 'text/plain');
        }
    }

    /**
     * Analyse le nom de la vue ou le tableau donné.
     *
     * @param  string|array  $view
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseView($view)
    {
        if (is_string($view)) {
            return array($view, null, null);
        }

        // Si la vue donnée est un tableau avec des touches numériques, nous supposerons simplement que
        // une vue "jolie" et "simple" a été fournie, nous allons donc renvoyer ceci
        // tableau tel quel, car il doit contenir les deux vues avec des touches numériques.
        if (is_array($view) && isset($view[0])) {
            return array($view[0], $view[1], null);
        }

        // Si la vue est un tableau mais ne contient pas de touches numériques, nous supposerons
        // les vues sont explicitement spécifiées et les extrairont via
        // clés nommées à la place, permettant aux développeurs d'utiliser l'une ou l'autre.
        else if (is_array($view)) {
            return array(
                Arr::get($view, 'html'),
                Arr::get($view, 'text'),
                Arr::get($view, 'raw'),
            );
        }

        throw new \InvalidArgumentException("Invalid view.");
    }

    /**
     * Envoyez une instance de message Swift.
     *
     * @param  \Swift_Message  $message
     * @return void
     */
    protected function sendSwiftMessage($message)
    {
        if ($this->events) {
            $this->events->dispatch('mailer.sending', array($message));
        }

        if (! $this->pretending) {
            try {
                $this->swift->send($message, $this->failedRecipients);
            }
            finally {
                $this->swift->getTransport()->stop();
            }
        }

        // En mode simulation.
        else if (isset($this->logger)) {
            $this->logMessage($message);
        }
    }

    /**
     * Enregistrez qu'un message a été envoyé.
     *
     * @param  \Swift_Message  $message
     * @return void
     */
    protected function logMessage($message)
    {
        $emails = implode(', ', array_keys((array) $message->getTo()));

        $this->logger->info("Pretending to mail message to: {$emails}");
    }

    /**
     * Appelez le générateur de messages fourni.
     *
     * @param  \Closure|string  $callback
     * @param  \Two\Mail\Message  $message
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function callMessageBuilder($callback, $message)
    {
        if ($callback instanceof Closure) {
            return call_user_func($callback, $message);
        } else if (is_string($callback)) {
            return $this->container[$callback]->mail($message);
        }

        throw new \InvalidArgumentException("Callback is not valid.");
    }

    /**
     * Créez une nouvelle instance de message.
     *
     * @return \Two\Mail\Message
     */
    protected function createMessage()
    {
        $message = new Message(new Swift_Message);

        // Si une adresse d'expéditeur globale a été spécifiée, nous la définirons sur chaque message
        // instances pour que le développeur n'ait pas à se répéter à chaque fois
        // ils créent un nouveau message. Nous allons simplement aller de l'avant et promouvoir l'adresse.
        if (isset($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        return $message;
    }

    /**
     * Rendre la vue donnée.
     *
     * @param  string  $view
     * @param  array   $data
     * @return \Two\View\View
     */
    protected function getView($view, $data)
    {
        return $this->views->make($view, $data)->render();
    }

    /**
     * Dites au courrier de ne pas vraiment envoyer de messages.
     *
     * @param  bool  $value
     * @return void
     */
    public function pretend($value = true)
    {
        $this->pretending = $value;
    }

    /**
     * Vérifiez si le logiciel de messagerie fait semblant d'envoyer des messages.
     *
     * @return bool
     */
    public function isPretending()
    {
        return $this->pretending;
    }

    /**
     * Obtenez l’instance de fabrique de vues.
     *
     * @return \Two\View\Factory
     */
    public function getFactory()
    {
        return $this->views;
    }

    /**
     * Obtenez l'instance Swift Mailer.
     *
     * @return \Swift_Mailer
     */
    public function getSwiftMailer()
    {
        return $this->swift;
    }

    /**
     * Obtenez le tableau des destinataires ayant échoué.
     *
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * Définissez l'instance Swift Mailer.
     *
     * @param  \Swift_Mailer  $swift
     * @return void
     */
    public function setSwiftMailer($swift)
    {
        $this->swift = $swift;
    }

    /**
     * Définissez l'instance d'écriture de journaux.
     *
     * @param  \Two\Log\Writer  $logger
     * @return $this
     */
    public function setLogger(Writer $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Définissez l'instance du gestionnaire de files d'attente.
     *
     * @param  \Two\Queue\QueueManager  $queue
     * @return $this
     */
    public function setQueue(QueueManager $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Définissez l'instance de conteneur IoC.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

}
