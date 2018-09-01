<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use function Enobrev\array_not_associative;
    use function Enobrev\dbg;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    use Enobrev\API\Exception\InvalidRequest;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Field;

    class Spec {
        const SKIP_PRIMARY = 1;

        /** @var string */
        private $sSummary;

        /** @var string */
        private $sDescription;

        /** @var boolean */
        private $bRequestValidated = false;

        /** @var boolean */
        private $bDeprecated;

        /** @var string */
        private $sPath;

        /** @var boolean */
        private $bPublic = false;

        /** @var string */
        private $sHttpMethod;

        /** @var string */
        private $sMethod;

        /** @var string[] */
        private $aScopes;

        /** @var Param[] */
        private $aPathParams = [];

        /** @var Param[] */
        private $aQueryParams = [];

        /** @var Param[] */
        private $aPostParams = [];

        /** @var array */
        private $aResponseSchema;

        /** @var string */
        private $sResponseReference;

        /** @var Param[] */
        private $aInHeaders = [];

        /** @var Param[] */
        private $aOutHeaders = [];

        /** @var array */
        private $aCodeSamples = [];

        /** @var array */
        private $aResponseHeaders = [];

        /** @var string[] */
        private $aTags = [];

        /** @var array */
        private $aResponses = [
            HTTP\OK => 'Success',
        ];

        public static function create() {
            return new self();
        }

        public function getPath():string {
            return $this->sPath;
        }

        public function getHttpMethod():string {
            return $this->sHttpMethod;
        }

        public function getLowerHttpMethod():string {
            return strtolower($this->sHttpMethod);
        }

        public function getScopeList(string $sDivider = ' '): string {
            return implode($sDivider, $this->aScopes);
        }

        public function hasAnyOfTheseScopes(array $aScopes): bool {
            if (count($this->aScopes) === 0) {
                return false;
            }

            return count(array_intersect($aScopes, $this->aScopes)) > 0;
        }

        public function hasAllOfTheseScopes(array $aScopes): bool {
            if (count($this->aScopes)) {
                return false;
            }

            return count(array_intersect($aScopes, $this->aScopes)) == count($aScopes);
        }

        /**
         * @return Param[]
         */
        public function getPathParams(): array {
            return $this->aPathParams;
        }

        /**
         * @return Param[]
         */
        public function getQueryParams(): array {
            return $this->aQueryParams;
        }

        /**
         * @return Param[]
         */
        public function getPostParams(): array {
            return $this->aPostParams;
        }

        public function isPublic():bool {
            return $this->bPublic;
        }

        public function pathParamsToJsonSchema():array {
            return self::paramsToJsonSchema($this->aPathParams)->all();
        }

        public function queryParamsToJsonSchema():array {
            return self::paramsToJsonSchema($this->aQueryParams)->all();
        }

        public function postParamsToJsonSchema():array {
            return self::paramsToJsonSchema($this->aPostParams)->all();
        }

        public function summary(string $sSummary):self {
            $this->sSummary = $sSummary;
            return $this;
        }

        public function description(string $sDescription):self {
            $this->sDescription = $sDescription;
            return $this;
        }

        public function deprecated(?bool $bDeprecated = true):self {
            $this->bDeprecated = $bDeprecated;
            return $this;
        }

        public function path(string $sPath):self {
            $this->sPath = $sPath;
            return $this;
        }

        public function httpMethod(string $sHttpMethod):self {
            $this->sHttpMethod = $sHttpMethod;
            return $this;
        }

        public function method(string $sMethod):self {
            $this->sMethod = $sMethod;
            return $this;
        }

        /**
         * @param array $aScopes
         * @return Spec
         * @throws Exception
         */
        public function scopes(array $aScopes):self {
            if (!array_not_associative($aScopes)) {
                throw new Exception('Please define Scopes as a non-Associative Array');
            }
            $this->aScopes = $aScopes;
            return $this;
        }

        public function setPublic(bool $bPublic = true):self {
            $this->bPublic = $bPublic;
            return $this;
        }

        public function pathParams(array $aParams):self {
            $this->aPathParams = $aParams;
            return $this;
        }

        public function queryParams(array $aParams):self {
            $this->aQueryParams = $aParams;
            return $this;
        }

        public function postParams(array $aParams):self {
            $this->aPostParams = $aParams;
            return $this;
        }

        public function responseHeader(string $sHeader, string $sValue):self {
            $this->aResponseHeaders[$sHeader] = $sValue;
            return $this;
        }

        public function removeResponse(int $iStatus):self {
            unset($this->aResponses[$iStatus]);
            return $this;
        }

        public function response(int $iStatus, string $sDescription):self {
            $this->aResponses[$iStatus] = $sDescription;
            return $this;
        }

        public function tags(array $aTags):self {
            $this->aTags += $aTags;
            $this->aTags = array_unique($aTags);
            return $this;
        }

        public function tag(string $sName):self {
            $this->aTags[] = $sName;
            return $this;
        }

        public function inTable(Table $oTable):self {
            return $this->queryParams(self::tableToParams($oTable));
        }

        public function responseSchema(array $aSchema):self {
            $this->aResponseSchema = $aSchema;
            return $this;
        }

        public function responseReference(string $aReference):self {
            $this->sResponseReference = $aReference;
            return $this;
        }

        public static function tableToJsonSchema(Table $oTable, int $iOptions = 0) {
            return self::paramsToJsonSchema(self::tableToParams($oTable, $iOptions));
        }

        /**
         * @param Table $oTable
         * @param int $iOptions
         * @return Param[]
         */
        public static function tableToParams(Table $oTable, int $iOptions = 0) {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                if ($iOptions & self::SKIP_PRIMARY && $oField->isPrimary()) {
                    continue;
                }

                $oParam = self::fieldToParam($oTable, $oField);
                if ($oParam instanceof Param) {
                    $aDefinitions[$oParam->sName] = $oParam;
                }
            }

            return $aDefinitions;
        }

        /**
         * @param Table $oTable
         * @param Field $oField
         * @return Param
         */
        public static function fieldToParam(Table $oTable, Field $oField): ?Param {
            switch(true) {
                default:
                case $oField instanceof Field\Text:    $iType = Param::STRING;  break;
                case $oField instanceof Field\Boolean: $iType = Param::BOOLEAN; break;
                case $oField instanceof Field\Integer: $iType = Param::INTEGER; break;
                case $oField instanceof Field\Number:  $iType = Param::NUMBER;  break;
            }

            $sField = DataMap::getPublicName($oTable, $oField->sColumn);
            if (!$sField) {
                return null;
            }

            $aValidations = [];

            switch(true) {
                case $oField instanceof Field\Enum:
                    $aValidations['enum'] = $oField->aValues;
                    break;

                case $oField instanceof Field\TextNullable:
                    $aValidations['nullable'] = true;
                    break;

                case $oField instanceof Field\DateTime:
                    $aValidations['format'] = "date-time";
                    break;

                case $oField instanceof Field\Date:
                    $aValidations['format'] = "date";
                    break;
            }

            if (strpos(strtolower($oField->sColumn), 'password') !== false) {
                $aValidations['format'] = "password";
            }

            if ($oField->hasDefault()) {
                if ($oField instanceof Field\Boolean) {
                    $aValidations['default'] = (bool) $oField->sDefault;
                } else {
                    $aValidations['default'] = $oField->sDefault;
                }
            }

            return new Param($sField, $iType, $aValidations);
        }

        public function inHeaders(array $aHeaders):self {
            $this->aInHeaders = $aHeaders;
            return $this;
        }

        public function outHeaders(array $aHeaders):self {
            $this->aOutHeaders = $aHeaders;
            return $this;
        }

        public function codeSample(string $sLanguage, string $sSource):self {
            $this->aCodeSamples[$sLanguage] = $sSource;
            return $this;
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        public function validateRequest(Request $oRequest,  Response $oResponse) {
            $this->bRequestValidated = true;

            $this->validatePathParameters($oRequest, $oResponse);
            $this->validateQueryParameters($oRequest, $oResponse);
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validatePathParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->pathParams();

            $this->validateParameters($this->aPathParams, $aParameters, $oResponse);
        }

        /**
         * @param Request $oRequest
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateQueryParameters(Request $oRequest, Response $oResponse) {
            $aParameters = $oRequest->queryParams();

            $this->validateParameters($this->aQueryParams, $aParameters, $oResponse);
        }

        /**
         * @param array $aParameters
         * @param Response $oResponse
         * @throws InvalidRequest
         */
        private function validateParameters(array $aSpecParameters, array $aParameters, Response $oResponse) {
            // Coerce CSV Params
            foreach($aSpecParameters as $oParam) {
                if ($oParam->is(Param::ARRAY)) {
                    if (isset($aParameters[$oParam->sName])) {
                        $aParameters[$oParam->sName] = explode(',', $aParameters[$oParam->sName]);
                    }
                }
            }

            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                self::paramsToJsonSchema($aSpecParameters)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS | Constraint::CHECK_MODE_COERCE_TYPES
            );

            if (!$oValidator->isValid()) {
                $oDot = new Dot();
                $oDot->set('parameters', $aParameters);

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    $aError['value'] = $oDot->get($aError['property']);
                    $aErrors[]       = $aError;
                }

                $oResponse->add('_request.validation.status', 'FAIL');
                $oResponse->add('_request.validation.errors', $aErrors);

                throw new InvalidRequest();
            } else {
                $oResponse->add('_request.validation.status', 'PASS');
                //$oRequest->ValidatedParams = (array) $oParameters;
            }
        }

        public function paramsToResponseSchema(array $aParams): Dot {
            if (count($aParams) && isset($aParams['type']) && isset($aParams['properties'])) { // JSONSchema
                $oSchema = new Dot($aParams);
            } else {
                $oSchema = self::paramsToJsonSchema($aParams);
            }

            $oSchema->set("properties._server", ['$ref' => "#/components/schemas/_server"]);
            $oSchema->set("properties._request", ['$ref' => "#/components/schemas/_request"]);

            return $oSchema;
        }

        /**
         * @param array|Param[] $aParams
         * @return Dot
         */
        public static function paramsToJsonSchema(array $aParams): Dot {
            $oSchema = new Dot([
                "type" => "object",
                "additionalProperties" => true
            ]);

            /** @var Param $oParam */
            foreach ($aParams as $oParam) {
                $sName = $oParam->sName;

                if (strpos($sName, '.') !== false) {
                    $aName = explode(".", $sName);
                    $sFullName = implode(".properties.", $aName);

                    $oSchema->set("properties.$sFullName", $oParam->JsonSchema());

                    if ($oParam->required()) {
                        $aParent = explode(".", $sName);
                        array_pop($aParent);
                        $sParent = implode(".properties.", $aParent);

                        $aKid = explode(".", $sName);
                        array_shift($aKid);
                        $sKid = implode(".", $aKid);

                        $oSchema->push("properties.$sParent.required", $sKid);
                    }
                } else {
                    $oSchema->set("properties.$sName", $oParam->JsonSchema());
                    if ($oParam->required()) {
                        $oSchema->push('required', $oParam->sName);
                    }
                }
            }

            return $oSchema;
        }

        public function generateOpenAPI(): array {
            $aMethod = [
                'summary'       => $this->sSummary ?? $this->sPath,
                'description'   => $this->sDescription ?? $this->sSummary ?? $this->sPath,
                'tags'          => $this->aTags
            ];

            if (!$this->bPublic && count($this->aScopes)) {
                $aMethod['security'] = [["OAuth2" => $this->aScopes]];
            }

            if ($this->bDeprecated) {
                $aMethod['deprecated'] = true;
            }

            $oPathJsonParams = self::paramsToJsonSchema($this->aPathParams);

            foreach($this->aPathParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI('path');
                    $aParam['schema'] = $oPathJsonParams->get("properties.{$oParam->sName}");
                    $aParam['required'] = true;
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI('path');
                }
            }

            $oQueryJsonParams = self::paramsToJsonSchema($this->aQueryParams);
            $aParameters   = [];

            foreach($this->aQueryParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI('query');
                    $aParam['schema'] = $oQueryJsonParams->get("properties.{$oParam->sName}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI('query');
                }
            }

            if (count($aParameters)) {
                $aMethod['parameters'] = $aParameters;
            }

            $oPostJsonParams = self::paramsToJsonSchema($this->aPostParams);
            $aPost = [];

            foreach($this->aPostParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI(null);
                    $aParam['schema'] = $oPostJsonParams->get("properties.{$oParam->sName}");
                    $aPost[] = $aParam;
                } else {
                    $aPost[] = $oParam->OpenAPI(null);
                }
            }
            
            if (count($aPost)) {
                $aMethod['requestBody'] = [
                    "content" => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $aPost
                            ]
                        ]
                    ]
                ];
            }

            $aMethod['responses'] = [];

            foreach($this->aResponses as $iStatus => $sDescription) {
                if ($iStatus === HTTP\OK) {
                    if ($this->aResponseSchema) {
                        $aMethod['responses'][$iStatus] = [
                            "description" => $sDescription,
                            "content" => [
                                "application/json" => [
                                    "schema" => $this->aResponseSchema
                                ]
                            ]
                        ];
                    } else if ($this->sResponseReference) {
                        $aMethod['responses'][$iStatus] = ['$ref' => $this->sResponseReference];
                    } else {
                        $aMethod['responses'][$iStatus] = [
                            "description" => $sDescription,
                            "content" => [
                                "application/json" => [
                                    "schema" => ['$ref' => "#/components/schemas/_default"]
                                ]
                            ]
                        ];
                    }
                } else {
                    $aMethod['responses'][$iStatus] = [
                        "description" => $sDescription
                    ];
                }
            }


            if (count($aParameters)) {
                $aMethod['responses'][HTTP\BAD_REQUEST] = [
                    "description" => "Problem with Request.  See `_request.validation` for details"
                ];
            }

            if (count($this->aCodeSamples)) {
                foreach($this->aCodeSamples as $sLanguage => $sSource) {
                    $aMethod['x-code-samples'][] = [
                        'lang'   => $sLanguage,
                        'source' => str_replace('{{PATH}}', $this->sPath, $sSource)
                    ];
                }
            }

            return $aMethod;
        }

        public function toArray() {
            $aPathParams = [];
            foreach($this->aPathParams as $sParam => $oParam) {
                $aPathParams[$sParam] = $oParam->JsonSchema();
            }
            
            $aQueryParams = [];
            foreach($this->aQueryParams as $sParam => $oParam) {
                $aQueryParams[$sParam] = $oParam->JsonSchema();
            }

            $aPostParams = [];
            foreach($this->aPostParams as $sParam => $oParam) {
                $aPostParams[$sParam] = $oParam->JsonSchema();
            }
            
            return [
                'Summary'           => $this->sSummary,
                'Description'       => $this->sDescription,
                'RequestValidated'  => $this->bRequestValidated,
                'Deprecated'        => $this->bDeprecated,
                'Path'              => $this->sPath,
                'Public'            => $this->bPublic,
                'HttpMethod'        => $this->sHttpMethod,
                'Method'            => $this->sMethod,
                'Scopes'            => $this->aScopes,
                'PathParams'        => $aPathParams,
                'QueryParams'       => $aQueryParams,
                'PostParams'        => $aPostParams,
                'ResponseSchema'    => $this->aResponseSchema,
                'ResponseReference' => $this->sResponseReference,
                'InHeaders'         => $this->aInHeaders,
                'OutHeaders'        => $this->aOutHeaders,
                'CodeSamples'       => $this->aCodeSamples,
                'ResponseHeaders'   => $this->aResponseHeaders,
                'Responses'         => $this->aResponses,
                'Tags'              => $this->aTags
            ];
        }
        
        public function toJson() {
            return json_encode($this->toArray());
        }
    }