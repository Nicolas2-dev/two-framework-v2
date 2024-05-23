<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling\Event;


use LogicException;

use Two\Mail\Mailer;
use Two\Container\Container;
use Two\Support\ProcessUtils;
use Two\Application\Two;
use Two\Console\Scheduling\Contracts\MutexInterface as Mutex;

use Closure;
use Carbon\Carbon;

use Cron\CronExpression;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;


class Event
{
    /**
     * La chaîne de commande.
     *
     * @var string
     */
    public $command;

    /**
     * L'expression cron représentant la fréquence de l'événement.
     *
     * @var string
     */
    public $expression = '* * * * * *';

    /**
     * Le fuseau horaire sur lequel la date doit être évaluée.
     *
     * @var \DateTimeZone|string
     */
    public $timezone;

    /**
     * L'utilisateur sous lequel la commande doit être exécutée.
     *
     * @var string
     */
    public $user;

    /**
     * La liste des environnements dans lesquels la commande doit s'exécuter.
     *
     * @var array
     */
    public $environments = array();

    /**
     * Indique si la commande doit s'exécuter en mode maintenance.
     *
     * @var bool
     */
    public $evenInMaintenanceMode = false;

    /**
     * Indique si la commande ne doit pas se chevaucher.
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * La durée pendant laquelle le mutex doit être valide.
     *
     * @var int
     */
    public $expiresAt = 1440;

    /**
     * Indique si la commande doit s'exécuter en arrière-plan.
     *
     * @var bool
     */
    public $runInBackground = false;

    /**
     * Le rappel du filtre.
     *
     * @var \Closure
     */
    protected $filter;

    /**
     * Le rappel de rejet.
     *
     * @var \Closure
     */
    protected $reject;

    /**
     * L'emplacement vers lequel la sortie doit être envoyée.
     *
     * @var string
     */
    public $output = '/dev/null';

    /**
     * Indique si la sortie doit être ajoutée.
     *
     * @var bool
     */
    protected $shouldAppendOutput = false;

    /**
     * Tableau de rappels à exécuter avant le démarrage de l'événement.
     *
     * @var array
     */
    protected $beforeCallbacks = array();

    /**
     * Tableau de rappels à exécuter une fois l'événement terminé.
     *
     * @var array
     */
    protected $afterCallbacks = array();

    /**
     * La description lisible par l’homme de l’événement.
     *
     * @var string
     */
    public $description;

    /**
     * L'implémentation du mutex.
     *
     * @var \Two\Console\Scheduling\Contracts\MutexInterface
     */
    public $mutex;


