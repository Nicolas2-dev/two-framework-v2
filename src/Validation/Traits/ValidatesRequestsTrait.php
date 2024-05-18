<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Validation\Traits;

use Two\Http\Exception\HttpResponseException;
use Two\Http\JsonResponse;
use Two\Http\Request;
use Two\Validation\Validator;
use Two\Support\Facades\App;
use Two\Support\Facades\Redirect;


trait ValidatesRequestsTrait
{
    /**
     * Le sac d'erreurs par défaut.
     *
     * @var string
     */
    protected $validatesRequestErrorBag;


    /**
     * Validez la demande donnée avec les règles données.
     *
     * @param  \Two\Http\Request  $request
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $attributes
     * @return void
     *
     * @throws \Two\Http\Exception\HttpResponseException
     */
    public function validate(Request $request, array $rules, array $messages = array(), array $attributes = array())
    {
        $input = $request->all();

        $validator = $this->getValidationFactory()
            ->make($input, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $this->throwValidationException($request, $validator);
        }
    }

    /**
     * Validez la demande donnée avec les règles données.
     *
     * @param  string  $errorBag
     * @param  \Two\Http\Request  $request
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $attributes
     * @return void
     *
     * @throws \Two\Http\Exception\HttpResponseException
     */
    public function validateWithBag($errorBag, Request $request, array $rules, array $messages = array(), array $attributes = array())
    {
        $this->withErrorBag($errorBag, function () use ($request, $rules, $messages, $attributes)
        {
            $this->validate($request, $rules, $messages, $attributes);
        });
    }

    /**
     * Lancez l'exception de validation échouée.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Two\Validation\Validator  $validator
     * @return void
     *
     * @throws \Two\Http\Exception\HttpResponseException
     */
    protected function throwValidationException(Request $request, $validator)
    {
        $response = $this->buildFailedValidationResponse(
            $request, $this->formatValidationErrors($validator)
        );

        throw new HttpResponseException($response);
    }

    /**
     * Créez la réponse lorsqu'une demande échoue à la validation.
     *
     * @param  \Two\Http\Request  $request
     * @param  array  $errors
     * @return \Two\Http\Response
     */
    protected function buildFailedValidationResponse(Request $request, array $errors)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return new JsonResponse($errors, 422);
        }

        $url = $this->getRedirectUrl();

        return Redirect::to($url)
            ->withInput($request->input())
            ->withErrors($errors, $this->errorBag());
    }

    /**
     * Formatez les erreurs de validation à renvoyer.
     *
     * @param  \Two\Validation\Validator  $validator
     * @return array
     */
    protected function formatValidationErrors(Validator $validator)
    {
        return $validator->errors()->getMessages();
    }

    /**
     * Obtenez l'URL vers laquelle nous devons rediriger.
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        return App::make('url')->previous();
    }

    /**
     * Obtenez une instance de fabrique de validation.
     *
     * @return \Two\Validation\Factory
     */
    protected function getValidationFactory()
    {
        return App::make('validator');
    }

    /**
     * Exécutez une fermeture à l'intérieur avec un sac d'erreur donné défini comme sac par défaut.
     *
     * @param  string  $errorBag
     * @param  callable  $callback
     * @return void
     */
    protected function withErrorBag($errorBag, callable $callback)
    {
        $this->validatesRequestErrorBag = $errorBag;

        call_user_func($callback);

        $this->validatesRequestErrorBag = null;
    }

    /**
     * Obtenez la clé à utiliser pour le sac d’erreurs de vue.
     *
     * @return string
     */
    protected function errorBag()
    {
        return $this->validatesRequestErrorBag ?: 'default';
    }
}
