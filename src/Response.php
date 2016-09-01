<?php
    namespace Enobrev\API;

    use DateTime;
    use DateTimeZone;
    use stdClass;

    use Enobrev\API\HTTP;
    use Enobrev\API\Exception;
    use Enobrev\ORM\ModifiedDateColumn;
    use Enobrev\ORM\Field;
    use Enobrev\ORM\Table;
    use Enobrev\ORM\Tables;
    use Enobrev\Log;

    use function Enobrev\array_from_path;

    use Zend\Diactoros\Response as ZendResponse;

    class Response {
        const FORMAT_PNG       = 'png';
        const FORMAT_JPG       = 'jpg';
        const FORMAT_JPEG      = 'jpeg';
        const FORMAT_GIF       = 'gif';
        const FORMAT_TTF       = 'ttf';
        const FORMAT_WOFF      = 'woff';
        const FORMAT_CSS       = 'css';
        const FORMAT_JSON      = 'json';
        const FORMAT_EMPTY     = 'empty';

        const SYNC_DATE_FORMAT = 'Y-m-d H:i:s';
        const HTTP_DATE_FORMAT = 'D, d M Y H:i:s T';


        /** @var  string */
        protected $sFormat;

        /** @var  string */
        protected $sFile;

        /** @var  array */
        protected $aResponse;

        /** @var  string */
        protected $sTextOutput;

        /** @var  stdClass */
        protected $oOutput;

        /** @var  array */
        protected $aHeaders;

        /** @var  int */
        protected $iStatus;

        /** @var array */
        protected static $aAllowedURIs = ['*'];

        /** @var string */
        protected static $sDomain = null;

        /** @var string */
        protected static $sScheme = 'https://';

        /**
         * Response constructor.
         * @param Request $oRequest
         * @throws Exception\Response
         */
        public function __construct(Request $oRequest) {
            if (self::$sDomain === null) {
                throw new Exception\Response('API Response Not Initialized');
            }

            $this->aHeaders = [];
            $this->setFormat($oRequest->Format);
            $this->oOutput  = new stdClass();
            $this->oOutput->_request = new stdClass();
            $this->oOutput->_request->method     = $oRequest->OriginalRequest->getMethod();
            $this->oOutput->_request->path       = $oRequest->OriginalRequest->getUri()->getPath();
            $this->oOutput->_request->attributes = $oRequest->OriginalRequest->getAttributes();
            $this->oOutput->_request->query      = $oRequest->OriginalRequest->getQueryParams();
            $this->oOutput->_request->data       = $oRequest->POST;
            $this->oOutput->_server = self::getServerObject();
            $this->iStatus = HTTP\OK;
        }

        /**
         * @param string $sDomain
         * @param string $sScheme
         * @param array  $aAllowedURIs
         */
        public static function init(string $sDomain, string $sScheme, array $aAllowedURIs = ['*']) {
            self::$sScheme      = $sScheme;
            self::$sDomain      = $sDomain;
            self::$aAllowedURIs = $aAllowedURIs;
        }

        /**
         * @param string $sFormat
         */
        public function setFormat(string $sFormat) {
            $this->sFormat = $sFormat;
        }

        /**
         * @param string $sFile
         */
        public function setFile(string $sFile) {
            $this->sFile = $sFile;
        }

        /**
         * @param string $sText
         */
        public function setText(string $sText) {
            $this->sTextOutput = $sText;
        }

        /**
         * @param int $iContentLength
         */
        public function setContentLength($iContentLength) {
            $this->addHeader('Content-Length', $iContentLength);
        }

        /**
         * @param string $sContentType
         */
        public function setContentType($sContentType) {
            $this->addHeader('Content-Type', $sContentType);
        }

        /**
         * @param array $aAllow
         */
        public function setAllow(Array $aAllow) {
            $this->addHeader('Allow', implode(',', $aAllow));
        }

        /**
         * @param string $sETag
         */
        public function setEtag($sETag = null) {
            if ($sETag) {
                $this->addHeader('ETag', $sETag);
            }
        }

        /**
         * @param DateTime $oLastModified
         */
        public function setLastModified(DateTime $oLastModified = null) {
            if ($oLastModified instanceof DateTime) {
                $this->addHeader('Last-Modified', $oLastModified->format(self::HTTP_DATE_FORMAT));
            }
        }

        /**
         * @param ModifiedDateColumn[]|Tables $oTables
         */
        public function setLastModifiedFromTables($oTables) {
            $oLatest = new DateTime();
            $oLatest->modify('-10 years');
            foreach($oTables as $oTable) {
                if ($oTable instanceof ModifiedDateColumn) {
                    $oLatest = max($oLatest, $oTable->getLastModified());
                } else {
                    $this->setLastModified(new DateTime());
                    return;
                }
            }

            $this->setLastModified($oLatest);
        }

        /**
         * @param Table $oTable
         */
        public function setHeadersFromTable(Table $oTable) {
            $this->setEtag($oTable->toHash());

            if ($oTable instanceof ModifiedDateColumn) {
                $this->setLastModified($oTable->getLastModified());
            }
        }

        /**
         * @param string $sHeader
         * @param string $sValue
         */
        public function addHeader($sHeader, $sValue) {
            $this->aHeaders[$sHeader] = $sValue;
        }

        /**
         * @param mixed $sVar
         * @param mixed $mValue
         */
        public function add($sVar, $mValue = NULL) {
            if ($sVar instanceof Table) {
                $this->add($sVar->getTitle(), $sVar->toArray());
            } else if ($sVar instanceof Field\DateTime) {
                $this->set($sVar->sColumn, (string) $sVar);
            } else if ($sVar instanceof Field) {
                $this->set($sVar->sColumn, $sVar->getValue());
            } else if (is_array($sVar)) {
                foreach($sVar as $sKey => $sValue) {
                    if ($sValue instanceof Field) {
                        if (preg_match('/[a-zA-Z]/', $sKey)) { // Associative key - replacing field names
                            $this->set($sKey, $sValue);
                        } else {
                            $this->set($sValue->sColumn, $sValue);
                        }
                    } else {
                        $this->add($sKey, $sValue);
                    }
                }
            } else if (is_array($mValue)) {
                foreach($mValue as $sKey => $sValue) {
                    if ($sValue instanceof Field) {
                        if (preg_match('/[a-zA-Z]/', $sKey)) { // Associative key - replacing field names
                            $this->set($sVar . '.' . $sKey, $sValue);
                        } else {
                            $this->set($sVar . '.' . $sValue->sColumn, $sValue);
                        }
                    } else {
                        $this->add($sVar . '.' . $sKey, $sValue);
                    }
                }
            } else {
                $this->set($sVar, $mValue);
            }
        }

        /**
         * @return stdClass
         */
        private static function getServerObject() {
            $oNow    = new \DateTime;
            $oServer = new stdClass;
            $oServer->timezone      = $oNow->format('T');
            $oServer->timezone_gmt  = $oNow->format('P');
            $oServer->date          = $oNow->format(self::SYNC_DATE_FORMAT);
            return $oServer;
        }

        /**
         * Turns a dot-separated var into a multidimensional array and merges it with prior data with
         * the same hierarchy
         * @param string|array|Field $sVar
         * @param mixed $mValue
         * @return void
         */
        private function set($sVar, $mValue) {
            if ($mValue instanceof Field) {
                $mValue = $mValue->getValue();
            }

            if ($mValue instanceof DateTime) {
                /** @var DateTime $mValue */
                $mValue->setTimezone(new DateTimeZone('GMT'));
                $mValue = $mValue->format(DateTime::RFC3339);
            }

            $aKey  = explode('.', $sVar);
            $sKey  = $aKey[0];
            $aData = array_from_path($sVar, $mValue);

            if(is_array($aData)) {
                if (!property_exists($this->oOutput, $sKey)) {
                    $this->oOutput->$sKey = array();
                }

                $aCleanData = array();
                foreach($aData as $sDataKey => $sDataValue) {
                    if ($aData === NULL) {
                        continue;
                    }

                    $aCleanData[$sDataKey] = $sDataValue;
                }

                $this->oOutput->$sKey = array_replace_recursive($this->oOutput->$sKey, $aCleanData);
            } else if ($aData === NULL) {
                return;
            } else {
                $this->oOutput->$sKey = $aData;
            }
        }

        public function emptyResponse() {
            $this->setFormat(self::FORMAT_EMPTY);
            $this->respond();
        }

        /**
         * @param array ...$aMethods
         */
        public function respondWithOptions(...$aMethods) {
            $this->setAllow($aMethods);
            $this->statusNoContent();
            $this->respond();
        }

        /**
         * @return bool
         */
        private function setOrigin() {
            if (in_array('*', self::$aAllowedURIs)) {
                $this->addHeader('Access-Control-Allow-Origin',      '*');
                $this->addHeader('Access-Control-Allow-Headers',     'Authorization, Content-Type');
                $this->addHeader('Access-Control-Allow-Methods',     implode(', ', Method\_ALL));
                $this->addHeader('Access-Control-Allow-Credentials', 'true');
                return true;
            } else if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], self::$aAllowedURIs)) {
                $this->addHeader('Access-Control-Allow-Origin',      $_SERVER['HTTP_ORIGIN']);
                $this->addHeader('Access-Control-Allow-Headers',     'Authorization, Content-Type');
                $this->addHeader('Access-Control-Allow-Methods',     implode(', ', Method\_ALL));
                $this->addHeader('Access-Control-Allow-Credentials', 'true');
                return true;
            }

            return false;
        }

        /**
         * @throws Exception\NoContentType
         */
        public function respond() {
            $bAccessControlHeaders = $this->setOrigin();

            Log::d('API.RESPONSE', array(
                'ach'     => $bAccessControlHeaders,
                'status'  => $this->iStatus,
                'headers' => $this->aHeaders,
                'body'    => json_decode(json_encode($this->oOutput), true)
            ));

            $oEmitter = new ZendResponse\SapiEmitter();

            switch($this->sFormat) {
                default:
                case self::FORMAT_JSON:
                    $oResponse = new ZendResponse\JsonResponse($this->oOutput, $this->iStatus, $this->aHeaders);
                    $oEmitter->emit($oResponse);
                    break;

                case self::FORMAT_CSS:
                    $oResponse = new ZendResponse\TextResponse($this->sTextOutput, $this->iStatus, $this->aHeaders);
                    $oEmitter->emit($oResponse);
                    break;

                case self::FORMAT_TTF:
                case self::FORMAT_WOFF:
                case self::FORMAT_PNG:
                case self::FORMAT_GIF:
                case self::FORMAT_JPG:
                case self::FORMAT_JPEG:
                    if (!isset($this->aHeaders['Content-Type'])) {
                        throw new Exception\NoContentType('Missing Content Type');
                    }

                    $oResponse = new ZendFileResponse($this->sFile, $this->iStatus, $this->aHeaders);
                    $oEmitter->emit($oResponse);
                    break;

                case self::FORMAT_EMPTY:
                    $oEmitter->emit(new ZendResponse\EmptyResponse($this->iStatus, $this->aHeaders));
            }

            exit(0);
        }

        /**
         * @return stdClass
         */
        public function getOutput() {
            $oOutput = new stdClass();
            $oOutput->headers   = $this->aHeaders;
            $oOutput->status    = $this->iStatus;
            $oOutput->data      = $this->oOutput;
            return $oOutput;
        }

        /**
         * @param string $sName
         * @param string $sValue
         * @param int $iHours
         */
        public function addCookie($sName, $sValue, $iHours = 1) {
            setcookie($sName, $sValue, time() + (3600 * $iHours), '/', self::$sDomain, self::$sScheme== 'https://', false);
        }

        /**
         * @param string $sUri
         * @param int $iStatus
         */
        public function redirect($sUri, $iStatus = HTTP\FOUND) {
            (new ZendResponse\SapiEmitter())->emit(new ZendResponse\RedirectResponse($sUri, $iStatus, $this->aHeaders));
            exit(0);
        }

        /**
         * @return bool
         */
        public function isStatusFailing(): bool {
            return $this->iStatus >= HTTP\BAD_REQUEST;
        }

        public function setStatus($iStatus) {
            $this->iStatus = $iStatus;
        }

        public function statusNoContent() {
            $this->setStatus(HTTP\NO_CONTENT);
            $this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusBadRequest() {
            $this->setStatus(HTTP\BAD_REQUEST);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusUnauthorized() {
            $this->setStatus(HTTP\UNAUTHORIZED);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusForbidden() {
            $this->setStatus(HTTP\FORBIDDEN);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusNotFound() {
            $this->setStatus(HTTP\NOT_FOUND);
            //$this->setFormat(self::FORMAT_EMPTY);
        }

        public function statusMethodNotAllowed() {
            $this->setStatus(HTTP\METHOD_NOT_ALLOWED);
            //$this->setFormat(self::FORMAT_EMPTY);
        }
    }