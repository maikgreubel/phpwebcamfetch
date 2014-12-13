<?php
namespace Nkey\WebcamFetch\Test;

use Nkey\WebcamFetch\WebcamFetch;
use Generics\Streams\FileInputStream;
use Generics\Util\UrlParser;
use Generics\Streams\FileOutputStream;

class WebcamFetchTest extends \PHPUnit_Framework_TestCase
{

    private $localFileName;

    public function setUp()
    {
        $this->localFileName = __DIR__ . "acapulco.jpeg";

        if (file_exists($this->localFileName)) {
            unlink($this->localFileName);
        }

        if (file_exists("nonexisting.jpeg")) {
            unlink("nonexisting.jpeg");
        }
    }

    public function tearDown()
    {
        if (file_exists($this->localFileName)) {
            unlink($this->localFileName);
        }

        if (file_exists("nonexisting.jpeg")) {
            unlink("nonexisting.jpeg");
        }
    }

    public function testWebcamFetch()
    {
        $wcf = new WebcamFetch("http://webcamsdemexico.net/acapulco1/live.jpg", 80, $this->localFileName, 300);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        $fis = new FileInputStream($this->localFileName);

        $this->assertNotEquals(0, $fis->count());

        $fis->close();
    }

    public function testWithUrl()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        $wcf = new WebcamFetch($url, 80, $this->localFileName, 300);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        $fis = new FileInputStream($this->localFileName);

        $this->assertNotEquals(0, $fis->count());

        $fis->close();

        $wcf->removeLocalFile();
    }

    public function testWithoutLocalFilename()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, 80, null, 300);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        $fis = new FileInputStream($url->getFile());

        $this->assertNotEquals(0, $fis->count());

        $fis->close();

        unlink($url->getFile());
    }

    public function testExpired()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, 80, null, 1);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        $this->assertFalse($wcf->checkIsNew());

        sleep(2); // one second is not enough for testing...

        $this->assertTrue($wcf->checkIsNew());

        unlink($url->getFile());
    }

    public function testExpiredByHeaders()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, 80);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        sleep(5);

        $current = new \DateTime();
        $new = $wcf->checkIsNew();

        $remoteDate = $wcf->getRemoteExpiredDate();

        unlink($url->getFile());
    }

    /**
     * @expectedException \Generics\Socket\SocketException
     */
    public function testInvalidServer()
    {
        $url = UrlParser::parseUrl("http://localhost:8421/nonexisting.jpeg");

        $wcf = new WebcamFetch($url, 80);

        $wcf->checkIsNew();

        $wcf->retrieve();
    }

    /**
     * @expectedException \Nkey\WebcamFetch\CheckRemoteException
     */
    public function testInvalidServerHeaderCheck()
    {
        $fos = new FileOutputStream("nonexisting.jpeg");
        $fos->close();

        $url = UrlParser::parseUrl("http://localhost:8421/nonexisting.jpeg");

        $wcf = new WebcamFetch($url, 80);

        $wcf->checkIsNew();
    }

    /**
     * @expectedException \Nkey\WebcamFetch\CheckRemoteException
     */
    public function testNonExistingImageHeaderCheck()
    {
        $fos = new FileOutputStream("nonexisting.jpeg");
        $fos->close();

        $url = UrlParser::parseUrl("http://httpbin.org/nonexisting.jpeg");

        $wcf = new WebcamFetch($url, 80);

        $wcf->checkIsNew();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidWidth()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, array('w' => 8000, 'h' => 6000), null, 1);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidHeight()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, array('w' => 6000, 'h' => 6000), null, 1);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();
    }

    public function testWidthAndHeight()
    {
        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, array('w' => 200, 'h' => 150), null, 1);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();
    }

    public function testArchive()
    {
        $archivePath = __DIR__ . "/testfolder";

        if (!file_exists($archivePath)) {
            mkdir($archivePath, "0777");
        }

        $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");

        if (file_exists($url->getFile())) {
            unlink($url->getFile());
        }

        $wcf = new WebcamFetch($url, array('w' => 200, 'h' => 150), $this->localFileName, 1, $archivePath);

        $this->assertTrue($wcf->checkIsNew());

        $wcf->retrieve();

        $wcf->shrink();

        sleep(2);

        $this->assertTrue($wcf->checkIsNew());

        $archived = null;
        $wcf->retrieve($archived);

        $this->assertTrue(file_exists($archived));
    }
}
