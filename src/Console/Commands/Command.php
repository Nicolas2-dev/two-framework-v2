<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;


class Command extends \Symfony\Component\Console\Command\Command
{
    /**
     * L'instance de deux applications.
     *
     * @var \Two\Application\Two
     */
    protected $container;

    /**
     * L’implémentation de l’interface d’entrée.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * L’implémentation de l’interface de sortie.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name;

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description;

    /**
     * Créez une nouvelle instance de commande de console.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct($this->name);

        // Nous allons continuer et définir le nom, la description et les paramètres sur la console
        // commandes juste pour rendre les choses un peu plus faciles pour le développeur. C'est
        // afin qu'ils n'aient pas besoin d'être tous spécifiés manuellement dans les constructeurs.
        $this->setDescription((string) $this->description);

        $this->specifyParameters();
    }

    /**
     * Spécifiez les arguments et les options de la commande.
     *
     * @return void
     */
    protected function specifyParameters()
    {
        // Nous allons parcourir tous les arguments et options de la commande et
        // les définit tous sur l'instance de commande de base. Ceci précise ce qui peut obtenir
        // passé dans ces commandes en tant que "paramètres" pour contrôler l'exécution.
        foreach ($this->getArguments() as $arguments) {
            call_user_func_array(array($this, 'addArgument'), $arguments);
        }

        foreach ($this->getOptions() as $options) {
            call_user_func_array(array($this, 'addOption'), $options);
        }
    }

    /**
     * Exécutez la commande console.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;

        $this->output = $output;
        try {
            return parent::run($input, $output);
        } finally {
            return 0;
        }
    }

    /**
     * Exécutez la commande de la console.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //return $this->handle();
        $method = method_exists($this, 'handle') ? 'handle' : 'fire';

        return $this->container->call([$this, $method]);
    }

    /**
     * Appelez une autre commande de console.
     *
     * @param  string  $command
     * @param  array   $arguments
     * @return int
     */
    public function call($command, array $arguments = array())
    {
        $instance = $this->getApplication()->find($command);

        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), $this->output);
    }

    /**
     * Appelez une autre commande de console en silence.
     *
     * @param  string  $command
     * @param  array   $arguments
     * @return int
     */
    public function callSilent($command, array $arguments = array())
    {
        $instance = $this->getApplication()->find($command);

        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), new NullOutput);
    }

    /**
     * Obtenez la valeur d'un argument de commande.
     *
     * @param  string  $key
     * @return string|array
     */
    public function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Obtenez la valeur d'une option de commande.
     *
     * @param  string  $key
     * @return string|array
     */
    public function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Confirmez une question avec l'utilisateur.
     *
     * @param  string  $question
     * @param  bool    $default
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new ConfirmationQuestion("<question>{$question}</question> ", $default);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Inviter l'utilisateur à entrer des données.
     *
     * @param  string  $question
     * @param  string  $default
     * @return string
     */
    public function ask($question, $default = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>", $default);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Inviter l’utilisateur à saisir une saisie avec saisie semi-automatique.
     *
     * @param  string  $question
     * @param  array   $choices
     * @param  string  $default
     * @return string
     */
    public function askWithCompletion($question, array $choices, $default = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>", $default);

        $question->setAutocompleterValues($choices);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Inviter l'utilisateur à saisir sa réponse mais masquer la réponse de la console.
     *
     * @param  string  $question
     * @param  bool    $fallback
     * @return string
     */
    public function secret($question, $fallback = true)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>");

        $question->setHidden(true)->setHiddenFallback($fallback);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Donnez à l'utilisateur un choix unique parmi un éventail de réponses.
     *
     * @param  string  $question
     * @param  array   $choices
     * @param  string  $default
     * @param  mixed   $attempts
     * @param  bool    $multiple
     * @return bool
     */
    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new ChoiceQuestion("<question>$question</question>", $choices, $default);

        $question->setMaxAttempts($attempts)->setMultiselect($multiple);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Formater l'entrée dans un tableau textuel
     *
     * @param  array   $headers
     * @param  array   $rows
     * @param  string  $style
     * @return void
     */
    public function table(array $headers, array $rows, $style = 'default')
    {
        $table = new Table($this->output);

        $table->setHeaders($headers)->setRows($rows)->setStyle($style)->render();
    }

    /**
     * Écrivez une chaîne comme sortie d'informations.
     *
     * @param  string  $string
     * @return void
     */
    public function info($string)
    {
        $this->output->writeln("<info>$string</info>");
    }

    /**
     * Écrivez une chaîne comme sortie standard.
     *
     * @param  string  $string
     * @return void
     */
    public function line($string)
    {
        $this->output->writeln($string);
    }

    /**
     * Écrivez une chaîne comme sortie de commentaire.
     *
     * @param  string  $string
     * @return void
     */
    public function comment($string)
    {
        $this->output->writeln("<comment>$string</comment>");
    }

    /**
     * Écrivez une chaîne comme sortie de question.
     *
     * @param  string  $string
     * @return void
     */
    public function question($string)
    {
        $this->output->writeln("<question>$string</question>");
    }

    /**
     * Écrivez une chaîne comme sortie d'erreur.
     *
     * @param  string  $string
     * @return void
     */
    public function error($string)
    {
        $this->output->writeln("<error>$string</error>");
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

    /**
     * Obtenez l’implémentation de sortie.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Obtenez l’instance de deux applications.
     *
     * @return \Two\Application\Two
     */
    public function getTwo()
    {
        return $this->container;
    }

    /**
     * Définissez l’instance de deux applications.
     *
     * @param  \Two\Application\Two  $Two
     * @return void
     */
    public function setContainer($Two)
    {
        $this->container = $Two;
    }

}
