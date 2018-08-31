<?php
    namespace Enobrev\API\Middleware;

    use Adbar\Dot;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Zend\Diactoros\Response\JsonResponse;

    use Enobrev\API\HTTP;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;

    use function Enobrev\dbg;


    class ValidateSpec implements MiddlewareInterface {

        private $bValid = true;

        /**
         * @param ServerRequestInterface $oRequest
         * @param RequestHandlerInterface $oHandler
         * @return ResponseInterface
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oSpec = RequestAttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                return $oHandler->handle($oRequest);
            }

            $oRequest = $this->validatePathParameters($oRequest);
            $oRequest = $this->validateQueryParameters($oRequest);
            // FIXME: Needs to be POST params as well

            if (!$this->bValid) {
                return new JsonResponse(ResponseBuilder::get($oRequest)->all(), HTTP\BAD_REQUEST);
            }

            return $oHandler->handle($oRequest);
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @param Spec $oSpec
         * @return ServerRequestInterface
         */
        private function validatePathParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = RequestAttributeSpec::getSpec($oRequest);
            $aParameters = FastRoute::getPathParams($oRequest);
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                Spec::paramsToJsonSchema($oSpec->PathParams)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if ($this->bValid && $oValidator->isValid() === false) {
                $this->bValid = false;
            }

            $oRequest = $this->adjustValidationPayload($oRequest, $oValidator, new Dot($aParameters));
            $oRequest = FastRoute::updatePathParams($oRequest, (array) $oParameters);

            return $oRequest;
        }

        /**
         * @param ServerRequestInterface $oRequest
         * @param Spec $oSpec
         * @return ServerRequestInterface
         */
        private function validateQueryParameters(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec       = RequestAttributeSpec::getSpec($oRequest);
            $aParameters = $oRequest->getQueryParams();
            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                Spec::paramsToJsonSchema($oSpec->QueryParams)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            $oRequest = $this->adjustValidationPayload($oRequest, $oValidator, new Dot($aParameters));
            $oRequest = $oRequest->withQueryParams((array) $oParameters);

            return $oRequest;
        }

        /**
         * @param Validator $oValidator
         * @param array $aParameters
         * @param Dot $oPayload
         * @return Dot
         */
        private function adjustValidationPayload(ServerRequestInterface $oRequest, Validator $oValidator, Dot $oParameters): ServerRequestInterface {
            $oBuilder = ResponseBuilder::get($oRequest);

            if (!$oValidator->isValid()) {
                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    // convert from array property `param[index]` to `param.index`
                    $sProperty = str_replace('[', '.', $aError['property']);
                    $sProperty = str_replace(']', '',  $sProperty);

                    $aError['value'] = $oParameters->get($sProperty);
                    $aErrors[]       = $aError;
                }

                $oBuilder->set('_request.validation.status', 'FAIL');
                $oBuilder->set('_request.validation.errors', $aErrors);

                $this->bValid = false;
            } else if ($this->bValid) { // Could have been set by last Validation
                $oBuilder->set('_request.validation.status', 'PASS');
            }

            return ResponseBuilder::update($oRequest, $oBuilder);
        }
    }