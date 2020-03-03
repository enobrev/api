<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;

    class _Integer extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::INTEGER;

        /**
         *
            "int32": Signed 32-bit integers (commonly used integer type).
            "int64": Signed 64-bit integers (long type)..
         * @param string $sFormat
         * @return _Integer
         */
        public function format(string $sFormat): self {
            return $this->validation(['format' => $sFormat]);
        }

        public function minimum(int $iMinimum, $bExclusive = false): self {
            if ($bExclusive) {
                return $this->validation(['minimum' => $iMinimum])->validation(['exclusiveMinimum' => $bExclusive]);
            }
            return $this->validation(['minimum' => $iMinimum]);
        }

        public function maximum(int $iMaximum, $bExclusive = false): self {
            if ($bExclusive) {
                return $this->validation(['maximum' => $iMaximum])->validation(['exclusiveMaximum' => $bExclusive]);
            }
            return $this->validation(['maximum' => $iMaximum]);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return int
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_numeric($mValue)) {
                /** @noinspection OpAssignShortSyntaxInspection */
                $mValue = $mValue + 0; // cast to number
            }

            /*
            if (is_bool($mValue) || is_null($mValue)) {
                $mValue = (int) $mValue;
            }

            if (is_array($mValue) && count($mValue) === 1) {
                $mValue = $this->coerce(reset($mValue));
            }
            */

            if (is_numeric($mValue) && (int) $mValue == $mValue) {
                return (int) $mValue; // cast to number
            }

            return $mValue;
        }
        
        /**
         * @param array $aSchema
         * @return Param\_String
         */
        public static function createFromJsonSchema(array $aSchema) {
            $oParam = self::create();

            if (isset($aSchema['minimum'])) {
                $oParam = $oParam->minimum($aSchema['minimum']);
            }

            if (isset($aSchema['maximum'])) {
                $oParam = $oParam->maximum($aSchema['maximum']);
            }

            if (isset($aSchema['format'])) {
                $oParam = $oParam->format($aSchema['format']);
            }

            return $oParam;
        }
    }