<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Localization\Commands;

use Exception;
use Throwable;

use Two\Config\Repository as Config;
use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;
use Two\Localization\LanguageManager;
use Two\Support\Arr;

use Symfony\Component\Console\Input\InputOption;


class LanguagesUpdateCommand extends Command
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'language:update';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Update the Language files';

    /**
     * L'instance du gestionnaire de langues.
     *
     * @var LanguageManager
     */
    protected $languages;

    /**
     * L'instance du système de fichiers.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * @var string
     */
    protected static $appPattern = '#__\(\'(.*)\'(?:,.*)?\)#smU';

    /**
     * @var string
     */
    protected static $pattern = '#__d\(\'(?:.*)?\',.?\s?\'(.*)\'(?:,.*)?\)#smU';


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param Filesystem $files
     */
    public function __construct(LanguageManager $languages, Filesystem $files)
    {
        parent::__construct();

        //
        $this->languages = $languages;

        $this->files = $files;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        $config = $this->container['config'];

        // Obtenez les codes de langue.
        $languages = array_keys(
            $config->get('languages', array())
        );

        if ($this->option('path')) {
            $path = $this->option('path');

            if (! $this->files->isDirectory($path)) {
                return $this->error('Not a directory: "' .$path .'"');
            } else if (! $this->files->isDirectory($path .DS .'Language')) {
                return $this->error('Not a translatable path: "' .$path .'"');
            }

            return $this->updateLanguageFiles($path, $languages);
        }

        // Aucun répertoire personnalisé n'a été spécifié.
        else {
            $paths = $this->scanWorkPaths($config);
        }

        // Mettez à jour les fichiers de langue dans les domaines disponibles.
        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            } else if (! $this->files->isDirectory($path .DS .'Language')) {
                $this->comment('Not a translatable path: "' .$path .'"');

                continue;
            }

            $this->updateLanguageFiles($path, $languages);
        }
    }

    protected function scanWorkPaths(Config $config)
    {
        $result = array(
            app_path(),
            base_path('shared')
        );

        // Recherchez les modules et les thèmes.
        $paths = array(
            $config->get('packages.modules.path', BASEPATH .'modules'),
            $config->get('packages.themes.path', BASEPATH .'themes')
        );

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            $result = array_merge(
                $result, $this->files->glob($path .'/*', GLOB_ONLYDIR)
            );
        }

        // Recherchez les packages locaux.
        $path = BASEPATH .'packages';

        if ($this->files->isDirectory($path)) {
            $result = array_merge($result, array_map(function ($path)
            {
                return $path .DS .'src';

            }, $this->files->glob($path .'/*', GLOB_ONLYDIR)));
        }

        return $result;
    }

    protected function updateLanguageFiles($path, $languages)
    {
        $withoutDomain = ($path == app_path());

        $pattern = $withoutDomain ? "__('" : "__d('";

        if (empty($paths = $this->fileGrep($pattern, $path))) {
            $this->comment(PHP_EOL .'No messages found in path: "' .$path .'"');

            return;
        }

        // Extrayez les messages des fichiers.
        $messages = $this->extractMessages($paths, $withoutDomain);

        if (empty($messages)) {
            return;
        }

        $this->info(PHP_EOL .'Processing the messages found in path: "' .$path .'"');

        foreach ($languages as $language) {
            $this->updateLanguageFile($language, $path, array_unique($messages));
        }
    }

    protected function fileGrep($pattern, $path)
    {
        $result = array();

        $fp = opendir($path);

        while ($fileName = readdir($fp)) {
            if (preg_match("#^\.+$#", $fileName) === 1) {
                // Ignorez les liens symboliques.
                continue;
            }

            $fullPath = $path .DS .$fileName;

            if ($this->files->isDirectory($fullPath)) {
                $result = array_merge($result, $this->fileGrep($pattern, $fullPath));
            }

            // Le chemin actuel n'est pas un répertoire.
            else if (preg_match("#^(.*)\.(php|tpl)$#", $fileName) !== 1) {
                continue;
            }

            // Nous avons trouvé un fichier PHP ou TPL.
            else if (stristr(file_get_contents($fullPath), $pattern)) {
                $result[] = $fullPath;
            }
        }

        return array_unique($result);
    }

    protected function extractMessages(array $paths, $withoutDomain)
    {
        if ($withoutDomain) {
            $pattern = '#__\(\'(.*)\'(?:,.*)?\)#smU';
        } else {
            $pattern = '#__d\(\'(?:.*)?\',.?\s?\'(.*)\'(?:,.*)?\)#smU';
        }

        $this->comment("Using PATERN: '" .$pattern."'");

        // Extrayez les messages des fichiers et renvoyez-les.
        $result = array();

        foreach ($paths as $path) {
            $content = $this->getFileContents($path);

            if (preg_match_all($pattern, $content, $matches) !== false) {
                if (empty($messages = $matches[1])) {
                    continue;
                }

                foreach ($messages as $message) {
                    //$message = trim($message);

                    if ($message == '$msg, $args = null') {
                        // Nous sauterons la définition des fonctions de traduction.
                        continue;
                    }

                    $result[] = str_replace("\\'", "'", $message);
                }
            }
        }

        return $result;
    }

    protected function getFileContents($path)
    {
        try {
            return $this->files->get($path);
        }
        catch (Exception $e) {
            //
        }
        catch (Throwable $e) {
            //
        }

        return '';
    }

    protected function updateLanguageFile($language, $path, array $messages)
    {
        $path = $path .str_replace('/', DS, '/Language/' .strtoupper($language) .'/messages.php');

        $data = $this->getMessagesFromFile($path);

        //
        $result = array();

        foreach ($messages as $key) {
            $result[$key] = Arr::get($data, $key, '');
        }

        $this->writeLanguageFile($path, $result);

        $this->line('Written the Language file: "' .str_replace(BASEPATH, '', $path) .'"');
    }

    protected function getMessagesFromFile($path)
    {
        $data = array();

        try {
            $data = $this->files->getRequire($path);
        }
        catch (Exception $e) {
            //
        }
        catch (Throwable $e) {
            //
        }

        return is_array($data) ? $data : array();
    }

    protected function writeLanguageFile($path, array $data)
    {
        ksort($data);

        // Assurez-vous que le répertoire existe.
        $this->files->makeDirectory(dirname($path), 0755, true, true);

        // Préparez le contenu du fichier de langue.
        $output = "<?php\n\nreturn " .var_export($data, true) .";\n";

        //$output = preg_replace("/^ {2}(.*)$/m","    $1", $output);

        $this->files->put($path, $output);
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('path', null, InputOption::VALUE_REQUIRED, 'Indicates the path from where translation strings are processed.'),
        );
    }
}
