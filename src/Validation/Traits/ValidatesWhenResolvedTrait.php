<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation\Traits;

use Two\Validation\Validator;
use Two\Validation\Exception\ValidationException;
use Two\Validation\Exception\UnAuthorizedException;


/**
 * Fournit une implémentation par défaut de ValidatesWhenResolvedInterface.
 */
trait ValidatesWhenResolvedTrait
{
    /**
     * Validez l'instance de classe.
     *
     * @return void
     */
    public function validate()
    {
        $instance = $this->getValidatorInstance();

        if (! $this->passesAuthorization()) {
            $this->failedAuthorization();
        } else if (! $instance->passes()) {
            $this->failedValidation($instance);
        }
    }

    /**
     * Obtenez l'instance de validateur pour la demande.
     *
     * @return \Two\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        return $this->validator();
    }

    /**
     * Gérer une tentative de validation échouée.
     *
     * @param  \Two\Validation\Validator  $validator
     * @return mixed
     */
    protected function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator);
    }

    /**
     * Déterminez si la demande réussit le contrôle d’autorisation.
     *
     * @return bool
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize')) {
            return $this->authorize();
        }

        return true;
    }

    /**
     * Gérer une tentative d'autorisation ayant échoué.
     *
     * @return mixed
     */
    protected function failedAuthorization()
    {
        throw new UnAuthorizedException();
    }
}
