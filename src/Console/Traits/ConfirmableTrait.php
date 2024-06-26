<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Traits;

use Closure;


trait ConfirmableTrait
{
    /**
     * Confirmez avant de poursuivre l'action
     *
     * @param  string    $warning
     * @param  \Closure  $callback
     * @return bool
     */
    public function confirmToProceed($warning = 'Application In Production!', Closure $callback = null)
    {
        $shouldConfirm = $callback ?: $this->getDefaultConfirmCallback();

        if (call_user_func($shouldConfirm))
        {
            if ($this->option('force')) return true;

            $this->comment(str_repeat('*', strlen($warning) + 12));
            $this->comment('*     '.$warning.'     *');
            $this->comment(str_repeat('*', strlen($warning) + 12));
            $this->output->writeln('');

            $confirmed = $this->confirm('Do you really wish to run this command?');

            if (! $confirmed) {
                $this->comment('Command Cancelled!');

                return false;
            }
        }

        return true;
    }

    /**
     * Obtenez le rappel de confirmation par défaut.
     *
     * @return \Closure
     */
    protected function getDefaultConfirmCallback()
    {
        return function() { return $this->getTwo()->environment() == 'production'; };
    }

}
