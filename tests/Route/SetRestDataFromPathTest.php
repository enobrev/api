<?php
    namespace Enobrev\Test\Route;

    require __DIR__ . '/../../vendor/autoload.php';

    use PHPUnit_Framework_TestCase as TestCase;

    use Enobrev\API\Exception;
    use Enobrev\API\Response;
    use Enobrev\API\Request;
    use Enobrev\API\Route;
    use function Enobrev\dbg;

    use Enobrev\ORM\Db;

    use Zend\Diactoros\ServerRequest;
    use Zend\Diactoros\Uri;

    use Enobrev\API\Mock\Table;


    class SetRestDataFromPathTest extends TestCase {

        const DOMAIN = 'example.com';

        /** @var PDO */
        private $oPDO;

        /** @var  Table\User */
        private $oUser1;

        /** @var  Table\User */
        private $oUser2;

        /** @var  Table\Address */
        private $oAddress1;

        /** @var  Table\Address */
        private $oAddress2;

        /** @var  Table\Address */
        private $oAddress3;

        public function setUp() {
            $sDatabase = file_get_contents(__DIR__ . '/../Mock/sqlite.sql');

            $this->oPDO = Db::defaultSQLiteMemory();
            $this->oPDO->exec("DROP TABLE IF EXISTS users");
            $this->oPDO->exec("DROP TABLE IF EXISTS addresses");
            $this->oPDO->exec($sDatabase);
            Db::getInstance($this->oPDO);

            $this->oUser1 = new Table\User;
            $this->oUser1->user_name->setValue('Test');
            $this->oUser1->user_email->setValue('test@example.com');
            $this->oUser1->user_happy->setValue(false);
            $this->oUser1->insert();

            $this->oUser2 = new Table\User;
            $this->oUser2->user_name->setValue('Test2');
            $this->oUser2->user_email->setValue('test2@example.com');
            $this->oUser2->user_happy->setValue(true);
            $this->oUser2->insert();

            $this->oAddress1 = new Table\Address;
            $this->oAddress1->user_id->setValue($this->oUser1);
            $this->oAddress1->address_line_1->setValue('123 Main Street');
            $this->oAddress1->address_city->setValue('Chicago');
            $this->oAddress1->insert();
            
            $this->oAddress2 = new Table\Address;
            $this->oAddress2->user_id->setValue($this->oUser1);
            $this->oAddress2->address_line_1->setValue('234 Main Street');
            $this->oAddress2->address_city->setValue('Brooklyn');
            $this->oAddress2->insert();

            $this->oAddress3 = new Table\Address;
            $this->oAddress3->user_id->setValue($this->oUser2);
            $this->oAddress3->address_line_1->setValue('345 Main Street');
            $this->oAddress3->address_city->setValue('Austin');
            $this->oAddress3->insert();
        }

        public function tearDown() {
            Db::getInstance()->query("DROP TABLE IF EXISTS users");
            Db::getInstance()->query("DROP TABLE IF EXISTS addresses");
        }

        public function testExistingTableUser() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/' . $this->oUser1->user_id->getValue()));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $this->assertInstanceOf(Table\User::class, $oRest->getData());
            $this->assertEquals($this->oUser1, $oRest->getData());
        }

        public function testExistingTableAddress() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/' . $this->oAddress1->address_id->getValue()));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $this->assertInstanceOf(Table\Address::class, $oRest->getData());
            $this->assertEquals($this->oAddress1, $oRest->getData());
        }

        public function testExistingTableUsers() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/users/'));

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $oData = $oRest->getData();
            $this->assertInstanceOf(Table\Users::class, $oData);
            $this->assertCount(2, $oData);
            $this->assertEquals($this->oUser1, $oData[0]);
            $this->assertEquals($this->oUser2, $oData[1]);
        }

        public function testExistingTableAddresses() {
            Route::init('\\Enobrev\\API\\Mock\\', '\\Enobrev\\API\\Mock\\Table\\', ['v1']);
            Response::init(self::DOMAIN);

            /** @var ServerRequest $oServerRequest */
            $oServerRequest = new ServerRequest;
            $oServerRequest = $oServerRequest->withMethod('GET');
            $oServerRequest = $oServerRequest->withUri(new Uri('http://' . self::DOMAIN . '/v1/addresses/'));
            $oServerRequest = $oServerRequest->withQueryParams(['search' => 'city:Brooklyn']);

            $oRequest = new Request($oServerRequest);
            $oRest    = Route::_getRestClass($oRequest);

            Route::_setRestDataFromPath($oRest, $oRequest);

            $oData = $oRest->getData();
            $this->assertInstanceOf(Table\Address::class, $oData);
            $this->assertEquals($this->oAddress2, $oData);
        }
    }