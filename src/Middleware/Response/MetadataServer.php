<?php
    namespace Enobrev\API\Middleware\Response;

    use DateTime;

    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\Log;

    use function Enobrev\dbg;

    class MetadataServer implements MiddlewareInterface {
        const SYNC_DATE_FORMAT = 'Y-m-d H:i:s';

        /**
         * Process an incoming server request and return a response, optionally delegating
         * response creation to a handler.
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.MetadataServer');
            $oBuilder = ResponseBuilder::get($oRequest);
            if ($oBuilder) {
                $oNow = new DateTime;
                $oBuilder->set('_server', [
                    'timezone'      => $oNow->format('T'),
                    'timezone_gmt'  => $oNow->format('P'),
                    'date'          => $oNow->format(self::SYNC_DATE_FORMAT),
                    'date_w3c'      => $oNow->format(DateTime::W3C)
                ]);
                ResponseBuilder::update($oRequest, $oBuilder);
            }

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }
    }