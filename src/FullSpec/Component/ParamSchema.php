<?php
    namespace Enobrev\API\FullSpec\Component;

    use cebe\openapi\SpecObjectInterface;

    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;
    use Enobrev\API\Param;

    class ParamSchema implements ComponentInterface, OpenApiInterface {
        public const PREFIX = 'schemas';

        private string $sName;

        private Param\_Object $oParam;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, self::PREFIX);
            } else if ($aName[0] !== self::PREFIX) {
                array_unshift($aName, self::PREFIX);
            }

            $this->sName = implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function param(Param\_Object $oParam):self {
            $this->oParam = $oParam;
            return $this;
        }

        public function getParam():Param\_Object {
            return $this->oParam;
        }

        /**
         * @return SpecObjectInterface
         */
        public function getSpecObject(): SpecObjectInterface {
            return $this->oParam->getSchema();
        }
    }