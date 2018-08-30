<?php
    namespace Enobrev\API\Param;
    
    use Enobrev\API\Param;

    class _Boolean extends Param {
        public function __construct(string $sName, $iOptions, ?array $aValidation = null, ?string $sDescription = null) {
            parent::__construct($sName, $iOptions | Param::BOOLEAN, $aValidation, $sDescription);
        }
    }