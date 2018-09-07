<?php
    namespace Enobrev\API\FullSpec\Component;

    use Adbar\Dot;
    use Enobrev\API\FullSpec;
    use Enobrev\API\FullSpec\ComponentInterface;
    use Enobrev\API\OpenApiInterface;

    class Response implements ComponentInterface, OpenApiInterface {
        const PREFIX = 'responses';

        /** @var string */
        private $sName;

        /** @var string */
        private $sSummary;

        /** @var string */
        private $sDescription;

        /** @var OpenApiInterface[] */
        private $aSchemas;

        public static function create(string $sName) {
            return new self($sName);
        }

        public function __construct($sName) {
            $aName = explode('/', $sName);
            if (count($aName) === 1) {
                array_unshift($aName, self::PREFIX);
            } else if ($aName[0] !== self::PREFIX) {
                array_unshift($aName, self::PREFIX);
            };

            $this->sName = implode('/', $aName);
        }

        public function getName(): string {
            return $this->sName;
        }

        public function getDescription(): string {
            return $this->sDescription;
        }

        public function description(string $sDescription):self {
            $this->sDescription = $sDescription;
            return $this;
        }

        public function summary(string $sSummary):self {
            $this->sSummary = $sSummary;
            return $this;
        }

        public function json(OpenApiInterface $mJson):self {
            $this->aSchemas[] = $mJson;
            return $this;
        }

        public function getOpenAPI(): array {
            if (!count($this->aSchemas)) {
                // If No schema is given, then simply apply the name and description to the default
                return self::create($this->sName)->description($this->sDescription)->json(Reference::create(FullSpec::SCHEMA_DEFAULT))->getOpenAPI();
            }

            $oResponse = new Dot([
                'description' => $this->sDescription,
                'content'     => []
            ]);

            if ($this->sSummary) {
                $oResponse->set('x-summary', $this->sSummary);
            }

            foreach($this->aSchemas as $mSubSchema) {
                $oResponse->set("content.application/json.schema", $mSubSchema->getOpenAPI());
            }

            return $oResponse->all();
        }
    }