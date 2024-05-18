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


class ConsoleMakeCommand extends MakeCommand
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'package:make:console';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Package Forge Command class';

    /**
     * Chaîne pour stocker le type de commande.
     *
     * @var string
     */
    protected $type = 'Command';

    /**
     * dossiers de packages à créer.
     *
     * @var array
     */
    protected $listFolders = array(
        'Console/Commands/',
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
            'console.stub',
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
        $this->data['path']      = $this->getBaseNamespace();
        $this->data['className'] = basename($filePath);

        //
        $this->data['command'] = $this->option('command');
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
            '{{command}}',
        );

        $replaces = array(
            $this->data['filename'],
            $this->data['path'],
            $this->data['namespace'],
            $this->data['className'],
            $this->data['command'],
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
            array('slug', InputArgument::REQUIRED, 'The slug of the package.'),
            array('name', InputArgument::REQUIRED, 'The name of the Model class.'),
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
            array('command', null, InputOption::VALUE_OPTIONAL, 'The terminal command that should be assigned.', 'command:name'),
        );
    }
}
