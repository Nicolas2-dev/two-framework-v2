<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Forge\Packages\Commands\MakeCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class JobMakeCommand extends MakeCommand
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'package:make:job';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Package Job class';

    /**
     * Chaîne pour stocker le type de commande.
     *
     * @var string
     */
    protected $type = 'Job';

    /**
     * option de signature du package.
     *
     * @var array
     */
    protected $signOption = array(
        'queued',
    );

    /**
     * dossiers de packages à créer.
     *
     * @var array
     */
    protected $listFolders = array(
        'Jobs/',
    );

    /**
     * fichiers de package à créer.
     *
     * @var array
     */
    protected $listFiles = array(
        '{{filename}}.php',
    );

    /**
     * stubs de package utilisés pour remplir les fichiers définis.
     *
     * @var array
     */
    protected $listStubs = array(
        'default' => array(
            'job.stub',
        ),
        'queued' => array(
            'job-queued.stub',
        ),
    );

    /**
     * Résolvez le conteneur après avoir obtenu le chemin du fichier.
     *
     * @param string $filePath
     *
     * @return array
     */
    protected function resolveByPath($filePath)
    {
        $this->data['filename']  = $this->makeFileName($filePath);
        $this->data['namespace'] = $this->getNamespace($filePath);

        $this->data['path'] = $this->getBaseNamespace();

        $this->data['className'] = basename($filePath);
    }

    /**
     * Remplacez le texte de l'espace réservé par des valeurs correctes.
     *
     * @return string
     */
    protected function formatContent($content)
    {
        $searches = array(
            '{{filename}}',
            '{{path}}',
            '{{namespace}}',
            '{{className}}',
        );

        $replaces = array(
            $this->data['filename'],
            $this->data['path'],
            $this->data['namespace'],
            $this->data['className'],
        );

        return str_replace($searches, $replaces, $content);
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::REQUIRED, 'The slug of the Package.'),
            array('name', InputArgument::REQUIRED, 'The name of the Event class.'),
        );
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('queued', null, InputOption::VALUE_NONE, 'Indicates that Job should be queued.'),
        );
    }
}
