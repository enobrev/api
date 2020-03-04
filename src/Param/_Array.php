<?php
    namespace Enobrev\API\Param;
    
    use cebe\openapi\spec\Schema;
    use Enobrev\API\Exception;
    use Enobrev\API\Param;
    use Enobrev\API\ParamTrait;
    use function Enobrev\dbg;

    class _Array extends Param {
        use ParamTrait;

        /** @var string */
        protected $sType = Param::ARRAY;

        public function items(Param $oItems): self {
            return $this->validation(['items' => $oItems]);
        }

        public function minItems(int $iMinItems): self {
            return $this->validation(['minItems' => $iMinItems]);
        }

        public function maxItems(int $iMaxItems): self {
            return $this->validation(['maxItems' => $iMaxItems]);
        }

        public function uniqueItems(bool $bUniqueItems = true): self {
            return $this->validation(['uniqueItems' => $bUniqueItems]);
        }

        public function getSchema(): Schema {
            $aSchema = $this->aValidation;

            if ($aSchema['items'] instanceof Param) {
                $aSchema['items'] = $aSchema['items']->getSchema();
            }

            if ($this->isDeprecated()) {
                $aSchema['deprecated'] = true;
            }

            if ($this->isNullable()) {
                $aSchema['nullable'] = true;
            }

            $aSchema['type'] = $this->getType();

            if ($this->sDescription) {
                $aSchema['description'] = $this->sDescription;
            }

            return new Schema($aSchema);
        }

        /**
         * Heavily inspired by justinrainbow/json-schema, except tries not to coerce nulls into non-nulls
         * @param $mValue
         * @return array
         */
        public function coerce($mValue) {
            if ($this->isNullable()) {
                if ($mValue === null || $mValue === 'null' || $mValue === 0 || $mValue === false || $mValue === '') {
                    return null;
                }
            }

            if (is_scalar($mValue) && strpos($mValue, ',') !== false) {
                $mValue = explode(',', $mValue);
                $mValue = array_map('trim', $mValue);
            }

            if (is_scalar($mValue) || $mValue === null) {
                $mValue = [$mValue];
            }

            if (is_array($mValue) && isset($this->aValidation['items'])) {
                $oItems = $this->aValidation['items'];
                if ($oItems instanceof Param) {
                    foreach ($mValue as &$mItem) {
                        $mItem = $oItems->coerce($mItem);
                    }
                }
            }

            return $mValue;
        }

        /**
         * @param array $aSchema
         * @return Param\_Array
         */
        public static function createFromJsonSchema(array $aSchema) {
            $oParam = self::create();

            if (isset($aSchema['minItems'])) {
                $oParam = $oParam->minItems($aSchema['minItems']);
            }

            if (isset($aSchema['maxItems'])) {
                $oParam = $oParam->maxItems($aSchema['maxItems']);
            }

            if (isset($aSchema['uniqueItems'])) {
                $oParam = $oParam->uniqueItems($aSchema['uniqueItems']);
            }

            if (isset($aSchema['items']) && is_array($aSchema['items'])) {
                $oParam = $oParam->items(Param::createFromJsonSchema($aSchema['items']));
            }

            return $oParam;
        }
    }