<?php
namespace Nkey\WebcamFetch\Test;

use Nkey\WebcamFetch\WebcamFetch;
use Generics\Streams\FileInputStream;

class WebcamFetchTest extends \PHPUnit_Framework_TestCase
{

    private $localFileName = "acapulco.jpeg";

    public function setUp()
    {
        if (file_exists($this->localFileName)) {
            unlink($this->localFileName);
        }
    }

    public function tearDown()
    {
        //unlink($this->localFileName);
    }

    public function testWebcamFetch()
    {
        $wcf = new WebcamFetch("http://webcamsdemexico.net/acapulco1/live.jpg", 80, $this->localFileName, 300);

        $new = $wcf->checkIsNew();

        $this->assertTrue($new);

        $wcf->retrieve();

        $wcf->shrink();

        $fis = new FileInputStream($this->localFileName);

        $this->assertNotEquals(0, $fis->count());
    }
}
