<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation;

use Closure;

use Two\Config\Repository as Config;
use Two\Support\Str;
use Two\Validation\DatabasePresenceVerifier;


class Factory
{
    /**
     * L'instance de configuration.
     *
     * @var \Two\Config\Repository
     */
    protected $config;

    /**
     * Instance du vérificateur de présence de base de données.
     *
     * @var \Two\Validation\DatabasePresenceVerifier
     */
    protected $verifier;

    /**
     * Toutes les extensions de validateur personnalisées.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * Toutes les extensions de validateur implicites personnalisées.
     *
     * @var array
     */
    protected $implicitExtensions = array();

    /**
     * Tous les remplaçants de messages du validateur personnalisé.
     *
     * @var array
     */
    protected $replacers = array();

    /**
     * Tous les messages de secours pour les règles personnalisées.
     *
     * @var array
     */
    protected $fallbackMessages = array();

    /**
     * L'instance du résolveur Validator.
     *
     * @var Closure
     */
    protected $resolver;


    /**
     * Créez une nouvelle instance de Validator Factory.
     *
     * @param  \Two\Validation\Translator  $translator
     * @return void
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Créez une nouvelle instance de validateur.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @return \Two\Validation\Validator
     */
    public function make(array $data, array $rules, array $messages = array(), array $customAttributes = array())
    {
        $validator = $this->resolve($data, $rules, $messages, $customAttributes);

        if (! is_null($this->verifier)) {
            $validator->setPresenceVerifier($this->verifier);
        }

        $this->addExtensions($validator);

        return $validator;
    }

    /**
     * Ajoutez les extensions à une instance de validateur.
     *
     * @param  \Two\Validation\Validator  $validator
     * @return void
     */
    protected function addExtensions($validator)
    {
        $validator->addExtensions($this->extensions);

        $implicit = $this->implicitExtensions;

        $validator->addImplicitExtensions($implicit);

        $validator->addReplacers($this->replacers);

        $validator->setFallbackMessages($this->fallbackMessages);
    }

    /**
     * Résolvez une nouvelle instance de Validator.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @return \Two\Validation\Validator
     */
    protected function resolve($data, $rules, $messages, $customAttributes)
    {
        if (is_null($this->resolver)) {
            return new Validator($this->config, $data, $rules, $messages, $customAttributes);
        } else {
            return call_user_func($this->resolver, $this->config, $data, $rules, $messages, $customAttributes);
        }
    }

    /**
     * Enregistrez une extension de validateur personnalisée.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @param  string  $message
     * @return void
     */
    public function extend($rule, $extension, $message = null)
    {
        $this->extensions[$rule] = $extension;

        if ($message !== null) {
            $rule = Str::snake($rule);

            $this->fallbackMessages[$rule] = $message;
        }
    }

    /**
     * Enregistrez une extension Validator implicite personnalisée.
     *
     * @param  string   $rule
     * @param  \Closure|string  $extension
     * @param  string  $message
     * @return void
     */
    public function extendImplicit($rule, $extension, $message = null)
    {
        $this->implicitExtensions[$rule] = $extension;

        if ($message !== null) {
            $rule = Str::snake($rule);

            $this->fallbackMessages[$rule] = $message;
        }
    }

    /**
     * Enregistrez un remplacement de message de validation implicite personnalisé.
     *
     * @param  string   $rule
     * @param  \Closure|string  $replacer
     * @return void
     */
    public function replacer($rule, $replacer)
    {
        $this->replacers[$rule] = $replacer;
    }

    /**
     * Définissez le résolveur d'instance Validator.
     *
     * @param  Closure  $resolver
     * @return void
     */
    public function resolver(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Obtenez l'instance de configuration.
     *
     * @return \Two\Config\Repository
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Obtenez l’instance du vérificateur de présence de base de données.
     *
     * @return \Two\Validation\DatabasePresenceVerifier
     */
    public function getPresenceVerifier()
    {
        return $this->verifier;
    }

    /**
     * Définissez l’implémentation du vérificateur de présence de base de données.
     *
     * @param  \Two\Validation\DatabasePresenceVerifier  $presenceVerifier
     * @return void
     */
    public function setPresenceVerifier(DatabasePresenceVerifier $presenceVerifier)
    {
        $this->verifier = $presenceVerifier;
    }
}
