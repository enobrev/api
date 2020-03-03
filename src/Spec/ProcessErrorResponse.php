<?php
    namespace Enobrev\API\Spec;

    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\Component\Reference;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;
    use Enobrev\API\HTTP;

    class ProcessErrorResponse implements OpenApiInterface, ErrorResponseInterface {
        use ErrorResponseTrait;

        /** @var number */
        private $iCode = HTTP\UNPROCESSABLE_ENTITY;
        
        /** @var string */
        private $sMessage;

        public function getOpenAPI(): array {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'process' => Param\_Object::create()->items([
                            'code'    => Param\_Integer::create()->minimum(100)->maximum(511)->default($this->iCode)->example($this->iCode),
                            'message' => Param\_String::create()->default($this->sMessage)->example($this->sMessage)
                        ])
                    ]
                ]
            ])->getOpenAPI();
        }

        public function getSpecObject(): SpecObjectInterface {
            return JsonResponse::allOf([
                Reference::create(FullSpec::SCHEMA_DEFAULT),
                [
                    '_errors' =>  [
                        'process' => Param\_Object::create()->items([
                            'code'    => Param\_Integer::create()->minimum(100)->maximum(511)->default($this->iCode)->example($this->iCode),
                            'message' => Param\_String::create()->default($this->sMessage)->example($this->sMessage)
                        ])
                    ]
                ]
            ])->getSpecObject();
        }
    }