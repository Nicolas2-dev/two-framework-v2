<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling;

use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputArgument;


class ScheduleFinishCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'schedule:finish';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';

    /**
     * L’instance de planification.
     *
     * @var \Two\Console\Scheduling\Schedule
     */
    protected $schedule;


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param  \Two\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;

        parent::__construct();
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $id = $this->argument('id');

        $events = collect($this->schedule->events())->filter(function ($event) use ($id)
        {
            return ($event->mutexName() == $id);
        });

        $events->each(function ($event)
        {
            $event->callAfterCallbacks($this->container);
        });
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('id', InputArgument::REQUIRED, 'The schedule ID'),
        );
    }
}
