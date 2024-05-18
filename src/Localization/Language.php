<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Localization;

use Two\Localization\LanguageManager;
use Two\Localization\MessageFormatter;


/**
 * Une classe Language pour charger le fichier de langue demandé.
 */
class Language
{
    
    /**
     * L'instance du gestionnaire de langues.
     *
     * @var \Two\Localization\LanguageManager
     */
    protected $manager;

    /**
     * Contient un tableau avec les messages du domaine.
     *
     * @var array
     */
    private $messages = array();

    /**
     * Le domaine linguistique actuel.
     */
    private $domain = null;

    /**
     * Les informations de langue actuelles.
     */
    private $code      = 'en';
    private $info      = 'English';
    private $name      = 'English';
    private $locale    = 'en-US';
    private $direction = 'ltr';


    /**
     * Créez une nouvelle instance de langage.
     *
     * @param string $domain
     * @param string $code
     * @param string $path
     */
    public function __construct(LanguageManager $manager, $domain, $code)
    {
        $languages = $manager->getLanguages();

        if (isset($languages[$code]) && ! empty($languages[$code])) {
            $info = $languages[$code];

            $this->code = $code;

            //
            $this->info      = $info['info'];
            $this->name      = $info['name'];
            $this->locale    = $info['locale'];
            $this->direction = $info['dir'];
        } else {
            $code = 'en';
        }

        $this->domain = $domain;

        // Déterminez le chemin actuel du fichier de langue.
        $namespaces = $manager->getNamespaces();

        // Vérifiez si le fichier de langue est lisible.
        if (! array_key_exists($domain, $namespaces)) return;

        // Déterminez le chemin de la ou des langues.
        $namespace = $namespaces[$domain];

        $filePath = $namespace .DS .strtoupper($code) .DS .'messages.php';

        if (is_readable($filePath)) {
            // Le fichier de langue demandé existe ; récupérer les messages de celui-ci.
            $messages = require $filePath;

            // Une vérification de cohérence des messages, avant de les paramétrer.
            if (is_array($messages) && ! empty($messages)) {
                $this->messages = $messages;
            }
        }
    }

    /**
     * Traduire un message avec une mise en forme facultative
     * @param string $message Message original.
     * @param array $params Paramètres facultatifs pour le formatage.
     * @return string
     */
    public function translate($message, array $params = array())
    {
        // Mettez à jour le message actuel avec la traduction du domaine, si nous en avons une.
        if (isset($this->messages[$message]) && ! empty($this->messages[$message])) {
            $message = $this->messages[$message];
        }

        if (empty($params)) {
            return $message;
        }

        $formatter = new MessageFormatter();

        return $formatter->format($message, $params, $this->locale);
    }

    /**
     * Obtenir le domaine actuel
     * @return string
     */
    public function domain()
    {
        return $this->domain;
    }

    /**
     * Obtenir le code actuel
     * @return string
     */
    public function code()
    {
        return $this->code;
    }

    /**
     * Obtenir des informations actuelles
     * @return string
     */
    public function info()
    {
        return $this->info;
    }

    /**
     * Obtenir le nom actuel
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Obtenir les paramètres régionaux actuels
     * @return string
     */
    public function locale()
    {
        return $this->locale;
    }

    /**
     * Recevez tous les messages
     * @return array
     */
    public function messages()
    {
        return $this->messages;
    }

    /**
     * Obtenez la direction actuelle
     *
     * @return string rtl or ltr
     */
    public function direction()
    {
        return $this->direction;
    }

}
