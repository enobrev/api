<?php
    namespace Enobrev\API;

    use Adbar\Dot;
    use Enobrev\API\Exception\DocumentationException;
    use Enobrev\API\Exception\InvalidRequest;
    use function Enobrev\dbg;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Field;
    use JsonSchema\Constraints\Constraint;
    use JsonSchema\Validator;

    class Spec {
        /** @var Response */
        public $Request;

        /** @var Response */
        public $Response;

        /** @var string */
        public $Summary;

        /** @var string */
        public $Description;

        /** @var string */
        public $Method;

        /** @var string[] */
        public $Scopes;

        /** @var Param[] */
        public $InParams = [];

        /** @var array */
        public $OutParams = [];

        /** @var array Expected Schemas */
        public $OutTypes = [];

        /** @var Dot Full Schema Objects for components.schemas */
        public $OutSchemas;

        /** @var array Collections of full Schema Objects for components.schemas  */
        public $OutCollections = [];

        /** @var Param[] */
        public $InHeaders = [];

        /** @var Param[] */
        public $OutHeaders = [];

        /** @var array */
        public $CodeSamples = [];

        /**
         * Spec constructor.
         * @param Request $oRequest
         * @param Response $oResponse
         */
        public function __construct(Request $oRequest,  Response $oResponse) {
            $this->Request  = $oRequest;
            $this->Response = $oResponse;
            $this->OutSchemas = new Dot;
        }

        public function summary(string $sSummary):self {
            $this->Summary = $sSummary;
            return $this;
        }

        public function description(string $sDescription):self {
            $this->Description = $sDescription;
            return $this;
        }

        public function method(string $sMethod):self {
            $this->Method = $sMethod;
            return $this;
        }

        public function scopes(array $aScopes):self {
            $this->Scopes = $aScopes;
            return $this;
        }

        public function inParams(array $aParams):self {
            $this->InParams = $aParams;
            return $this;
        }

        public function inTable(Table $oTable):self {
            $aParams = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                switch(true) {
                    default:
                    case $oField instanceof Field\Text:    $sType = Param::STRING;  break;
                    case $oField instanceof Field\Boolean: $sType = Param::BOOLEAN; break;
                    case $oField instanceof Field\Integer: $sType = Param::INTEGER; break;
                    case $oField instanceof Field\Number:  $sType = Param::NUMBER;  break;
                }

                $sField = DataMap::getPublicName($oTable, $oField->sColumn);
                if (!$sField) {
                    continue;
                }

                if ($oField instanceof Field\Enum) {
                    $aParams[$sField] = new Param($sField, $sType, ['enum' => $oField->aValues]);
                } else {
                    $aParams[$sField] = new Param($sField, $sType);
                }
            }

            return $this->inParams($aParams);
        }

        public function outParams(array $aParams):self {
            $this->OutParams = $aParams;
            return $this;
        }

        public function outSchema(string $sName, array $aSchema):self {
            $this->OutSchemas->mergeRecursiveDistinct($sName, $aSchema);
            return $this;
        }

        public function outTable(string $sName, Table $oTable) {
            $aDefinitions = [];
            $aFields = $oTable->getColumnsWithFields();

            foreach($aFields as $oField) {
                switch(true) {
                    default:
                    case $oField instanceof Field\Text:    $sType = Param::STRING;  break;
                    case $oField instanceof Field\Boolean: $sType = Param::BOOLEAN; break;
                    case $oField instanceof Field\Integer: $sType = Param::INTEGER; break;
                    case $oField instanceof Field\Number:  $sType = Param::NUMBER;  break;
                }

                $sField = DataMap::getPublicName($oTable, $oField->sColumn);
                if (!$sField) {
                    continue;
                }

                if ($oField instanceof Field\Enum) {
                    $aDefinitions[$sField] = new Param($sField, $sType, ['enum' => $oField->aValues]);
                } else {
                    $aDefinitions[$sField] = new Param($sField, $sType);
                }
            }

            return $this->outSchema($sName, $aDefinitions);
        }

        public function outCollection(string $sName, string $sKey, string $sReference):self {
            $this->OutCollections[$sName] = [
                'key'       => $sKey,
                'reference' => $sReference
            ];
            return $this;
        }

        public function inHeaders(array $aHeaders):self {
            $this->InHeaders = $aHeaders;
            return $this;
        }

        public function outHeaders(array $aHeaders):self {
            $this->OutHeaders = $aHeaders;
            return $this;
        }

        public function codeSample(string $sLanguage, string $sSource):self {
            $this->CodeSamples[$sLanguage] = $sSource;
            return $this;
        }

        /**
         * @throws DocumentationException
         */
        private function documentation() {
            $bRequestedDocumentation = $this->Request->OriginalRequest->hasHeader('X-Welcome-Docs');

            if ($bRequestedDocumentation) {
                $this->Response->add('openapi', (object) [
                    'paths' => [
                        '/' => $this->generateOpenAPI('/', '__class__')
                    ],
                    'components' => [
                        'schemas' => $this->generateOpenAPISchemas('/')
                    ]
                ]);
                $this->Response->add('jsonschema', (object) $this->paramsToJsonSchema($this->InParams)->all());

                throw new DocumentationException();
            }

        }

        /**
         * @throws DocumentationException
         * @throws InvalidRequest
         */
        public function ready() {
            $this->documentation();

            $aParameters = [];
            switch ($this->Method) {
                case Method\GET:  $aParameters = $this->Request->GET;  break;
                case Method\POST: $aParameters = $this->Request->POST; break;
            }

            $oParameters = (object) $aParameters;
            $oValidator  = new Validator;
            $oValidator->validate(
                $oParameters,
                $this->paramsToJsonSchema($this->InParams)->all(),
                Constraint::CHECK_MODE_APPLY_DEFAULTS | Constraint::CHECK_MODE_ONLY_REQUIRED_DEFAULTS
            );

            if (!$oValidator->isValid()) {
                $oDot = new Dot();
                $oDot->set('parameters', $aParameters);

                $aErrors = [];
                foreach($oValidator->getErrors() as $aError) {
                    $aError['value'] = $oDot->get($aError['property']);
                    $aErrors[]       = $aError;
                }

                $this->Response->add('_request.validation.status', 'FAIL');
                $this->Response->add('_request.validation.errors', $aErrors);

                throw new InvalidRequest();
            } else {
                $this->Response->add('_request.validation.status', 'PASS');
                $this->Request->ValidatedParams = (array) $oParameters;
            }
        }

        private function paramsToResponseSchema(array $aParams): Dot {
            $oSchema = $this->paramsToJsonSchema($aParams);

            $oSchema->set("properties._server", ['$ref' => "#/components/schemas/_server"]);
            $oSchema->set("properties._request", ['$ref' => "#/components/schemas/_request"]);

            return $oSchema;
        }

        private function paramsToJsonSchema(array $aParams): Dot {
            $oSchema = new Dot([
                "type" => "object",
                "additionalProperties" => false
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

        public function generateOpenAPI(string $sPath, string $sTarget, array $aRouteParams = []): array {
            $aMethod = [
                'summary'       => $this->Summary ?? $sPath,
                'description'   => $this->Description ?? $this->Summary ?? $sPath,
                'tags'          => [$sTarget]
            ];

            if (count($this->Scopes)) {
                $aMethod['security'] = [["OAuth2" => $this->Scopes]];
            }


            $oInJsonParams = $this->paramsToJsonSchema($this->InParams);
            $aParameters   = [];

            foreach($this->InParams as $oParam) {
                if (strpos($oParam->sName, '.') !== false) {
                    continue;
                }

                if ($oParam->is(Param::OBJECT)) {
                    $aParam = $oParam->OpenAPI();
                    $aParam['schema'] = $oInJsonParams->get("properties.{$oParam->sName}");
                    $aParameters[] = $aParam;
                } else {
                    $aParameters[] = $oParam->OpenAPI();
                }
            }

            if (count($aRouteParams)) {
                foreach($aParameters as &$aParameter) {
                    if (in_array($aParameter['name'], $aRouteParams)) {
                        $aParameter['in'] = 'path';
                        break;
                    }
                }
            }

            if (count($aParameters)) {
                $aMethod['parameters'] = $aParameters;
            }

            $aResponses = [];

            foreach($this->OutSchemas as $sOutputType => $aOutSchema) {
                $aResponses[] = ['$ref' => "#/components/schemas/$sOutputType"];
            }

            if (count($this->OutParams)) {
                $sCleaned = $this->cleanAPISchemaPath($sPath);
                $aResponses[] = ['$ref' => "#/components/schemas/$sCleaned"];
            }

            if (!count($aResponses)) {
                $aResponses[] = ['$ref' => "#/components/schemas/_default"];
            }

            $aMethod['responses'] = [
                HTTP\OK => [
                    "description" => "Success",
                    "content" => [
                        "application/json" => [
                            "schema" => [
                                "allOf" => $aResponses,
                            ]
                        ]
                    ]
                ]
            ];

            if (count($aParameters)) {
                $aMethod['responses'][HTTP\BAD_REQUEST] = [
                    "description" => "Problem with Request.  See _request.validation for details"
                ];
            }

            if (count($this->CodeSamples)) {
                foreach($this->CodeSamples as $sLanguage => $sSource) {
                    $aMethod['x-code-samples'][] = [
                        'lang'   => $sLanguage,
                        'source' => str_replace('{{PATH}}', $sPath, $sSource)
                    ];
                }
            }

            return $aMethod;
        }

        private function cleanAPISchemaPath(string $sPath) {
            $sCleaned = trim($sPath, '/');
            $sCleaned = 'path-' . preg_replace('/[^a-z0-9]/', '', $sCleaned);
            return $sCleaned;
        }

        public function generateOpenAPISchemas(string $sPath):array {
            $aDefinitions = [];
            foreach ($this->OutSchemas->all() as $sDefinition => $aParams) {
                $aDefinitions[$sDefinition] = $this->paramsToResponseSchema($aParams)->all();
            }

            if (count($this->OutParams)) {
                $sCleaned = $this->cleanAPISchemaPath($sPath);

                $aDefinitions[$sCleaned] = $this->paramsToResponseSchema($this->OutParams)->all();
            }

            return $aDefinitions;
        }

        const DEFAULT_RESPONSE_SCHEMAS = [
            "_default" => [
                "type" => "object",
                "properties" => [
                    "_server" => [
                        '$ref' => "#/components/schemas/_server"
                    ],
                    "_request" => [
                        '$ref' => "#/components/schemas/_request"
                    ]
                ]
            ],
            "_server" => [
                "type" => "object",
                "properties"=> [
                    "timezone"      => ["type" => "string"],
                    "timezone_gmt"  => ["type" => "string"],
                    "date"          => ["type" => "string"],
                    "date_w3c"      => ["type" => "string"]
                ],
                "additionalProperties"=> false
            ],
            "_request" => [
                "type" => "object",
                "properties"=> [
                    "validation" => [
                        "type" => "object",
                        "properties" => [
                            "status" => [
                                "type" => "string",
                                "enum" => ["PASS", "FAIL"]
                            ],
                            "errors" => [
                                "type" => "array",
                                "items" => ['$ref' => "#/components/schemas/_validation_error"]
                            ]
                        ]
                    ],
                    "logs"      => [
                        "type" => "object",
                        "properties" => [
                            "thread" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up entire request thread in logs"
                            ],
                            "request" => [
                                "type" => "string",
                                "description" => "Alphanumeric hash for looking up specific API request in logs"
                            ]
                        ]
                    ],
                    "method"        => [
                        "type" => "string",
                        "enum" => ["GET", "POST", "PUT", "DELETE"]
                    ],
                    "path"          => ["type" => "string"],
                    "attributes"    => [
                        "type" => "array",
                        "description" => "Parameters pulled from the path",
                        "items" => ["type" => "string"]
                    ],
                    "query"         => [
                        "type" => "array",
                        "items" => ["type" => "string"]
                    ],
                    "data"          => [
                        '$ref' => '#/components/schemas/_any',
                        "description" => "POSTed Data"
                    ]
                ],
                "additionalProperties"=> false
            ],
            "_response" => [
                "type" => "object",
                "properties"=> [
                    "validation" => [
                        "type" => "object",
                        "properties" => [
                            "status" => [
                                "type" => "string",
                                "enum" => ["PASS", "FAIL"]
                            ],
                            "errors" => [
                                "type" => "array",
                                "items" => ['$ref' => "#/components/schemas/_validation_error"]
                            ]
                        ]
                    ]
                ],
                "additionalProperties"=> false
            ],
            "_validation_error" => [
                "type" => "object",
                "properties" => [
                    "property"      => ["type" => "string"],
                    "pointer"       => ["type" => "string"],
                    "message"       => ["type" => "string"],
                    "constraint"    => ["type" => "string"],
                    "context"       => ["type" => "number"],
                    "minimum"       => ["type" => "number"],
                    "value"         => [
                        '$ref' => '#/components/schemas/_any'
                    ]
                ]
            ]
        ];
    }