    /**
     * Créez une nouvelle instance d'événement.
     *
     * @param  \Two\Console\Scheduling\Contracts\MutexInterface  $mutex
     * @param  string  $command
     * @return void
     */
    public function __construct(Mutex $mutex, $command)
    {
        $this->mutex = $mutex;
        $this->command = $command;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Obtenez la sortie par défaut en fonction du système d'exploitation.
     *
     * @return string
     */
    protected function getDefaultOutput()
    {
        return windows_os() ? 'NUL' : '/dev/null';
    }

    /**
     * Exécutez l'événement donné.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function run(Container $container)
    {
        if ($this->withoutOverlapping && ! $this->mutex->create($this)) {
            return;
        }

        if ($this->runInBackground) {
            $this->runCommandInBackground($container);
        } else {
            $this->runCommandInForeground($container);
        }
    }

    /**
     * Exécutez la commande en arrière-plan.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    protected function runCommandInBackground(Container $container)
    {
        $this->callBeforeCallbacks($container);

        $process = new Process([$this->buildCommand()], base_path(), null, null, null);

        $process->disableOutput();

        $process->run();
    }

    /**
     * Exécutez la commande au premier plan.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    protected function runCommandInForeground(Container $container)
    {
        $this->callBeforeCallbacks($container);

        $process = new Process([$this->buildCommand()], base_path(), null, null, null);

        $process->run();

        $this->callAfterCallbacks($container);
    }

    /**
     * Appelez tous les rappels « avant » pour l'événement.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function callBeforeCallbacks(Container $container)
    {
        foreach ($this->beforeCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Appelez tous les rappels « après » pour l’événement.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function callAfterCallbacks(Container $container)
    {
        foreach ($this->afterCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Construisez la chaîne de commande.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = $this->compileCommand();

        if (! is_null($this->user) && ! windows_os()) {
            return 'sudo -u ' .$this->user .' -- sh -c \'' .$command .'\'';
        }

        return $command;
    }

    /**
     * Créez une chaîne de commande avec mutex.
     *
     * @return string
     */
    protected function compileCommand()
    {
        $output = ProcessUtils::escapeArgument($this->output);

        $redirect = $this->shouldAppendOutput ? ' >> ' : ' > ';

        if (! $this->runInBackground) {
            return $this->command .$redirect .$output .' 2>&1';
        }

        $delimiter = windows_os() ? '&' : ';';

        $phpBinary = ProcessUtils::escapeArgument(
            with(new PhpExecutableFinder)->find(false)
        );

        $forgeBinary = defined('FORGE_BINARY') ? ProcessUtils::escapeArgument(FORGE_BINARY) : 'forge';

        $finished = $phpBinary .' ' .$forgeBinary .' schedule:finish ' . ProcessUtils::escapeArgument($this->mutexName());

        return '(' .$this->command .$redirect .$output .' 2>&1 ' .$delimiter .' ' .$finished .') > '
            . ProcessUtils::escapeArgument($this->getDefaultOutput()) .' 2>&1 &';
    }

    /**
     * Obtenez le chemin mutex pour la commande planifiée.
     *
     * @return string
     */
    public function mutexName()
    {
        return storage_path('schedule-' .sha1($this->expression .$this->command));
    }

    /**
     * Déterminez si l'événement donné doit s'exécuter en fonction de l'expression Cron.
     *
     * @param  \Two\Application\Two  $app
     * @return bool
     */
    public function isDue(Two $app)
    {
        if (! $this->runsInMaintenanceMode() && $app->isDownForMaintenance()) {
            return false;
        }

        return $this->expressionPasses() && $this->filtersPass($app) && $this->runsInEnvironment($app->environment());
    }

    /**
     * Déterminez si l’expression Cron réussit.
     *
     * @return bool
     */
    protected function expressionPasses()
    {
        $date = Carbon::now();

        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    /**
     * Déterminez si les filtres réussissent pour l’événement.
     *
     * @param  \Two\Application\Two  $app
     * @return bool
     */
    protected function filtersPass(Two $app)
    {
        if (($this->filter && ! $app->call($this->filter)) || ($this->reject && $app->call($this->reject))) {
            return false;
        }

        return true;
    }

    /**
     * Déterminez si l’événement s’exécute dans l’environnement donné.
     *
     * @param  string  $environment
     * @return bool
     */
    public function runsInEnvironment($environment)
    {
        return empty($this->environments) || in_array($environment, $this->environments);
    }

    /**
     * Déterminez si l’événement s’exécute en mode maintenance.
     *
     * @return bool
     */
    public function runsInMaintenanceMode()
    {
        return $this->evenInMaintenanceMode;
    }

    /**
     * L'expression Cron représentant la fréquence de l'événement.
     *
     * @param  string  $expression
     * @return $this
     */
    public function cron($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Planifiez l'événement pour qu'il se déroule toutes les heures.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->cron('0 * * * * *');
    }

    /**
     * Planifiez l'événement pour qu'il se déroule quotidiennement.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->cron('0 0 * * * *');
    }

    /**
     * Planifiez la commande à une heure donnée.
     *
     * @param  string  $time
     * @return $this
     */
    public function at($time)
    {
        return $this->dailyAt($time);
    }

    /**
     * Planifiez l'événement pour qu'il se déroule quotidiennement à une heure donnée (10h00, 19h30, etc.).
     *
     * @param  string  $time
     * @return $this
     */
    public function dailyAt($time)
    {
        $segments = explode(':', $time);

        //
        $hours = (int) $segments[0];

        $minutes = (count($segments) == 2) ? (int) $segments[1] : '0';

        return $this->spliceIntoPosition(2, $hours)->spliceIntoPosition(1, $minutes);
    }

    /**
     * Planifiez l'événement pour qu'il se déroule deux fois par jour.
     *
     * @param  int  $first
     * @param  int  $second
     * @return $this
     */
    public function twiceDaily($first = 1, $second = 13)
    {
        $hours = $first .',' .$second;

        return $this->spliceIntoPosition(1, 0)->spliceIntoPosition(2, $hours);
    }

    /**
     * Planifiez l’événement pour qu’il se déroule chaque semaine.
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->cron('0 0 * * 0 *');
    }

    /**
     * Planifiez l’événement pour qu’il se déroule chaque semaine à un jour et à une heure donnés.
     *
     * @param  int  $day
     * @param  string  $time
     * @return $this
     */
    public function weeklyOn($day, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * Planifiez l’événement pour qu’il se déroule mensuellement.
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->cron('0 0 1 * * *');
    }

    /**
     * Planifiez l’événement pour qu’il se déroule chaque année.
     *
     * @return $this
     */
    public function yearly()
    {
        return $this->cron('0 0 1 1 * *');
    }

    /**
     * Planifiez l'événement pour qu'il se déroule toutes les minutes.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->cron('* * * * * *');
    }

    /**
     * Planifiez l'événement pour qu'il se déroule toutes les cinq minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->cron('*/5 * * * * *');
    }

    /**
     * Planifiez l'événement pour qu'il se déroule toutes les dix minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->cron('*/10 * * * * *');
    }

