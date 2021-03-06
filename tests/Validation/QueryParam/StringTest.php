<?php /** @noinspection PhpUnhandledExceptionInspection */

    namespace Enobrev\Test\Validation\QueryParam;

    require __DIR__ . '/../../../vendor/autoload.php';

    use Laminas\Diactoros\ServerRequest;
    use Middlewares\Utils\Dispatcher;
    use PHPUnit\Framework\TestCase;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;

    use Enobrev\API\Exception\ValidationException;
    use Enobrev\API\FullSpec;
    use Enobrev\API\Method;
    use Enobrev\API\Middleware\FastRoute;
    use Enobrev\API\Middleware\Request\AttributeSpec;
    use Enobrev\API\Middleware\Request\CoerceParams;
    use Enobrev\API\Middleware\Request\ValidateSpec;
    use Enobrev\API\Middleware\RequestHandler;
    use Enobrev\API\Middleware\Response\MetadataRequest;
    use Enobrev\API\Middleware\ResponseBuilderDone;
    use Enobrev\API\Middleware\ResponseBuilder;
    use Enobrev\API\Param;
    use Enobrev\API\Spec;
    use Enobrev\API\SpecInterface;

    class StringTest extends TestCase {
        /** @var array  */
        private $aPipeline = [];

        public function setUp(): void {
            parent::setUp();

            $oFullSpec = FullSpec::getInstance();
            $oFullSpec->addSpecFromInstance(new StringTestClass());

            $this->aPipeline = [
                new ResponseBuilder(),
                new MetadataRequest(),
                new FastRoute(),
                new AttributeSpec(),
                new CoerceParams(),
                new ValidateSpec(),
                new RequestHandler(),
                new ResponseBuilderDone()
            ];
        }

        private function getResponse(array $aQueryParams): ResponseInterface {
            return Dispatcher::run($this->aPipeline, new ServerRequest(
                [],
                [],
                '/testing/query_params',
                Method\GET,
                'php://input',
                [],
                [],
                $aQueryParams
            ));
        }

        public function testOk(): void {
            $oResponse = $this->getResponse(['test' => 'abc']);
            $sResponse = $oResponse->getBody()->getContents();
            $aResponse = json_decode($sResponse, true);

            $this->assertIsArray($aResponse);
            $this->assertIsArray($aResponse['_request']);
            $this->assertIsArray($aResponse['_request']['params']);
            $this->assertIsArray($aResponse['_request']['params']['query']);
            $this->assertEquals("abc", $aResponse['_request']['params']['query']['test']);
            $this->assertEquals(1, $aResponse['TEST_OK']);
        }

        public function testMinLength(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => 'ab']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('minLength', $aContext[0]['constraint']);
                $this->assertStringContainsString('Must be at least', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testMaxLength(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => 'abcdefghijklmnopqrstuvwxyz']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('maxLength', $aContext[0]['constraint']);
                $this->assertStringContainsString('Must be at most', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testPattern(): void {
            $this->expectException(ValidationException::class);

            try {
                $this->getResponse(['test' => 'abc9']);
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('test', $aContext[0]['property']);
                $this->assertEquals('pattern', $aContext[0]['constraint']);
                $this->assertStringContainsString('Does not match the regex pattern', $aContext[0]['message']);

                throw $e;
            }
        }

        public function testEmail(): void {
            $oFullSpec = FullSpec::getInstance();
            $oFullSpec->addSpecFromInstance(new StringEmailTestClass());

            $this->aPipeline = [
                new ResponseBuilder(),
                new MetadataRequest(),
                new FastRoute(),
                new AttributeSpec(),
                new CoerceParams(),
                new ValidateSpec(),
                new RequestHandler(),
                new ResponseBuilderDone()
            ];

            $this->expectException(ValidationException::class);

            try {
                Dispatcher::run($this->aPipeline, new ServerRequest(
                    [],
                    [],
                    '/testing/query_params',
                    Method\GET,
                    'php://input',
                    [],
                    [],
                    ['email' => 'abc9']
                ));
            } catch (ValidationException $e) {
                $aContext = $e->getContext();

                $this->assertIsArray($aContext);
                $this->assertCount(1, $aContext);
                $this->assertEquals('email', $aContext[0]['property']);
                $this->assertEquals('format', $aContext[0]['constraint']);
                $this->assertStringContainsString('Invalid email', $aContext[0]['message']);

                throw $e;
            }
        }
    }

    class StringTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\GET)
                       ->path            ('/testing/query_params')
                       ->queryParams     ([
                                              'test' => Param\_String::create()->required()
                                                                     ->minLength(3)
                                                                     ->maxLength(20)
                                                                     ->pattern('^[a-zA-Z0-8]+$')
                                          ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };

    class StringEmailTestClass implements SpecInterface, MiddlewareInterface {
        public function spec(): Spec {
            return Spec::create()
                       ->httpMethod      (Method\GET)
                       ->path            ('/testing/query_params')
                       ->queryParams     ([
                                              'email' => Param\_String::create()->required()
                                                                      ->minLength(3)
                                                                      ->maxLength(20)
                                                                      ->format('email')
                                          ]);
        }

        public function process(ServerRequestInterface $oRequest, RequestHandlerInterface $oHandler): ResponseInterface {
            $oResponse = ResponseBuilder::get($oRequest);
            $oResponse->set('TEST_OK', 1);

            return $oHandler->handle(ResponseBuilder::update($oRequest, $oResponse));
        }
    };