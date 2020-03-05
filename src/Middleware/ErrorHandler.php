<?php
declare(strict_types = 1);

namespace Enobrev\API\Middleware;

use Enobrev\API\RequestAttribute;
use Enobrev\API\RequestAttributeInterface;
use Throwable;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Enobrev\API\Exception\HttpErrorException;

/**
 * Class ErrorHandler
 * middlewares/error-handler but with my own exception class
 *
 * @package Middlewares
 */
class ErrorHandler implements MiddlewareInterface, RequestAttributeInterface {
    use RequestAttribute;

    private $handler;

    /**
     * @var callable|null The status code validator
     */
    private $statusCodeValidator;

    /**
     * @var bool Whether or not catch exceptions
     */
    private $catchExceptions = false;

    public static function get(ServerRequestInterface $oRequest): HttpErrorException {
        return self::getAttribute($oRequest);
    }

    public static function update(ServerRequestInterface $oRequest, HttpErrorException $oError):ServerRequestInterface {
        return self::setAttribute($oRequest, $oError);
    }

    public function __construct(RequestHandlerInterface $handler = null)
    {
        $this->handler = $handler;
    }

    /**
     * Configure the catchExceptions.
     */
    public function catchExceptions(bool $catch = true): self
    {
        $this->catchExceptions = (bool) $catch;

        return $this;
    }

    /**
     * Configure the status code validator.
     */
    public function statusCode(callable $statusCodeValidator): self
    {
        $this->statusCodeValidator = $statusCodeValidator;

        return $this;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ob_start();
        $level = ob_get_level();

        try {
            $response = $handler->handle($request);

            if ($this->isError($response->getStatusCode())) {
                $exception = new HttpErrorException($response->getReasonPhrase(), $response->getStatusCode());
                return $this->handleError($request, $exception);
            }

            return $response;
        } catch (HttpErrorException $exception) {
            return $this->handleError($request, $exception);
        } catch (Throwable $exception) {
            if (!$this->catchExceptions) {
                throw $exception;
            }

            return $this->handleError($request, HttpErrorException::create(500, [], $exception));
        } finally {
            while (ob_get_level() >= $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * Execute the error handler.
     */
    private function handleError(ServerRequestInterface $request, HttpErrorException $exception): ResponseInterface
    {
        $request = self::update($request, $exception);
        $handler = $this->handler ?: new ErrorHandlerDefault();

        return $handler->handle($request);
    }

    /**
     * Check whether the status code represents an error or not.
     */
    private function isError(int $statusCode): bool
    {
        if ($this->statusCodeValidator) {
            return call_user_func($this->statusCodeValidator, $statusCode);
        }

        return $statusCode >= 400 && $statusCode < 600;
    }
}
