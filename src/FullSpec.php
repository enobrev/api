<?php
    namespace Enobrev\API;

    use Adbar\Dot;

    class FullSpec {
        /** @var Dot */
        private $oData;

        /** @var array */
        private $aInfo;

        /** @var array */
        private $aSchemas;

        /** @var array */
        private $aResponses;

        /** @var Spec[] */
        private $aPaths;

        public function __construct(array $aInfo) {
            $this->aPaths     = [];
            $this->aResponses = [];
            $this->aSchemas   = [];
            $this->aInfo      = $aInfo;
        }

        public function schemas($sSchema, $aSchema) {
            $this->aSchemas[$sSchema] = $aSchema;
        }

        /**
         * @param string $sName
         * @param string $sDescription
         * @param array $aContent
         */
        public function responses(string $sName, string $sDescription, array $aContent) {
            $this->aResponses[$sName] = [
                'description' => $sDescription,
                'content'     => $aContent
            ];
        }

        public function defaultSchemaResponse(string $sResponse) {
            $this->responses($sResponse, "A successful response object with the $sResponse data and the standard metadata", [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => "#/components/schemas/_default"],
                            ['$ref' => "#/components/schemas/$sResponse"],
                        ]
                    ]
                ]
            ]);
        }

        public function paths(Spec $oSpec) {
            $this->aPaths["{$oSpec->HttpMethod}.{$oSpec->Path}"] = $oSpec;
        }

        public function getPath(string $sHttpMethod, string $sPath): ?Spec {
            return $this->aPaths["{$sHttpMethod}.{$sPath}"] ?? null;
        }

        /**
         * @param string $sAPIUrl
         * @param string $sDescription
         * @param array $aScopes
         * @return Dot
         */
        public function generateOpenAPI(string $sAPIUrl, string $sAPIDescription, array $aScopes = []) {
            $aSecurityFlows = [
                'password' => [
                    'tokenUrl'    => $sAPIUrl . 'auth/client',
                    'refreshUrl'  => $sAPIUrl . 'auth/client',
                    'x-grantType' => 'password',
                    'scopes'      => [
                        'www' => 'General access for the Web Client',
                        'ios' => 'General access for the IOS Client',
                        'cms' => 'General access for the CMS Client'
                    ]
                ],
                'clientCredentials' => [
                    'tokenUrl'    => $sAPIUrl . 'auth/client',
                    'refreshUrl'  => $sAPIUrl . 'auth/client',
                    'x-grantType' => 'client_credentials',
                    'scopes'      => [
                        's2s' => 'General access for backend clients'
                    ]
                ],
                'facebook' => [
                    'tokenUrl'    => $sAPIUrl . 'auth/client',
                    'refreshUrl'  => $sAPIUrl . 'auth/client',
                    'x-grantType' => 'facebook',
                    'scopes'      => [
                        'www' => 'General access for the Web Client',
                        'ios' => 'General access for the IOS Client',
                        'cms' => 'General access for the CMS Client'
                    ]
                ]
            ];

            $aFlows = [];
            if (count($aScopes)) {
                foreach($aSecurityFlows as $sFlow => $aSecurityFlow) {
                    if (count(array_intersect($aScopes, array_keys($aSecurityFlow['scopes'])))) {
                        $aFlows[$sFlow] = $aSecurityFlow;
                        $aFlows[$sFlow]['scopes'] = array_intersect_key($aFlows[$sFlow]['scopes'], array_flip($aScopes));
                    }
                }
            } else {
                $aFlows = $aSecurityFlows;
            }

            $oData = new Dot([
                'openapi' => '3.0.1',
                'info'    => $this->aInfo,
                'servers' => [
                    [
                        'url'         => $sAPIUrl,
                        'description' => $sAPIDescription
                    ]
                ],
                'paths'         => [],
                'components'   => [
                    'schemas' => self::DEFAULT_RESPONSE_SCHEMAS,
                    'securitySchemes' => [
                        'OAuth2' => [
                            'type'  => 'oauth2',
                            'flows' => $aFlows
                        ]
                    ]
                ]
            ]);

            $oData->set('components.schemas._any', (object) []);

            $oData->mergeRecursiveDistinct("components.responses", $this->aResponses);
            $oData->mergeRecursiveDistinct("components.schemas", $this->aSchemas);

            /**
             * @var string $sPath
             * @var Spec $oSpec
             */

            foreach($this->aPaths as $oSpec) {
                if (count($aScopes)) {
                    if (count($oSpec->Scopes) && count(array_intersect($aScopes, $oSpec->Scopes))) {
                        $sMethod = strtolower($oSpec->HttpMethod);
                        $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                    }
                } else {
                    $sMethod = strtolower($oSpec->HttpMethod);
                    $oData->set("paths.{$oSpec->Path}.{$sMethod}", $oSpec->generateOpenAPI());
                }
            }

            return $oData;
        }

        public function get($sPath) {
            return $this->oData->get($sPath);
        }

        public function set($sPath, $mValue) {
            return $this->oData->set($sPath, $mValue);
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
