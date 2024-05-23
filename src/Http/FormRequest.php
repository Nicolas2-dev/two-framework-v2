<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use Two\Container\Container;
use Two\Http\Request;
use Two\Http\Response;
use Two\Http\JsonResponse;
use Two\Routing\Redirector;
use Two\Validation\Validator;
use Two\Http\Exception\HttpResponseException;
use Two\Validation\Contracts\ValidatesWhenResolvedInterface as ValidatesWhenResolved;
use Two\Validation\Traits\ValidatesWhenResolvedTrait;
use Two\Validation\Factory as ValidationFactory;


class FormRequest extends Request implements ValidatesWhenResolved
{
    use ValidatesWhenResolvedTrait;

    /**
     * L'instance de conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * L'instance du redirecteur.
     *
     * @var \Two\Routing\Redirector
     */
    protected $redirector;

    /**
     * URI vers lequel rediriger si la validation échoue.
     *
     * @var string
     */
    protected $redirect;

    /**
     * L'itinéraire vers lequel rediriger si la validation échoue.
     *
     * @var string
     */
    protected $redirectRoute;

    /**
     * Action du contrôleur vers laquelle rediriger si la validation échoue.
     *
     * @var string
     */
    protected $redirectAction;

    /**
     * La clé à utiliser pour le sac d'erreurs de vue.
     *
     * @var string
     */
    protected $errorBag = 'default';

    /**
     * Les touches de saisie qui ne doivent pas être flashées lors de la redirection.
     *
     * @var array
     */
    protected $dontFlash = array('password', 'password_confirmation');

    /**
     * Obtenez l'instance de validateur pour la demande.
     *
     * @return \Two\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        $factory = $this->container->make(ValidationFactory::class);

        if (method_exists($this, 'validator')) {
            return $this->container->call(array($this, 'validator'), compact('factory'));
        }

        $rules = $this->container->call(array($this, 'rules'));

        return $factory->make($this->all(), $rules, $this->messages(), $this->attributes());
    }

    /**
     * Gérer une tentative de validation échouée.
     *
     * @param  \Two\Validation\Validator  $validator
     * @return mixed
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException($this->response(
            $this->formatErrors($validator)
        ));
    }

    /**
     * Déterminez si la demande réussit le contrôle d’autorisation.
     *
     * @return bool
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize')) {
            return $this->container->call(array($this, 'authorize'));
        }

        return false;
    }

    /**
     * Gérer une tentative d'autorisation ayant échoué.
     *
     * @return mixed
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException($this->forbiddenResponse());
    }

    /**
     * Obtenez la réponse de validation d'échec appropriée pour la demande.
     *
     * @param  array  $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        if ($this->ajax() || $this->wantsJson()) {
            return new JsonResponse($errors, 422);
        }

        return $this->redirector->to($this->getRedirectUrl())
            ->withInput($this->except($this->dontFlash))
            ->withErrors($errors, $this->errorBag);
    }

    /**
     * Obtenez la réponse pour une opération interdite.
     *
     * @return \Two\Http\Response
     */
    public function forbiddenResponse()
    {
        return new Response('Forbidden', 403);
    }

    /**
     * Formatez les erreurs de l’instance Validator donnée.
     *
     * @param  \Two\Validation\Validator  $validator
     * @return array
     */
    protected function formatErrors(Validator $validator)
    {
        return $validator->getMessageBag()->toArray();
    }

    /**
     * Obtenez l'URL vers laquelle rediriger en cas d'erreur de validation.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $url = $this->redirector->getUrlGenerator();

        if ($this->redirect) {
            return $url->to($this->redirect);
        } else if ($this->redirectRoute) {
            return $url->route($this->redirectRoute);
        } else if ($this->redirectAction) {
            return $url->action($this->redirectAction);
        }

        return $url->previous();
    }

    /**
     * Définissez l'instance du redirecteur.
     *
     * @param  \Two\Routing\Redirector  $redirector
     * @return \Two\Application\Http\FormRequest
     */
    public function setRedirector(Redirector $redirector)
    {
        $this->redirector = $redirector;

        return $this;
    }

    /**
     * Définissez l’implémentation du conteneur.
     *
     * @param  \Two\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Définissez des messages personnalisés pour les erreurs du validateur.
     *
     * @return array
     */
    public function messages()
    {
        return array();
    }

    /**
     * Définissez des attributs personnalisés pour les erreurs du validateur.
     *
     * @return array
     */
    public function attributes()
    {
        return array();
    }
}
