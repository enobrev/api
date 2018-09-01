<?php
    namespace Enobrev\API\Middleware\Response;

    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Middleware\ResponseBuilder;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    class MetadataRequest implements MiddlewareInterface {
        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oBuilder->set('_request', [
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

            return $oHandler->handle($oRequest);
        }
    }