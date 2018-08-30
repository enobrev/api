<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;

    class _Array extends Param {
        public function __construct(string $sName, $iOptions, ?array $aValidation = null, ?string $sDescription = null) {
            parent::__construct($sName, $iOptions | Param::ARRAY, $aValidation, $sDescription);
        }
    }