<?php
    namespace Enobrev\API\Middleware\Response;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\Log;

    class MetadataRequest implements MiddlewareInterface {
        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.MetadataRequest');
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oBuilder->mergeRecursiveDistinct('_request', [
                    'method'     => $oRequest->getMethod(),
                    'path'       => $oRequest->getUri()->getPath(),
                    'params'     => [
                        'path'  => FastRoute::getPathParams($oRequest),
                        'query' => $oRequest->getQueryParams(),
                        'post'  => $oRequest->getParsedBody()
                    ],
                    'headers'    => json_encode($oRequest->getHeaders())
                ]);
                ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }