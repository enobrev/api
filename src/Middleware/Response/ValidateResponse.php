<?php
    namespace Enobrev\API\Middleware\Response;

    use BenMorel\OpenApiSchemaToJsonSchema\Converter\SchemaConverter;
    use cebe\openapi\ReferenceContext;
    use cebe\openapi\spec\OpenApi;
    use cebe\openapi\spec\Reference;
    use cebe\openapi\spec\Responses;
    use cebe\openapi\SpecObjectInterface;
    use ReflectionException;
    
    use Adbar\Dot;
    use BenMorel\OpenApiSchemaToJsonSchema\Convert;
    use cebe\openapi\spec\Schema as OpenApi_Schema;
    use cebe\openapi\spec\Response as OpenApi_Response;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;
    use Middlewares;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception;
    use Enobrev\API\Exception\ValidationException;
    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Schema;
    use Enobrev\API\HTTP;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Spec;
    use Enobrev\Log;

    class ValidateResponse implements MiddlewareInterface {
        private $bThrowException = false;

        public function __construct($bThrowException = false) {
            $this->bThrowException = $bThrowException;
        }

        /**
         * @param ServerRequestInterface  $oRequest
         * @param RequestHandlerInterface $oHandler
         *
         * @return ResponseInterface
         * @throws Exception\HttpErrorException
         * @throws ReflectionException
         */
        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oTimer = Log::startTimer('Enobrev.Middleware.Response.ValidateResponse');
            $oSpec = AttributeSpec::getSpec($oRequest);

            if ($oSpec instanceof Spec === false) {
                Log::dt($oTimer);
                return $oHandler->handle($oRequest);
            }

            $oRequest = $this->validateResponse($oRequest);

            Log::dt($oTimer);
            return $oHandler->handle($oRequest);
        }

        /**
         * @param ServerRequestInterface $oRequest
         *
         * @return ServerRequestInterface
         * @throws Exception\HttpErrorException
         * @throws ReflectionException
         * @throws \BenMorel\OpenApiSchemaToJsonSchema\Exception\InvalidInputException
         * @throws \BenMorel\OpenApiSchemaToJsonSchema\Exception\InvalidTypeException
         * @throws \cebe\openapi\exceptions\TypeErrorException
         * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
         */
        private function validateResponse(ServerRequestInterface $oRequest): ServerRequestInterface {
            $oSpec          = AttributeSpec::getSpec($oRequest);
            $oSpecResponses = $oSpec->getResponses();
            if ($oSpecResponses instanceof Responses) {
                $oOpenApi       = FullSpec::getInstance()->getOpenApi();
                $oSpecResponse  = $oSpecResponses->getResponse(200);
                $oRefContext    = new ReferenceContext($oOpenApi, '/');

                if ($oSpecResponse instanceof Reference) {
                    $oSpecResponse = $oSpecResponse->resolve($oRefContext);
                }

                $oSpecResponse->resolveReferences($oRefContext);

                /** @var OpenApi_Schema $oSchema */
                $oSchema                = $oSpecResponse->content['application/json']->schema;
                $oWithMergedAllOfs      = $this->mergeAllOfs($oSchema);

                // Convert Properties that look like {property_name} to use jsonSchema "patternProperties" .*
                $oWithPatternProperties = $this->findPatternProperties($oWithMergedAllOfs->getSerializableData());
                $oSpecSchema    = Convert::openapiSchemaToJsonSchema($oWithPatternProperties, ['supportPatternProperties'=> true ]);
                $aFullResponse  = ResponseBuilder::get($oRequest)->all();
                $oFullResponse  = json_decode(json_encode($aFullResponse));
                $oValidator     = new Validator;
                $oValidator->validate(
                    $oFullResponse,
                    $oSpecSchema,
                    Constraint::CHECK_MODE_APPLY_DEFAULTS
                );

                if ($oValidator->isValid() === false) {
                    $aErrors    = $this->getErrorsWithValues($oValidator, $aFullResponse);
                    $iErrors    = count($aErrors);
                    $aLogErrors = $iErrors > 5 ? array_slice($aErrors, 0, 5) : $aErrors;
                    
                    Log::e('Enobrev.Middleware.Response.ValidateResponse', [
                        'path'          => $oSpec->getPath(),
                        'error_count'   => $iErrors,
                        'errors'        => json_encode($aLogErrors)
                    ]);

                    if ($this->bThrowException) {
                        throw ValidationException::create(HTTP\BAD_RESPONSE, $aErrors);
                    }
                }
            }

            return $oRequest;
        }

        // Merge AllOf Because allOf in json-schema does not mean merge, it means match ALL entries and that's now how we're using it
        private function mergeAllOfs($oSchema)  {
            if (isset($oSchema->allOf)) {
                $oMerged = new Dot;
                foreach($oSchema->allOf as $oSubSchema) {
                    $aSubSchema = json_decode(json_encode($oSubSchema->getSerializableData()), true);
                    $oMerged->mergeRecursiveDistinct($aSubSchema);
                }
                $oSchema = new OpenApi_Schema($oMerged->all());
            } else if (isset($oSchema->oneOf)) {
                $aOptions = [];
                foreach($oSchema->oneOf as $oSubSchema) {
                    $aOptions[] = $this->mergeAllOfs($oSubSchema);
                }
                $oSchema->oneOf = $aOptions;
            }

            return $oSchema;
        }

        // If we find a property name that looks like {property}, we replace the parent "properties" with patternProperties to treat it as a dynamic property
        private function findPatternProperties($oSchema) {
            foreach($oSchema as $sProperty => $oSubSchema) {
                if (is_object($oSubSchema) || is_array($oSubSchema)) {
                    $bFoundDynamicProperty = false;
                    foreach($oSubSchema as $sSubProperty => $oSubSubSchema) {
                        if (preg_match('/^{|}$/', $sSubProperty, $aMatches)) {
                            $bFoundDynamicProperty = true;
                            $oSchema->{'x-patternProperties'} = new \stdClass();
                            $oSchema->{'x-patternProperties'}->{'.*'} = $this->findPatternProperties($oSubSubSchema);
                        }
                    }

                    if($bFoundDynamicProperty) {
                        unset($oSchema->properties);
                    } else if (is_object($oSchema)) {
                        $oSchema->$sProperty = $this->findPatternProperties($oSubSchema);
                    } else if (is_array($oSchema)) {
                        $oSchema[$sProperty] = $this->findPatternProperties($oSubSchema);
                    }
                }
            }

            return $oSchema;
        }

        private function getErrorsWithValues(Validator $oValidator, ?array $aParameters): ?array {
            if ($oValidator->isValid()) {
                return null;
            }

            $aErrors = [];
            $oParameters = new Dot($aParameters);

            $aErrorProperties = [];

            foreach ($oValidator->getErrors() as $aError) {
                if (empty($aError['property']) && is_array($aError['constraint']) && $aError['constraint']['name'] === 'additionalProp') {
                    $aError['property'] = $aError['constraint']['params']['property'];
                    $aError['value']    = $oParameters->get($aError['property']);
                } else {
                    // convert from array property `param[index]` to `param.index`
                    $sProperty       = str_replace(['[', ']'], ['.', ''], $aError['property']);
                    $aError['value'] = $oParameters->get($sProperty);
                }

                // only one error per property
                if (isset($aError['property'])) {
                    if (isset($aErrorProperties[$aError['property']])) {
                        if (
                            (
                                is_array($aError['constraint']) && isset($aError['constraint']['name']) && in_array($aError['constraint']['name'], ['type', 'anyOf'])
                            )
                        ||  in_array($aError['constraint'], ['type', 'anyOf'])) {
                            // An error on a nullable field , like lets say a maxLength error on a nullable field
                            // Will add two additional errors - one because the value is not null, and one because
                            // The field isn't matching either of the "anyOf" (which includes the original and the null
                            // This way we skip those extra errors as they're not useful.
                            continue;
                        }
                    }

                    $aErrorProperties[$aError['property']] = 1;
                }

                $aErrors[] = $aError;
            }

            return $aErrors;
        }
    }