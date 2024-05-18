<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Scheduling;

use Two\Console\Commands\Command;


class ScheduleRunCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'schedule:run';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';

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
        $events = $this->schedule->dueEvents($this->container);

        foreach ($events as $event) {
            $this->line('<info>Running scheduled command:</info> ' .$event->getSummaryForDisplay());

            $event->run($this->container);
        }

        if (count($events) === 0) {
            $this->info('No scheduled commands are ready to run.');
        }
    }
}
