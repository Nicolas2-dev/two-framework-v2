<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions;

use Exception;

use Two\Http\Request;
use Psr\Log\LoggerInterface;
use Two\Container\Container;
use Two\Support\Facades\Config;
use Two\Support\Facades\Redirect;
use Two\Support\Facades\Response;
use Two\Http\Response as HttpResponse;
use Two\Auth\Exception\AuthorizedException;
use Two\Http\Exception\HttpResponseException;
use Two\Exceptions\Contracts\HandlerInterface;
use Two\Exceptions\Exception\FlattenException;
use Two\Auth\Exception\AuthenticationException;
use Two\Validation\Exception\ValidationException;
use Two\Database\Exception\ModelNotFoundException;
use Two\Exceptions\Debug\DebugExceptionHandler as SymfonyExceptionHandler;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class TwoHandlerException implements HandlerInterface
{

    /**
     * La mise en œuvre du conteneur.
     *
     * @var \Two\Container\Container
     */
    protected $container;

    /**
     * Une liste des types d'exception qui ne doivent pas être signalés.
     *
     * @var array
     */
    protected $dontReport = array();


    /**
     * Créez une nouvelle instance de gestionnaire d'exceptions.
     *
     * @param  \Psr\Log\LoggerInterface  $log
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Signaler ou enregistrer une exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            return $e->report();
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        }
        catch (Exception $ex) {
            throw $e; //  Lève l'exception d'origine
        }

        $logger->error($e);
    }

    /**
     * Déterminez si l'exception doit être signalée.
     *
     * @param  \Exception  $e
     * @return bool
     */
    public function shouldReport(Exception $e)
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Déterminez si l'exception ne doit pas être signalée.
     *
     * @param  \Exception  $e
     * @return bool
     */
    public function shouldntReport(Exception $e)
    {
        $dontReport = array_merge($this->dontReport, array(
            HttpResponseException::class
        ));

        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Préparez une exception pour le rendu.
     *
     * @param  \Exception  $e
     * @return \Exception
     */
    protected function prepareException(Exception $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        } else if ($e instanceof AuthorizedException) {
            $e = new HttpException(403, $e->getMessage());
        }

        return $e;
    }

    /**
     * Rendre une exception dans une réponse.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Exception  $e
     * @return \Two\Http\Response
     */
    public function render(Request $request, Exception $e)
    {
        $e = $this->prepareException($e);

        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        } else if ($e instanceof AuthenticationException) {
            return $this->unauthenticated($request, $e);
        } else if ($e instanceof ValidationException) {
            return $this->convertValidationExceptionToResponse($e, $request);
        }

        return $this->prepareResponse($request, $e);
    }

        /**
         * Convertir une exception d'authentification en une réponse non authentifiée.
         *
         * @param  \Two\Http\Request  $request
         * @param  \Two\Auth\Exception\AuthenticationException  $exception
         * @return \Two\Http\Response
         */
        protected function unauthenticated(Request $request, AuthenticationException $exception)
        {
            //
        }

    /**
     * Créez un objet de réponse à partir de l'exception de validation donnée.
     *
     * @param  \Two\Validation\ValidationException  $e
     * @param  \Two\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, Request $request)
    {
        if ($e->response) {
            return $e->response;
        }

        $errors = $e->validator->errors()->getMessages();

        if ($request->ajax() || $request->wantsJson()) {
            return Response::json($errors, 422);
        }

        return Redirect::back()->withInput($request->input())->withErrors($errors);
    }

    /**
     * Préparer la réponse contenant le rendu d'exception.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Exception $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareResponse(Request $request, Exception $e)
    {
        if ($this->isHttpException($e)) {
            return $this->createResponse($this->renderHttpException($e, $request), $e);
        } else {
            return $this->createResponse($this->convertExceptionToResponse($e, $request), $e);
        }
    }

    /**
     * Mapper une exception dans une réponse Two.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Exception  $e
     * @return \Two\Http\Response
     */
    protected function createResponse($response, Exception $e)
    {
        $response = new HttpResponse($response->getContent(), $response->getStatusCode(), $response->headers->all());

        return $response->withException($e);
    }

    /**
     * Rendre l'exception HttpException donnée.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpException  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpException $e, Request $request)
    {
        return $this->convertExceptionToResponse($e, $request);
    }

    /**
     * Convertir l'exception donnée en une instance Response.
     *
     * @param  \Exception  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertExceptionToResponse(Exception $e, Request $request)
    {
        $debug = Config::get('app.debug');

        //
        $e = FlattenException::create($e);

        $handler = new SymfonyExceptionHandler($debug);

        return new SymfonyResponse($handler->getHtml($e), $e->getStatusCode(), $e->getHeaders());
    }

    /**
     * Rendre une exception à la console.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  \Exception  $e
     * @return void
     */
    public function renderForConsole($output, Exception $e)
    {
        with(new ConsoleApplication)->renderThrowable($e, $output);
    }

    /**
     * Déterminez si l'exception donnée est une exception HTTP.
     *
     * @param  \Exception  $e
     * @return bool
     */
    protected function isHttpException(Exception $e)
    {
        return $e instanceof HttpException;
    }
}
