<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Support\Str;
use Two\Console\Forge\Packages\Commands\MakeCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class MigrationMakeCommand extends MakeCommand
{
    /**
     * Le nom et la signature de la commande de console.
     *
     * @var string
     */
    protected $name = 'package:make:migration';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Package Migration file';

    /**
     * Chaîne pour stocker le type de commande.
     *
     * @var string
     */
    protected $type = 'Migration';

    /**
     * Dossiers de packages à créer.
     *
     * @var array
     */
    protected $listFolders = array(
        'Database/Migrations/',
    );

    /**
     * Fichiers de package à créer.
     *
     * @var array
     */
    protected $listFiles = array(
        '{{filename}}.php',
    );

    /**
     * Option de signature du package.
     *
     * @var array
     */
    protected $signOption = array(
        'create',
        'table',
    );

    /**
     * Stubs de package utilisés pour remplir les fichiers définis.
     *
     * @var array
     */
    protected $listStubs = array(
        'default' => array(
            'migration.stub',
        ),
        'create' => array(
            'migration_create.stub',
        ),
        'table' => array(
            'migration_table.stub',
        ),
    );

    /**
     * Résolvez le conteneur après avoir obtenu le chemin du fichier.
     *
     * @param string $FilePath
     *
     * @return array
     */
    protected function resolveByPath($filePath)
    {
        $this->data['filename'] = $this->makeFileName($filePath);

        $this->data['className'] = basename($filePath);
        $this->data['tableName'] = 'dummy';
    }

    /**
     * Résolvez le conteneur après avoir obtenu l’option de saisie.
     *
     * @param string $option
     *
     * @return array
     */
    protected function resolveByOption($option)
    {
        $this->data['tableName'] = $option;
    }

    /**
     * Créez un nom de fichier.
     *
     * @param string $filePath
     *
     * @return string
     */
    protected function makeFileName($filePath)
    {
        return date('Y_m_d_His') .'_' .strtolower(Str::snake(basename($filePath)));
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
            '{{className}}',
            '{{tableName}}',
        );

        $replaces = array(
            $this->data['filename'],
            $this->data['className'],
            $this->data['tableName'],
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
            array('name', InputArgument::REQUIRED, 'The name of the Migration.'),
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
            array('--create', null, InputOption::VALUE_OPTIONAL, 'The table to be created.'),
            array('--table',  null, InputOption::VALUE_OPTIONAL, 'The table to migrate.'),
        );
    }
}
