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
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:finish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle the completion of a scheduled command';

    /**
     * The schedule instance.
     *
     * @var \Two\Console\Scheduling\Schedule
     */
    protected $schedule;


    /**
     * Create a new command instance.
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
     * Execute the console command.
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
     * Get the console command arguments.
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