    /**
     * Planifiez l'événement pour qu'il se déroule toutes les trente minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->cron('0,30 * * * * *');
    }

    /**
     * Définissez les jours de la semaine pendant lesquels la commande doit être exécutée.
     *
     * @param  array|mixed  $days
     * @return $this
     */
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Définissez le fuseau horaire sur lequel la date doit être évaluée.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Indiquez que la commande doit s'exécuter en arrière-plan.
     *
     * @return $this
     */
    public function runInBackground()
    {
        $this->runInBackground = true;

        return $this;
    }

    /**
     * Définissez sous quel utilisateur la commande doit être exécutée.
     *
     * @param  string  $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Limitez les environnements dans lesquels la commande doit s'exécuter.
     *
     * @param  array|mixed  $environments
     * @return $this
     */
    public function environments($environments)
    {
        $this->environments = is_array($environments) ? $environments : func_get_args();

        return $this;
    }

    /**
     * Indiquez que la commande doit s’exécuter même en mode maintenance.
     *
     * @return $this
     */
    public function evenInMaintenanceMode()
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * Ne laissez pas les événements se chevaucher.
     *
     * @param  int  $expiresAt
     * @return $this
     */
    public function withoutOverlapping($expiresAt = 1440)
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->then(function ()
        {
            $this->mutex->forget($this);

        })->skip(function ()
        {
            return $this->mutex->exists($this);
        });
    }

    /**
     * Enregistrez un rappel pour filtrer davantage le calendrier.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function when(Closure $callback)
    {
        $this->filter = $callback;

        return $this;
    }

    /**
     * Enregistrez un rappel pour filtrer davantage le calendrier.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function skip(Closure $callback)
    {
        $this->reject = $callback;

        return $this;
    }

    /**
     * Envoyez la sortie de la commande à un emplacement donné.
     *
     * @param  string  $location
     * @param  bool  $append
     * @return $this
     */
    public function sendOutputTo($location, $append = false)
    {
        $this->output = $location;

        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Ajoutez la sortie de la commande à un emplacement donné.
     *
     * @param  string  $location
     * @return $this
     */
    public function appendOutputTo($location)
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * Envoyez par e-mail les résultats de l’opération programmée.
     *
     * @param  array|mixed  $addresses
     * @param  bool  $onlyIfOutputExists
     * @return $this
     *
     * @throws \LogicException
     */
    public function emailOutputTo($addresses, $onlyIfOutputExists = false)
    {
        $this->ensureOutputIsBeingCapturedForEmail();

        if (! is_array($addresses)) {
            $addresses = array($addresses);
        }

        return $this->then(function (Mailer $mailer) use ($addresses, $onlyIfOutputExists)
        {
            $this->emailOutput($mailer, $addresses, $onlyIfOutputExists);
        });
    }

    /**
     * Envoyez par courrier électronique les résultats de l’opération planifiée si elle produit une sortie.
     *
     * @param  array|mixed  $addresses
     * @return $this
     *
     * @throws \LogicException
     */
    public function emailWrittenOutputTo($addresses)
    {
        return $this->emailOutputTo($addresses, true);
    }

    /**
     * Assurez-vous que le résultat est capturé pour le courrier électronique.
     *
     * @return void
     */
    protected function ensureOutputIsBeingCapturedForEmail()
    {
        if (is_null($this->output) || ($this->output == $this->getDefaultOutput())) {
            $output = storage_path('logs/schedule-' .sha1($this->mutexName()) .'.log');

            $this->sendOutputTo($output);
        }
    }

    /**
     * Envoyez par courrier électronique le résultat de l’événement aux destinataires.
     *
     * @param  \Two\Contracts\Mail\Mailer  $mailer
     * @param  array  $addresses
     * @param  bool  $onlyIfOutputExists
     * @return void
     */
    protected function emailOutput(Mailer $mailer, $addresses, $onlyIfOutputExists = false)
    {
        $text = file_exists($this->output) ? file_get_contents($this->output) : '';

        if ($onlyIfOutputExists && empty($text)) {
            return;
        }

        $mailer->raw($text, function ($message) use ($addresses)
        {
            $message->subject($this->getEmailSubject());

            foreach ($addresses as $address) {
                $message->to($address);
            }
        });
    }

    /**
     * Obtenez la ligne d'objet de l'e-mail pour les résultats de sortie.
     *
     * @return string
     */
    protected function getEmailSubject()
    {
        if (isset($this->description)) {
            return __d('Two', 'Scheduled Job Output ({0})', $this->description);
        }

        return __d('Two', 'Scheduled Job Output');
    }

    /**
     * Enregistrez un rappel à appeler avant l'opération.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function before(Closure $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Enregistrez un rappel à rappeler après l'opération.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function after(Closure $callback)
    {
        return $this->then($callback);
    }

    /**
     * Enregistrez un rappel à rappeler après l'opération.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function then(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Définissez la description conviviale de l’événement.
     *
     * @param  string  $description
     * @return $this
     */
    public function name($description)
    {
        return $this->description($description);
    }

    /**
     * Définissez la description conviviale de l’événement.
     *
     * @param  string  $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Collez la valeur donnée dans la position donnée de l'expression.
     *
     * @param  int  $position
     * @param  string  $value
     * @return $this
     */
    protected function spliceIntoPosition($position, $value)
    {
        $segments = explode(' ', $this->expression);

        //
        $key = $position - 1;

        $segments[$key] = $value;

        return $this->cron(implode(' ', $segments));
    }

    /**
     * Obtenez le résumé de l’événement pour l’afficher.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return $this->buildCommand();
    }

    /**
     * Obtenez l'expression Cron pour l'événement.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    /**
     * Définissez l’implémentation du mutex à utiliser.
     *
     * @param  \Two\Console\Scheduling\Contracts\MutexInterface  $mutex
     * @return $this
     */
    public function preventOverlapsUsing(Mutex $mutex)
    {
        $this->mutex = $mutex;

        return $this;
    }
}
