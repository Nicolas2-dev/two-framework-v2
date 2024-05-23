<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation;

use Closure;
use DateTime;
use Countable;
use Exception;
use DateTimeZone;
use BadMethodCallException;
use InvalidArgumentException;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Support\Fluent;
use Two\Support\MessageBag;
use Two\Container\Container;
use Two\Config\Repository as Config;
use Two\Application\Contracts\MessageProviderInterface;
use Two\Validation\Contracts\PresenceVerifierInterface;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class Validator implements MessageProviderInterface
{
    /**
     * L'instance de configuration.
     *
     * @var \Two\Config\Repository
     */
    protected $config;

    /**
     * L'instance de conteneur.
     *
     * @var \Two\Container\Container
     */    
    protected $container;

    /**
     * L’implémentation du vérificateur de présence.
     *
     * @var \Two\Validation\Contracts\PresenceVerifierInterface
     */
    protected $presenceVerifier;

    /**
     * Les règles de validation ayant échoué.
     *
     * @var array
     */
    protected $failedRules = array();

    /**
     * L'instance du sac de messages.
     *
     * @var \Two\Support\MessageBag
     */
    protected $messages;

    /**
     * Les données en cours de validation.
     *
     * @var array
     */
    protected $data;

    /**
     * Les dossiers en cours de validation.
     *
     * @var array
     */
    protected $files = array();

    /**
     * Les règles à appliquer aux données.
     *
     * @var array
     */
    protected $rules;

    /**
     * Le tableau de messages d’erreur personnalisés.
     *
     * @var array
     */
    protected $customMessages = array();

    /**
     * Le tableau des messages d’erreur de secours.
     *
     * @var array
     */
    protected $fallbackMessages = array();

    /**
     * Le tableau de noms d’attributs personnalisés.
     *
     * @var array
     */
    protected $customAttributes = array();

    /**
     * Le tableau de valeurs affichées personnalisées.
     *
     * @var array
     */
    protected $customValues = array();

    /**
     * Toutes les extensions de validateur personnalisées.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * Toutes les extensions de remplacement personnalisées.
     *
     * @var array
     */
    protected $replacers = array();

    /**
     * Les règles de validation liées à la taille.
     *
     * @var array
     */
    protected $sizeRules = array('Size', 'Between', 'Min', 'Max');

    /**
     * Les règles de validation liées aux valeurs numériques.
     *
     * @var array
     */
    protected $numericRules = array('Numeric', 'Integer');

    /**
     * Les règles de validation qui impliquent que le champ est obligatoire.
     *
     * @var array
     */
    protected $implicitRules = array(
        'Required', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll', 'RequiredIf', 'Accepted'
    );


    /**
     * Créez une nouvelle instance de validateur.
     *
     * @param  \Two\Config\Repository  $config
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return void
     */
    public function __construct(Config $config, array $data, array $rules, array $messages = array(), array $customAttributes = array())
    {
        $this->config = $config;

        $this->customMessages = $messages;

        $this->data  = $this->parseData($data);
        $this->rules = $this->explodeRules($rules);

        $this->customAttributes = $customAttributes;
    }

    /**
     * Analysez les données et hydratez le tableau de fichiers.
     *
     * @param  array   $data
     * @param  string  $arrayKey
     * @return array
     */
    protected function parseData(array $data, $arrayKey = null)
    {
        if (is_null($arrayKey)) {
            $this->files = array();
        }

        foreach ($data as $key => $value) {
            $key = ($arrayKey) ? "$arrayKey.$key" : $key;

            // Si cette valeur est une instance de la classe HttpFoundation File, nous le ferons
            // le supprime du tableau data et l'ajoute au tableau files, ce qui
            // nous utilisons pour séparer facilement ces fichiers des autres données.
            if ($value instanceof File) {
                $this->files[$key] = $value;

                unset($data[$key]);
            } else if (is_array($value)) {
                $this->parseData($value, $key);
            }
        }

        return $data;
    }

    /**
     * Décomposez les règles en un ensemble de règles.
     *
     * @param  string|array  $rules
     * @return array
     */
    protected function explodeRules($rules)
    {
        foreach ($rules as $key => $rule) {
            $rules[$key] = is_string($rule) ? explode('|', $rule) : $rule;
        }

        return $rules;
    }

    /**
     * Ajoutez des conditions à un champ donné en fonction d'une fermeture.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @param  callable  $callback
     * @return void
     */
    public function sometimes($attribute, $rules, callable $callback)
    {
        $payload = new Fluent(array_merge($this->data, $this->files));

        if (call_user_func($callback, $payload)) {
            foreach ((array) $attribute as $key) {
                $this->mergeRules($key, $rules);
            }
        }
    }

    /**
     * Définissez un ensemble de règles qui s'appliquent à chaque élément d'un attribut de tableau.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function each($attribute, $rules)
    {
        if (! is_array($rules)) {
            $rules = array($rules);
        }

        $data = Arr::get($this->data, $attribute, array());

        if (! is_array($data)) {
            if ($this->hasRule($attribute, 'Array')) {
                return;
            }

            throw new InvalidArgumentException('Attribute for each() must be an array.');
        }

        foreach ($data as $dataKey => $dataValue) {
            foreach ($rules as $ruleValue) {
                $this->mergeRules("$attribute.$dataKey", $ruleValue);
            }
        }
    }

    /**
     * Fusionnez des règles supplémentaires dans un attribut donné.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return void
     */
    public function mergeRules($attribute, $rules)
    {
        $current = isset($this->rules[$attribute]) ? $this->rules[$attribute] : array();

        $merge = head($this->explodeRules(array($rules)));

        $this->rules[$attribute] = array_merge($current, $merge);
    }

    /**
     * Déterminez si les données satisfont aux règles de validation.
     *
     * @return bool
     */
    public function passes()
    {
        $this->messages = new MessageBag;

        // Nous passerons en revue chaque règle, en validant les attributs qui y sont attachés
        // règle. Tous les messages d'erreur seront ajoutés aux conteneurs avec chacun des
        // les autres messages d'erreur, renvoyant vrai si nous n'avons pas de messages.
        foreach ($this->rules as $attribute => $rules) {
            foreach ($rules as $rule) {
                $this->validate($attribute, $rule);
            }
        }

        return count($this->messages->all()) === 0;
    }

    /**
     * Déterminez si les données ne respectent pas les règles de validation.
     *
     * @return bool
     */
    public function fails()
    {
        return ! $this->passes();
    }

    /**
     * Valider un attribut donné par rapport à une règle.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return void
     */
    protected function validate($attribute, $rule)
    {
        list($rule, $parameters) = $this->parseRule($rule);

        if ($rule == '') {
            return;
        }

        // Nous obtiendrons la valeur de l'attribut donné à partir du tableau de données, puis
        // vérifie que l'attribut est bien validable. Sauf si la règle implique
        // que l'attribut est requis, les règles ne sont pas exécutées pour les valeurs manquantes.
        $value = $this->getValue($attribute);

        $validatable = $this->isValidatable($rule, $attribute, $value);

        $method = "validate{$rule}";

        if ($validatable && ! $this->$method($attribute, $value, $parameters, $this)) {
            $this->addFailure($attribute, $rule, $parameters);
        }
    }

    /**
     * Renvoie les données qui étaient valides.
     *
     * @return array
     */
    public function valid()
    {
        if (! $this->messages) {
            $this->passes();
        }

        return array_diff_key($this->data, $this->messages()->toArray());
    }

    /**
     * Renvoie les données invalides.
     *
     * @return array
     */
    public function invalid()
    {
        if (! $this->messages) {
            $this->passes();
        }

        return array_intersect_key($this->data, $this->messages()->toArray());
    }

    /**
     * Obtenez la valeur d'un attribut donné.
     *
     * @param  string  $attribute
     * @return mixed
     */
    protected function getValue($attribute)
    {
        if (! is_null($value = Arr::get($this->data, $attribute))) {
            return $value;
        } else if (! is_null($value = Arr::get($this->files, $attribute))) {
            return $value;
        }
    }

    /**
     * Déterminez si l'attribut est validable.
     *
     * @param  string  $rule
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function isValidatable($rule, $attribute, $value)
    {
        return $this->presentOrRuleIsImplicit($rule, $attribute, $value) &&
               $this->passesOptionalCheck($attribute) &&
               $this->hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute);
    }

    /**
     * Déterminez si le champ est présent ou si la règle implique qu'il est obligatoire.
     *
     * @param  string  $rule
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function presentOrRuleIsImplicit($rule, $attribute, $value)
    {
        return $this->validateRequired($attribute, $value) || $this->isImplicit($rule);
    }

    /**
     * Déterminez si l’attribut réussit une vérification facultative.
     *
     * @param  string  $attribute
     * @return bool
     */
    protected function passesOptionalCheck($attribute)
    {
        if (! $this->hasRule($attribute, array('Sometimes'))) {
            return true;
        }

        return array_key_exists($attribute, Arr::dot($this->data))
            || in_array($attribute, array_keys($this->data))
            || array_key_exists($attribute, $this->files);
    }

    /**
     * Déterminez si une règle donnée implique que l'attribut est requis.
     *
     * @param  string  $rule
     * @return bool
     */
    protected function isImplicit($rule)
    {
        return in_array($rule, $this->implicitRules);
    }

    /**
     * Déterminez s'il s'agit d'une validation de présence nécessaire.
     *
     * Ceci permet d'éviter d'éventuelles erreurs de comparaison des types de bases de données.
     *
     * @param  string  $rule
     * @param  string  $attribute
     * @return bool
     */
    protected function hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute)
    {
        $rules = array('Unique', 'Exists');

        return in_array($rule, $rules) ? ! $this->messages->has($attribute) : true;
    }

    /**
     * Ajoutez une règle ayant échoué et un message d'erreur à la collection.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return void
     */
    protected function addFailure($attribute, $rule, $parameters)
    {
        $this->addError($attribute, $rule, $parameters);

        $this->failedRules[$attribute][$rule] = $parameters;
    }

    /**
     * Ajoutez un message d'erreur à la collection de messages du validateur.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return void
     */
    protected function addError($attribute, $rule, $parameters)
    {
        $message = $this->getMessage($attribute, $rule);

        $message = $this->doReplacements($message, $attribute, $rule, $parameters);

        $this->messages->add($attribute, $message);
    }

    /**
     * "Valider" les attributs facultatifs.
     *
     * Renvoie toujours vrai, permet-nous simplement de mettre parfois des règles.
     *
     * @return bool
     */
    protected function validateSometimes()
    {
        return true;
    }

    /**
     * Vérifiez qu'un attribut obligatoire existe.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        }  else if (is_string($value) && (trim($value) === '')) {
            return false;
        } else if ((is_array($value) || ($value instanceof Countable)) && (count($value) < 1)) {
            return false;
        } else if ($value instanceof File) {
            return (string) $value->getPath() != '';
        }

        return true;
    }

    /**
     * Validez que l'attribut donné est rempli s'il est présent.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateFilled($attribute, $value)
    {
        if (array_key_exists($attribute, $this->data) || array_key_exists($attribute, $this->files)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Déterminez si l’un des attributs donnés échoue au test requis.
     *
     * @param  array  $attributes
     * @return bool
     */
    protected function anyFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if (! $this->validateRequired($key, $this->getValue($key))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Déterminez si tous les attributs donnés échouent au test requis.
     *
     * @param  array  $attributes
     * @return bool
     */
    protected function allFailingRequired(array $attributes)
    {
        foreach ($attributes as $key) {
            if ($this->validateRequired($key, $this->getValue($key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifiez qu'un attribut existe lorsqu'un autre attribut existe.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWith($attribute, $value, $parameters)
    {
        if (! $this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Vérifiez qu'un attribut existe lorsque tous les autres attributs existent.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithAll($attribute, $value, $parameters)
    {
        if (! $this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Vérifiez qu'un attribut existe alors qu'un autre attribut ne l'existe pas.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithout($attribute, $value, $parameters)
    {
        if ($this->anyFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Vérifiez qu'un attribut existe alors que tous les autres attributs ne le sont pas.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredWithoutAll($attribute, $value, $parameters)
    {
        if ($this->allFailingRequired($parameters)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Validez qu'un attribut existe lorsqu'un autre attribut a une valeur donnée.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  mixed   $parameters
     * @return bool
     */
    protected function validateRequiredIf($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'required_if');

        $data = Arr::get($this->data, $parameters[0]);

        $values = array_slice($parameters, 1);

        if (in_array($data, $values)) {
            return $this->validateRequired($attribute, $value);
        }

        return true;
    }

    /**
     * Obtenez le nombre d'attributs présents dans une liste.
     *
     * @param  array  $attributes
     * @return int
     */
    protected function getPresentCount($attributes)
    {
        $count = 0;

        foreach ($attributes as $key) {
            if (Arr::get($this->data, $key) || Arr::get($this->files, $key)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifiez qu'un attribut a une confirmation correspondante.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateConfirmed($attribute, $value)
    {
        return $this->validateSame($attribute, $value, array($attribute.'_confirmation'));
    }

    /**
     * Vérifiez que deux attributs correspondent.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateSame($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'same');

        $other = Arr::get($this->data, $parameters[0]);

        return (isset($other) && $value == $other);
    }

    /**
     * Validez qu’un attribut est différent d’un autre attribut.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDifferent($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'different');

        $other = $parameters[0];

        return isset($this->data[$other]) && ($value != $this->data[$other]);
    }

    /**
     * Valider qu'un attribut a été "accepté".
     *
     * Cette règle de validation implique que l'attribut est "obligatoire".
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAccepted($attribute, $value)
    {
        $acceptable = array('yes', 'on', '1', 1, true, 'true');

        return ($this->validateRequired($attribute, $value) && in_array($value, $acceptable, true));
    }

    /**
     * Vérifiez qu'un attribut est un tableau.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateArray($attribute, $value)
    {
        return is_array($value);
    }

    /**
     * Vérifiez qu'un attribut est un booléen.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateBoolean($attribute, $value)
    {
        $acceptable = array(true, false, 0, 1, '0', '1');

        return in_array($value, $acceptable, true);
    }

    /**
     * Vérifiez qu'un attribut est un entier.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateInteger($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Vérifiez qu'un attribut est numérique.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateNumeric($attribute, $value)
    {
        return is_numeric($value);
    }

    /**
     * Vérifiez qu'un attribut est une chaîne.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateString($attribute, $value)
    {
        return is_string($value);
    }

    /**
     * Vérifiez qu'un attribut a un nombre donné de chiffres.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDigits($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'digits');

        return $this->validateNumeric($attribute, $value)
            && (strlen((string) $value) == $parameters[0]);
    }

    /**
     * Vérifiez qu'un attribut est compris entre un nombre donné de chiffres.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDigitsBetween($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'digits_between');

        $length = strlen((string) $value);

        return $this->validateNumeric($attribute, $value)
          && ($length >= $parameters[0]) && ($length <= $parameters[1]);
    }

    /**
     * Validez la taille d'un attribut.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateSize($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'size');

        return $this->getSize($attribute, $value) == $parameters[0];
    }

    /**
     * Valider que la taille d'un attribut se situe entre un ensemble de valeurs.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateBetween($attribute, $value, $parameters)
    {
        $this->requireParameterCount(2, $parameters, 'between');

        $size = $this->getSize($attribute, $value);

        return $size >= $parameters[0] && $size <= $parameters[1];
    }

    /**
     * Valider que la taille d'un attribut est supérieure à une valeur minimale.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMin($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'min');

        return $this->getSize($attribute, $value) >= $parameters[0];
    }

    /**
     * Valider que la taille d'un attribut est inférieure à une valeur maximale.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMax($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'max');

        if (($value instanceof UploadedFile) && ! $value->isValid()) {
            return false;
        }

        return $this->getSize($attribute, $value) <= $parameters[0];
    }

    /**
     * Obtenez la taille d'un attribut.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return mixed
     */
    protected function getSize($attribute, $value)
    {
        $hasNumeric = $this->hasRule($attribute, $this->numericRules);

        // Cette méthode déterminera si l'attribut est un nombre, une chaîne ou un fichier et
        // renvoie la taille appropriée en conséquence. Si c'est un nombre, alors le numéro lui-même
        // est la taille. Si c'est un fichier, on prend des kilo-octets, et pour une chaîne le
        // toute la longueur de la chaîne sera considérée comme la taille de l'attribut.
        if (is_numeric($value) && $hasNumeric) {
            return Arr::get($this->data, $attribute);
        } else if (is_array($value)) {
            return count($value);
        } else if ($value instanceof File) {
            return $value->getSize() / 1024;
        }

        return $this->getStringSize($value);
    }

    /**
     * Obtenez la taille d'une chaîne.
     *
     * @param  string  $value
     * @return int
     */
    protected function getStringSize($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    /**
     * Valider qu'un attribut est contenu dans une liste de valeurs.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateIn($attribute, $value, $parameters)
    {
        return in_array((string) $value, $parameters);
    }

    /**
     * Vérifier qu'un attribut n'est pas contenu dans une liste de valeurs.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateNotIn($attribute, $value, $parameters)
    {
        return ! $this->validateIn($attribute, $value, $parameters);
    }

    /**
     * Valider l'unicité d'une valeur d'attribut sur une table de base de données donnée.
     *
     * Si une colonne de base de données n'est pas spécifiée, l'attribut sera utilisé.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateUnique($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'unique');

        list($connection, $table) = $this->parseTable($parameters[0]);

        // La position du deuxième paramètre contient le nom de la colonne qui doit
        // être vérifié comme unique. Si ce paramètre n'est pas spécifié, nous le ferons simplement
        // suppose que cette colonne à vérifier partage le nom de l'attribut.
        $column = isset($parameters[1]) ? $parameters[1] : $attribute;

        list($idColumn, $id) = array(null, null);

        if (isset($parameters[2])) {
            list($idColumn, $id) = $this->getUniqueIds($parameters);

            if (strtolower($id) == 'null') {
                $id = null;
            }
        }

        // Le vérificateur de présence est responsable du comptage des lignes dans ce magasin
        // mécanisme qui peut être une base de données relationnelle ou tout autre mécanisme permanent
        // magasin de données comme Redis, etc. Nous l'utiliserons pour déterminer l'unicité.
        $verifier = $this->getPresenceVerifier();

        if (! is_null($connection)) {
            $verifier->setConnection($connection);
        }

        $extra = $this->getUniqueExtra($parameters);

        return $verifier->getCount(
            $table, $column, $value, $id, $idColumn, $extra

        ) == 0;
    }

    /**
     * Analysez la connexion/la table pour les règles uniques/existantes.
     *
     * @param  string  $table
     * @return array
     */
    protected function parseTable($table)
    {
        if (Str::contains($table, '.')) {
            return explode('.', $table, 2);
        }

        return array(null, $table);
    }

    /**
     * Obtenez la colonne d’ID exclus et la valeur de la règle unique.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getUniqueIds($parameters)
    {
        $idColumn = isset($parameters[3]) ? $parameters[3] : 'id';

        return array($idColumn, $parameters[2]);
    }

    /**
     * Obtenez les conditions supplémentaires pour une règle unique.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getUniqueExtra($parameters)
    {
        if (isset($parameters[4])) {
            return $this->getExtraConditions(array_slice($parameters, 4));
        }

        return array();
    }

    /**
     * Valider l'existence d'une valeur d'attribut dans une table de base de données.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateExists($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'exists');

        list($connection, $table) = $this->parseTable($parameters[0]);

        // La deuxième position du paramètre contient le nom de la colonne qui doit être
        // vérifié comme existant. Si ce paramètre n'est pas précisé nous devinerons
        // que les colonnes en cours de "vérification" partagent le nom de l'attribut donné.
        $column = isset($parameters[1]) ? $parameters[1] : $attribute;

        $expected = (is_array($value)) ? count($value) : 1;

        return $this->getExistCount($connection, $table, $column, $value, $parameters) >= $expected;
    }

    /**
     * Obtenez le nombre d’enregistrements existants en stockage.
     *
     * @param  string  $connection
     * @param  string  $table
     * @param  string  $column
     * @param  mixed   $value
     * @param  array   $parameters
     * @return int
     */
    protected function getExistCount($connection, $table, $column, $value, $parameters)
    {
        $verifier = $this->getPresenceVerifier();

        if (! is_null($connection)) {
            $verifier->setConnection($connection);
        }

        $extra = $this->getExtraExistConditions($parameters);

        if (is_array($value)) {
            return $verifier->getMultiCount($table, $column, $value, $extra);
        }

        return $verifier->getCount($table, $column, $value, null, null, $extra);
    }

    /**
     * Obtenez les conditions d'existence supplémentaires.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getExtraExistConditions(array $parameters)
    {
        return $this->getExtraConditions(array_values(array_slice($parameters, 2)));
    }

    /**
     * Obtenez les conditions supplémentaires pour une règle unique/existe.
     *
     * @param  array  $segments
     * @return array
     */
    protected function getExtraConditions(array $segments)
    {
        $extra = array();

        $count = count($segments);

        for ($i = 0; $i < $count; $i = $i + 2) {
            $extra[$segments[$i]] = $segments[$i + 1];
        }

        return $extra;
    }

    /**
     * Vérifiez qu'un attribut est une adresse IP valide.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateIp($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Vérifiez qu'un attribut est une adresse e-mail valide.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateEmail($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Vérifiez qu'un attribut est une URL valide.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateUrl($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Vérifiez qu'un attribut est une URL active.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateActiveUrl($attribute, $value)
    {
        $url = str_replace(array('http://', 'https://', 'ftp://'), '', strtolower($value));

        return checkdnsrr($url);
    }

    /**
     * Valider que le type MIME d'un fichier est un type MIME image.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateImage($attribute, $value)
    {
        return $this->validateMimes($attribute, $value, array('jpeg', 'png', 'gif', 'bmp'));
    }

    /**
     * Vérifiez que le type MIME d'un attribut de téléchargement de fichier se trouve dans un ensemble de types MIME.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateMimes($attribute, $value, $parameters)
    {
        if (! $this->isAValidFileInstance($value)) {
            return false;
        }

        return $value->getPath() != '' && in_array($value->guessExtension(), $parameters);
    }

    /**
     * Vérifiez que la valeur donnée est une instance de fichier valide.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isAValidFileInstance($value)
    {
        if (($value instanceof UploadedFile) && ! $value->isValid()) {
            return false;
        }

        return $value instanceof File;
    }

    /**
     * Vérifiez qu'un attribut contient uniquement des caractères alphabétiques.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlpha($attribute, $value)
    {
        return preg_match('/^[\pL\pM]+$/u', $value);
    }

    /**
     * Vérifiez qu'un attribut contient uniquement des caractères alphanumériques.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlphaNum($attribute, $value)
    {
        return preg_match('/^[\pL\pM\pN]+$/u', $value);
    }

    /**
     * Vérifiez qu'un attribut contient uniquement des caractères alphanumériques, des tirets et des traits de soulignement.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateAlphaDash($attribute, $value)
    {
        return preg_match('/^[\pL\pM\pN_-]+$/u', $value);
    }

    /**
     * Vérifiez qu'un attribut réussit une vérification d'expression régulière.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateRegex($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'regex');

        return preg_match($parameters[0], $value);
    }

    /**
     * Vérifiez qu'un attribut est une date valide.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateDate($attribute, $value)
    {
        if ($value instanceof DateTime) {
            return true;
        } else if (strtotime($value) === false) {
            return false;
        }

        $date = date_parse($value);

        return checkdate($date['month'], $date['day'], $date['year']);
    }

    /**
     * Vérifiez qu'un attribut correspond à un format de date.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateDateFormat($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'date_format');

        $parsed = date_parse_from_format($parameters[0], $value);

        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }

    /**
     * Validez que la date est antérieure à une date donnée.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateBefore($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'before');

        if ($format = $this->getDateFormat($attribute)) {
            return $this->validateBeforeWithFormat($format, $value, $parameters);
        }

        if (! ($date = strtotime($parameters[0]))) {
            return strtotime($value) < strtotime($this->getValue($parameters[0]));
        }

        return strtotime($value) < $date;
    }

    /**
     * Valider que la date est antérieure à une date donnée avec un format donné.
     *
     * @param  string  $format
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateBeforeWithFormat($format, $value, $parameters)
    {
        $param = $this->getValue($parameters[0]) ?: $parameters[0];

        return $this->checkDateTimeOrder($format, $value, $param);
    }

    /**
     * Validez que la date est postérieure à une date donnée.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateAfter($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'after');

        if ($format = $this->getDateFormat($attribute)) {
            return $this->validateAfterWithFormat($format, $value, $parameters);
        }

        if (! ($date = strtotime($parameters[0]))) {
            return strtotime($value) > strtotime($this->getValue($parameters[0]));
        }

        return strtotime($value) > $date;
    }

    /**
     * Valider que la date est postérieure à une date donnée avec un format donné.
     *
     * @param  string  $format
     * @param  mixed   $value
     * @param  array   $parameters
     * @return bool
     */
    protected function validateAfterWithFormat($format, $value, $parameters)
    {
        $param = $this->getValue($parameters[0]) ?: $parameters[0];

        return $this->checkDateTimeOrder($format, $param, $value);
    }

    /**
     * Étant donné deux chaînes date/heure, vérifiez que l’une est après l’autre.
     *
     * @param  string  $format
     * @param  string  $before
     * @param  string  $after
     * @return bool
     */
    protected function checkDateTimeOrder($format, $before, $after)
    {
        $before = $this->getDateTimeWithOptionalFormat($format, $before);

        $after = $this->getDateTimeWithOptionalFormat($format, $after);

        return ($before && $after) && ($after > $before);
    }

    /**
     * Obtenez une instance DateTime à partir d’une chaîne.
     *
     * @param  string  $format
     * @param  string  $value
     * @return \DateTime|null
     */
    protected function getDateTimeWithOptionalFormat($format, $value)
    {
        $date = DateTime::createFromFormat($format, $value);

        if ($date !== false) {
            return $date;
        }

        try {
            return new DateTime($value);
        }
        catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Vérifiez qu'un attribut est un fuseau horaire valide.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function validateTimezone($attribute, $value)
    {
        try {
            new DateTimeZone($value);
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Obtenez le format de date d'un attribut s'il en possède un.
     *
     * @param  string  $attribute
     * @return string|null
     */
    protected function getDateFormat($attribute)
    {
        if ($result = $this->getRule($attribute, 'DateFormat')) {
            return $result[1][0];
        }
    }

    /**
     * Obtenez le message de validation pour un attribut et une règle.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getMessage($attribute, $rule)
    {
        $lowerRule = Str::camel($rule);

        $inlineMessage = $this->getInlineMessage($attribute, $lowerRule);

        // Nous allons d'abord récupérer le message personnalisé pour la règle de validation si une
        // existe. Si un message de validation personnalisé est utilisé, nous renverrons le
        // message personnalisé, sinon nous continuerons à rechercher un message valide.
        if (! is_null($inlineMessage)) {
            return $inlineMessage;
        }

        $customKey = "validation.custom.{$attribute}.{$lowerRule}";

        $customMessage = $this->config->get($customKey);

        // Nous vérifions d’abord s’il existe un message de validation personnalisé pour l’attribut
        // et règle. Cela permet au développeur de spécifier des messages spécifiques pour
        // seulement certains attributs et règles qui doivent être spécialement formés.
        if (! is_null($customMessage)) {
            return $customMessage;
        }

        // Si la règle en cours de validation est une règle de « taille », nous devrons rassembler les
        // message d'erreur spécifique au type d'attribut en cours de validation tel
        // sous forme de nombre, de fichier ou de chaîne ayant tous des types de messages différents.
        else if (in_array($rule, $this->sizeRules)) {
            return $this->getSizeMessage($attribute, $rule);
        }

        // Enfin, si aucun message spécifié par le développeur n'a été défini et qu'aucun autre
        // des messages spéciaux s'appliquent à cette règle, nous allons simplement extraire la valeur par défaut
        // messages sortant du service de traduction pour cette règle de validation.
        $key = "validation.{$lowerRule}";

        if (! is_null($value = $this->config->get($key))) {
            return $value;
        }

        $lowerRule = Str::snake($rule);

        return $this->getInlineMessage(
            $attribute, $lowerRule, $this->fallbackMessages

        ) ?: $key;
    }

    /**
     * Obtenez le message en ligne pour une règle si elle existe.
     *
     * @param  string  $attribute
     * @param  string  $lowerRule
     * @param  array   $source
     * @return string
     */
    protected function getInlineMessage($attribute, $lowerRule, $source = null)
    {
        $source = $source ?: $this->customMessages;

        $keys = array("{$attribute}.{$lowerRule}", $lowerRule);

        // Nous allons d'abord rechercher un message personnalisé pour une règle spécifique à un attribut.
        // message pour les champs, puis nous rechercherons une ligne personnalisée générale
        // ce n'est pas spécifique à un attribut. Si nous trouvons l'un ou l'autre, nous le rendrons.
        foreach ($keys as $key) {
            if (isset($source[$key])) {
                return $source[$key];
            }
        }
    }

    /**
     * Obtenez le message d’erreur approprié pour une règle d’attribut et de taille.
     *
     * @param  string  $attribute
     * @param  string  $rule
     * @return string
     */
    protected function getSizeMessage($attribute, $rule)
    {
        $lowerRule = Str::camel($rule);

        // Il existe trois types différents de validations de taille. L'attribut peut être
        // soit un nombre, un fichier ou une chaîne, nous allons donc vérifier quelques points à savoir
        // de quel type de valeur il s'agit et renvoie la ligne correcte pour ce type.
        $type = $this->getAttributeType($attribute);

        $key = "validation.{$lowerRule}.{$type}";

        return $this->config->get($key, $key);
    }

    /**
     * Obtenez le type de données de l'attribut donné.
     *
     * @param  string  $attribute
     * @return string
     */
    protected function getAttributeType($attribute)
    {
        // Nous supposons que les attributs présents dans le tableau file sont des fichiers donc
        // signifie que si l'attribut n'a pas de règle numérique et que les fichiers
        // la liste ne l'a pas, nous la considérerons simplement comme une chaîne par élimination.

        if ($this->hasRule($attribute, $this->numericRules)) {
            return 'numeric';
        } else if ($this->hasRule($attribute, array('Array'))) {
            return 'array';
        } else if (array_key_exists($attribute, $this->files)) {
            return 'file';
        }

        return 'string';
    }

    /**
     * Remplacez tous les espaces réservés des messages d’erreur par des valeurs réelles.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function doReplacements($message, $attribute, $rule, $parameters)
    {
        $message = str_replace(':attribute', $this->getAttribute($attribute), $message);

        $replacer = Str::snake($rule);

        if (isset($this->replacers[$replacer])) {
            $message = $this->callReplacer($message, $attribute, $replacer, $parameters);
        } else if (method_exists($this, $replacer = "replace{$rule}")) {
            $message = call_user_func(array($this, $replacer), $message, $attribute, $rule, $parameters);
        }

        return $message;
    }

    /**
     * Transformez un tableau d'attributs en leur forme affichable.
     *
     * @param  array  $values
     * @return array
     */
    protected function getAttributeList(array $values)
    {
        $attributes = array();

        // Pour chaque attribut de la liste, nous obtiendrons simplement sa forme affichable sous la forme
        // ceci est pratique pour remplacer des listes de paramètres comme certains des
        // les fonctions de remplacement le font lors du formatage du message de validation.
        foreach ($values as $key => $value) {
            $attributes[$key] = $this->getAttribute($value);
        }

        return $attributes;
    }

    /**
     * Obtenez le nom affichable de l’attribut.
     *
     * @param  string  $attribute
     * @return string
     */
    protected function getAttribute($attribute)
    {
        // Le développeur peut spécifier dynamiquement le tableau d'attributs personnalisés
        // sur cette instance du Validator. Si l'attribut existe dans ce tableau
        // cela a priorité sur toutes les autres façons dont nous pouvons extraire des attributs.
        if (isset($this->customAttributes[$attribute])) {
            return $this->customAttributes[$attribute];
        }

        $key = "validation.attributes.{$attribute}";

        // Nous permettons au développeur de spécifier des lignes de langue pour chacun des
        // attributs permettant des contreparties plus affichables de chacun des
        // les attributs. Cela offre la possibilité d’utiliser des formats simples.
        if (! is_null($line = $this->config->get($key))) {
            return $line;
        }

        // Si aucune ligne de langue n'a été spécifiée pour l'attribut, tous les
        // les traits de soulignement sont supprimés du nom de l'attribut et ce sera
        // utilisé comme versions par défaut des noms affichables de l'attribut.
        return str_replace('_', ' ', Str::snake($attribute));
    }

    /**
     * Obtenez le nom affichable de la valeur.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @return string
     */
    public function getDisplayableValue($attribute, $value)
    {
        if (isset($this->customValues[$attribute][$value])) {
            return $this->customValues[$attribute][$value];
        }

        $key = "validation.values.{$attribute}.{$value}";

        if (! is_null($line = $this->config->get($key))) {
            return $line;
        }

        return $value;
    }

    /**
     * Remplacez tous les espaces réservés pour la règle between.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceBetween($message, $attribute, $rule, $parameters)
    {
        return str_replace(array(':min', ':max'), $parameters, $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle des chiffres.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDigits($message, $attribute, $rule, $parameters)
    {
        return str_replace(':digits', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle des chiffres (entre).
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDigitsBetween($message, $attribute, $rule, $parameters)
    {
        return $this->replaceBetween($message, $attribute, $rule, $parameters);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle de taille.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceSize($message, $attribute, $rule, $parameters)
    {
        return str_replace(':size', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle min.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMin($message, $attribute, $rule, $parameters)
    {
        return str_replace(':min', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle max.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMax($message, $attribute, $rule, $parameters)
    {
        return str_replace(':max', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle in.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceIn($message, $attribute, $rule, $parameters)
    {
        foreach ($parameters as &$parameter) {
            $parameter = $this->getDisplayableValue($attribute, $parameter);
        }

        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle not_in.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceNotIn($message, $attribute, $rule, $parameters)
    {
        return $this->replaceIn($message, $attribute, $rule, $parameters);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle des mimes.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceMimes($message, $attribute, $rule, $parameters)
    {
        return str_replace(':values', implode(', ', $parameters), $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle require_with.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWith($message, $attribute, $rule, $parameters)
    {
        $parameters = $this->getAttributeList($parameters);

        return str_replace(':values', implode(' / ', $parameters), $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle require_without.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWithout($message, $attribute, $rule, $parameters)
    {
        return $this->replaceRequiredWith($message, $attribute, $rule, $parameters);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle require_without_all.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredWithoutAll($message, $attribute, $rule, $parameters)
    {
        return $this->replaceRequiredWith($message, $attribute, $rule, $parameters);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle require_if.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceRequiredIf($message, $attribute, $rule, $parameters)
    {
        $parameters[1] = $this->getDisplayableValue($parameters[0], Arr::get($this->data, $parameters[0]));

        $parameters[0] = $this->getAttribute($parameters[0]);

        return str_replace(array(':other', ':value'), $parameters, $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la même règle.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceSame($message, $attribute, $rule, $parameters)
    {
        return str_replace(':other', $this->getAttribute($parameters[0]), $message);
    }

    /**
     * Remplacez tous les espaces réservés pour les différentes règles.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDifferent($message, $attribute, $rule, $parameters)
    {
        return $this->replaceSame($message, $attribute, $rule, $parameters);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle date_format.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceDateFormat($message, $attribute, $rule, $parameters)
    {
        return str_replace(':format', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle avant.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceBefore($message, $attribute, $rule, $parameters)
    {
        if (! (strtotime($parameters[0]))) {
            return str_replace(':date', $this->getAttribute($parameters[0]), $message);
        }

        return str_replace(':date', $parameters[0], $message);
    }

    /**
     * Remplacez tous les espaces réservés pour la règle after.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function replaceAfter($message, $attribute, $rule, $parameters)
    {
        return $this->replaceBefore($message, $attribute, $rule, $parameters);
    }

    /**
     * Déterminez si l'attribut donné a une règle dans l'ensemble donné.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return bool
     */
    protected function hasRule($attribute, $rules)
    {
        return ! is_null($this->getRule($attribute, $rules));
    }

    /**
     * Obtenez une règle et ses paramètres pour un attribut donné.
     *
     * @param  string  $attribute
     * @param  string|array  $rules
     * @return array|null
     */
    protected function getRule($attribute, $rules)
    {
        if (! array_key_exists($attribute, $this->rules)) {
            return;
        }

        $rules = (array) $rules;

        foreach ($this->rules[$attribute] as $rule) {
            list($rule, $parameters) = $this->parseRule($rule);

            if (in_array($rule, $rules)) {
                return array($rule, $parameters);
            }
        }
    }

    /**
     * Extrayez le nom de la règle et les paramètres d'une règle.
     *
     * @param  array|string  $rules
     * @return array
     */
    protected function parseRule($rules)
    {
        if (is_array($rules)) {
            return $this->parseArrayRule($rules);
        }

        return $this->parseStringRule($rules);
    }

    /**
     * Analyser une règle basée sur un tableau.
     *
     * @param  array  $rules
     * @return array
     */
    protected function parseArrayRule(array $rules)
    {
        $rule = Arr::get($rules, 0);

        return array(
            Str::studly(trim($rule)), array_slice($rules, 1)
        );
    }

    /**
     * Analyser une règle basée sur une chaîne.
     *
     * @param  string  $rules
     * @return array
     */
    protected function parseStringRule($rules)
    {
        $parameters = array();

        // Le format de spécification des règles et paramètres de validation suit un
        // convention de formatage simple {rule} : {parameters}. Par exemple le
        // la règle "Max:3" indique que la valeur ne peut contenir que trois lettres.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);

            $parameters = $this->parseParameters($rules, $parameter);
        }

        return array(
            Str::studly(trim($rules)), $parameters
        );
    }

    /**
     * Analyser une liste de paramètres.
     *
     * @param  string  $rule
     * @param  string  $parameter
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) == 'regex') {
            return array($parameter);
        }

        return str_getcsv($parameter);
    }

    /**
     * Obtenez la gamme d’extensions de validateur personnalisées.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Enregistrez un tableau d’extensions de validateur personnalisées.
     *
     * @param  array  $extensions
     * @return void
     */
    public function addExtensions(array $extensions)
    {
        if ($extensions) {
            $keys = array_map('Str::snake', array_keys($extensions));

            $extensions = array_combine($keys, array_values($extensions));
        }

        $this->extensions = array_merge($this->extensions, $extensions);
    }

    /**
     * Enregistrez un tableau d’extensions de validateur implicites personnalisées.
     *
     * @param  array  $extensions
     * @return void
     */
    public function addImplicitExtensions(array $extensions)
    {
        $this->addExtensions($extensions);

        foreach ($extensions as $rule => $extension) {
            $this->implicitRules[] = Str::studly($rule);
        }
    }

    /**
     * Enregistrez une extension de validateur personnalisée.
     *
     * @param  string  $rule
     * @param  \Closure|string  $extension
     * @return void
     */
    public function addExtension($rule, $extension)
    {
        $this->extensions[Str::snake($rule)] = $extension;
    }

    /**
     * Enregistrez une extension de validateur implicite personnalisée.
     *
     * @param  string   $rule
     * @param  \Closure|string  $extension
     * @return void
     */
    public function addImplicitExtension($rule, $extension)
    {
        $this->addExtension($rule, $extension);

        $this->implicitRules[] = Str::studly($rule);
    }

    /**
     * Obtenez la gamme de remplaçants de messages de validateur personnalisés.
     *
     * @return array
     */
    public function getReplacers()
    {
        return $this->replacers;
    }

    /**
     * Enregistrez un tableau de remplacements de messages de validateur personnalisés.
     *
     * @param  array  $replacers
     * @return void
     */
    public function addReplacers(array $replacers)
    {
        if (! empty($replacers)) {
            $keys = array_map('Str::snake', array_keys($replacers));

            $replacers = array_combine($keys, array_values($replacers));
        }

        $this->replacers = array_merge($this->replacers, $replacers);
    }

    /**
     * Enregistrez un remplaçant de message de validateur personnalisé.
     *
     * @param  string  $rule
     * @param  \Closure|string  $replacer
     * @return void
     */
    public function addReplacer($rule, $replacer)
    {
        $this->replacers[Str::snake($rule)] = $replacer;
    }

    /**
     * Obtenez les données en cours de validation.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Définissez les données sous validation.
     *
     * @param  array  $data
     * @return void
     */
    public function setData(array $data)
    {
        $this->data = $this->parseData($data);
    }

    /**
     * Obtenez les règles de validation.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Définissez les règles de validation.
     *
     * @param  array  $rules
     * @return $this
     */
    public function setRules(array $rules)
    {
        $this->rules = $this->explodeRules($rules);

        return $this;
    }

    /**
     * Définissez les attributs personnalisés sur le validateur.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function setAttributeNames(array $attributes)
    {
        $this->customAttributes = $attributes;

        return $this;
    }

    /**
     * Définissez les valeurs personnalisées sur le validateur.
     *
     * @param  array  $values
     * @return $this
     */
    public function setValueNames(array $values)
    {
        $this->customValues = $values;

        return $this;
    }

    /**
     * Obtenez les fichiers en cours de validation.
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Mettez les fichiers en cours de validation.
     *
     * @param  array  $files
     * @return $this
     */
    public function setFiles(array $files)
    {
        $this->files = $files;

        return $this;
    }

    /**
     * Obtenez l’implémentation de Presence Verifier.
     *
     * @return \Two\Validation\Contracts\PresenceVerifierInterface
     *
     * @throws \RuntimeException
     */
    public function getPresenceVerifier()
    {
        if (! isset($this->presenceVerifier)) {
            throw new \RuntimeException("Presence verifier has not been set.");
        }

        return $this->presenceVerifier;
    }

    /**
     * Définissez l’implémentation de Presence Verifier.
     *
     * @param  \Two\Validation\Contracts\PresenceVerifierInterface  $presenceVerifier
     * @return void
     */
    public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier)
    {
        $this->presenceVerifier = $presenceVerifier;
    }

    /**
     * Obtenez les messages personnalisés pour le validateur
     *
     * @return array
     */
    public function getCustomMessages()
    {
        return $this->customMessages;
    }

    /**
     * Définir les messages personnalisés pour le validateur
     *
     * @param  array  $messages
     * @return void
     */
    public function setCustomMessages(array $messages)
    {
        $this->customMessages = array_merge($this->customMessages, $messages);
    }

    /**
     * Obtenez les attributs personnalisés utilisés par le validateur.
     *
     * @return array
     */
    public function getCustomAttributes()
    {
        return $this->customAttributes;
    }

    /**
     * Ajoutez des attributs personnalisés au validateur.
     *
     * @param  array  $customAttributes
     * @return $this
     */
    public function addCustomAttributes(array $customAttributes)
    {
        $this->customAttributes = array_merge($this->customAttributes, $customAttributes);

        return $this;
    }

    /**
     * Obtenez les valeurs personnalisées pour le validateur.
     *
     * @return array
     */
    public function getCustomValues()
    {
        return $this->customValues;
    }

    /**
     * Ajoutez les valeurs personnalisées pour le validateur.
     *
     * @param  array  $customValues
     * @return $this
     */
    public function addCustomValues(array $customValues)
    {
        $this->customValues = array_merge($this->customValues, $customValues);

        return $this;
    }

    /**
     * Obtenez les messages de secours pour le validateur.
     *
     * @return array
     */
    public function getFallbackMessages()
    {
        return $this->fallbackMessages;
    }

    /**
     * Définissez les messages de secours pour le validateur.
     *
     * @param  array  $messages
     * @return void
     */
    public function setFallbackMessages(array $messages)
    {
        $this->fallbackMessages = $messages;
    }

    /**
     * Obtenez les règles de validation ayant échoué.
     *
     * @return array
     */
    public function failed()
    {
        return $this->failedRules;
    }

    /**
     * Obtenez le conteneur de messages pour le validateur.
     *
     * @return \Two\Support\MessageBag
     */
    public function messages()
    {
        if (! isset($this->messages)) {
            $this->passes();
        }

        return $this->messages;
    }

    /**
     * Un raccourci alternatif plus sémantique vers le conteneur de messages.
     *
     * @return \Two\Support\MessageBag
     */
    public function errors()
    {
        return $this->messages();
    }

    /**
     * Récupérez les messages de l'instance.
     *
     * @return \Two\Support\MessageBag
     */
    public function getMessageBag()
    {
        return $this->messages();
    }

    /**
     * Définissez l'instance de conteneur IoC.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Appelez une extension de validateur personnalisée.
     *
     * @param  string  $rule
     * @param  array   $parameters
     * @return bool
     */
    protected function callExtension($rule, $parameters)
    {
        $callback = $this->extensions[$rule];

        if ($callback instanceof Closure) {
            return call_user_func_array($callback, $parameters);
        } else if (is_string($callback)) {
            return $this->callClassBasedExtension($callback, $parameters);
        }
    }

    /**
     * Appelez une extension de validateur basée sur une classe.
     *
     * @param  string  $callback
     * @param  array   $parameters
     * @return bool
     */
    protected function callClassBasedExtension($callback, $parameters)
    {
        list($class, $method) = explode('@', $callback);

        $instance = $this->container->make($class);

        return call_user_func_array(array($instance, $method), $parameters);
    }

    /**
     * Appelez un remplaçant de message de validateur personnalisé.
     *
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function callReplacer($message, $attribute, $rule, $parameters)
    {
        $callback = $this->replacers[$rule];

        if ($callback instanceof Closure) {
            return call_user_func_array($callback, func_get_args());
        } else if (is_string($callback)) {
            return $this->callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters);
        }
    }

    /**
     * Appelez un remplaçant de message de validateur basé sur une classe.
     *
     * @param  string  $callback
     * @param  string  $message
     * @param  string  $attribute
     * @param  string  $rule
     * @param  array   $parameters
     * @return string
     */
    protected function callClassBasedReplacer($callback, $message, $attribute, $rule, $parameters)
    {
        list($class, $method) = explode('@', $callback);

        $instance = $this->container->make($class);

        return call_user_func_array(array($instance, $method), array_slice(func_get_args(), 1));
    }

    /**
     * Nécessite la présence d'un certain nombre de paramètres.
     *
     * @param  int    $count
     * @param  array  $parameters
     * @param  string  $rule
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function requireParameterCount($count, $parameters, $rule)
    {
        if (count($parameters) < $count) {
            throw new InvalidArgumentException("Validation rule $rule requires at least $count parameters.");
        }
    }

    /**
     * Gérez les appels dynamiques aux méthodes de classe.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $rule = Str::snake(substr($method, 8));

        if (isset($this->extensions[$rule])) {
            return $this->callExtension($rule, $parameters);
        }

        throw new BadMethodCallException("Method [$method] does not exist.");
    }
}
