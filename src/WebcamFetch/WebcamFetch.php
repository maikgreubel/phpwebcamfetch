<?php
/**
 * This file is part of WebcamFetch package.
 *
 * @package Nkey/WebcamFetch
 */
namespace Nkey\WebcamFetch;

use \DateTime;
use \InvalidArgumentException;
use Generics\FileNotFoundException;
use Generics\Streams\StreamException;
use Generics\Socket\ClientSocket;
use Generics\Util\UrlParser;
use Generics\Socket\InvalidUrlException;
use Generics\Streams\FileOutputStream;
use Generics\Client\HttpClient;
use Generics\Socket\Url;
use Generics\Client\HttpStatus;

/**
 * WebcamFetch implementation
 *
 * @author Maik Greubel <greubel@nkey.de>
 */
class WebcamFetch
{

    /**
     * The url where the data exists to retrieve
     *
     * @var \Generics\Socket\Url
     */
    private $url;

    /**
     * The percentage to shrink the image down to
     *
     * @var number
     */
    private $shrinkTo;

    /**
     * The locale file name
     *
     * @var Optional, if not given, the file name from
     *      remote will be taken
     */
    private $imageFileName;

    /**
     * Flag which indicates to retrieve the remote data
     *
     * @var boolean
     */
    private $needToFetch;

    /**
     * Flag which indicates to shrink the local image
     *
     * @var boolean
     */
    private $needToShrink;

    /**
     * Number of seconds, the local image can stay unmodified
     *
     * @var number
     */
    private $localMaxAge;

    /**
     * Data holder for remote expired check
     *
     * @var \DateTime
     */
    private $remoteExpired;

    /**
     * Path where to store the archive images
     *
     * @var string
     */
    private $archivePath;

    /**
     * Http client instance
     *
     * @var \Generics\Client\HttpClient
     */
    private $client;

    /**
     * Create a new Fetch instance
     *
     * @param string|\Generics\Socket\Url $url
     *            The url where the original webcam image lives
     *
     * @param number|array $shrinkTo
     *            Either percentage as number or fixed dimensions as array('w' => number,'h' => number)
     *
     * @param string $imageFileName
     *            The name of the image for local storage
     *
     * @param number $maxAge
     *            The number of seconds when the local image expires
     *
     * @param string $archivePath
     *            The path where to store archive images
     *
     * @throws \Nkey\WebcamFetch\FetchException
     */
    public function __construct($url, $shrinkTo = 0, $imageFileName = null, $maxAge = 0, $archivePath = null)
    {
        if (! function_exists('imagecreatefromjpeg')) {
            throw new FetchException("GD2 extension is not enabled. Please configure your php.ini properly!");
        }

        clearstatcache();

        $this->needToFetch = true;
        $this->needToShrink = true;

        if ($url instanceof Url) {
            $this->url = $url;
        } else {
            $this->url = UrlParser::parseUrl($url);
        }
        $this->shrinkTo = $shrinkTo;
        $this->imageFileName = $imageFileName;
        $this->localMaxAge = $maxAge;
        $this->archivePath = $archivePath;

        if ($this->imageFileName == null) {
            $this->imageFileName = $this->url->getFile();
        }

        if (file_exists($this->imageFileName)) {
            $this->needToShrink = false;
        }

        $this->client = new HttpClient($this->url);
    }

    /**
     * Checks whether it is expired
     *
     * @param \DateTime $current
     * @param \DateTime $localDate
     * @return boolean
     */
    private function isExpired(DateTime $current, DateTime $localDate)
    {
        $expires = clone $localDate;
        $expires->setTimestamp($expires->getTimestamp() + $this->localMaxAge);

        if ($expires->getTimestamp() < $current->getTimestamp()) {
            return $this->needToFetch = true;
        }
        return $this->needToFetch = false;
    }

    /**
     * Check the header retrieved by HttpClient
     *
     * @param \DateTime $localDate
     * @throws \Nkey\WebcamFetch\CheckRemoteException
     * @return boolean
     */
    private function checkHeaders(DateTime $localDate)
    {
        $response = null;

        try {
            $response = $this->client->retrieveHeaders();
        } catch (\Generics\Socket\SocketException $ex) {
            throw new CheckRemoteException(
                "Could not retrieve headers: {exception}",
                array('exception' => $ex->getMessage()),
                $ex->getCode(),
                $ex
            );
        }

        if ($this->client->getResponseCode() != 200) {
            throw new CheckRemoteException('Server returned invalid reponse "{response}"!', array(
                'response' => HttpStatus::getStatus($this->client->getResponseCode())
            ));
        }

        if (isset($response['Last-Modified'])) {
            $this->remoteExpired = new DateTime($response['Last-Modified']);

            if ($localDate->getTimestamp() < $this->remoteExpired->getTimestamp()) {
                return $this->needToFetch = true;
            }
        } elseif (isset($response['Expires'])) {
            $this->remoteExpired = new DateTime($response['Expires']);

            if ($localDate->getTimestamp() > $this->remoteExpired->getTimestamp()) {
                return $this->needToFetch = true;
            }
        }

        return $this->needToFetch = false;
    }

    /**
     * Check whether the remote file is newer than the local one
     *
     * @throws \Nkey\WebcamFetch\CheckRemoteException
     * @return boolean
     */
    public function checkIsNew()
    {
        if (! file_exists($this->imageFileName)) {
            return $this->needToFetch = true;
        }

        $current = new DateTime();
        $localDate = clone $current;
        $localDate->setTimestamp(filemtime($this->imageFileName));

        if ($this->localMaxAge > 0) {
            return $this->isExpired($current, $localDate);
        } else {
            return $this->checkHeaders($localDate);
        }
    }

    /**
     * Performs the file archivation
     *
     * @throws \Nkey\WebcamFetch\WriteLocalFileException
     */
    private function archive()
    {
        if ($this->archivePath != null) {
            if (is_dir($this->archivePath)) {
                $mtime = filemtime($this->imageFileName);
                $pinfo = pathinfo($this->imageFileName);

                $mDateTime = new DateTime();
                $mDateTime->setTimestamp($mtime);
                $newName = sprintf(
                    "%s/%s-%s.%s", //
                    $this->archivePath, //
                    $pinfo['filename'], //
                    $mDateTime->format("YmdHis"), //
                    $pinfo['extension'] //
                );

                if (! copy($this->imageFileName, $newName)) {
                    throw new WriteLocalFileException("Could not archive local file, copying failed!");
                }
            } else {
                throw new WriteLocalFileException("Could not archive local file, archive path is not a directory!");
            }
        }
    }

    /**
     * If the shrink level is not in bounds between 0 and 99,
     * validation has failed.
     *
     * @throws \InvalidArgumentException
     */
    private function validatePercentage()
    {
        if ($this->shrinkTo < 0 || $this->shrinkTo >= 100) {
            throw new \InvalidArgumentException("Invalid shrink size (0 < expected < 100)");
        }
    }

    /**
     * If the shrink level is a dimension, pixel width and height must be
     * 1 and 6000 for width, and 1 and 5000 for height. Otherwise
     * validation has failed.
     *
     * @throws \InvalidArgumentException
     */
    private function validateWidthAndHeight()
    {
        $width = isset($this->shrinkTo['w']) ? intval($this->shrinkTo['w']) : 0;
        $height = isset($this->shrinkTo['h']) ? intval($this->shrinkTo['h']) : 0;

        if ($width < 1 || $width > 6000) {
            throw new \InvalidArgumentException("The width value for shrinking is invalid!");
        }
        if ($height < 1 || $height > 5000) {
            throw new \InvalidArgumentException("The height value for shrinking is invalid!");
        }
    }

    /**
     * Performs some sanity checks whether shrinking is possible
     *
     * @throws \Nkey\WebcamFetch\FetchException
     * @throws \InvalidArgumentException
     * @throws \Generics\FileNotFoundException
     */
    private function validateShrink()
    {
        if ($this->needToFetch) {
            throw new FetchException("Please retrieve the remote file first!");
        }

        if (is_int($this->shrinkTo)) {
            $this->validatePercentage();
        } elseif (is_array($this->shrinkTo)) {
            $this->validateWidthAndHeight();
        }

        if (! file_exists($this->imageFileName)) {
            throw new FileNotFoundException("Shrinking failed, the local file does not exist!");
        }
    }

    /**
     * Retrieve current width and height
     *
     * @throws \Nkey\WebcamFetch\InvalidFileDataException
     * @return array
     */
    private function getCurrentWidthAndHeight()
    {
        $gdInfo = getimagesize($this->imageFileName);
        if (! $gdInfo) {
            throw new InvalidFileDataException("Could not read the dimensions of the local file!");
        }

        $width = intval($gdInfo[0]);
        $height = intval($gdInfo[1]);

        if ($width < 1 || $width > 6000 || $height < 1 || $height > 5000) {
            throw new InvalidFileDataException("The remote file has invalid dimensions (w = {w}, h = {h})", array(
                'w' => $width,
                'h' => $height
            ));
        }

        return array($width, $height);
    }

    /**
     * Calculate new dimensions based on image dimension data and the desired shrink level.
     *
     * @throws \Nkey\WebcamFetch\InvalidFileDataException
     *
     * @return array The current and new dimension
     */
    private function calculateDimensions()
    {
        list($width, $height) = $this->getCurrentWidthAndHeight();

        if (is_int($this->shrinkTo)) {
            $newWidth = intval($width / 100 * $this->shrinkTo);
            $newHeight = intval($height / 100 * $this->shrinkTo);
        } else {
            $newWidth = intval($this->shrinkTo['w']);
            $newHeight = intval($this->shrinkTo['h']);
        }

        if ($newWidth < 1 || $newWidth > 6000 || $newHeight < 1 || $newHeight > 5000) {
            throw new InvalidFileDataException("The local file has invalid dimensions (w = {w}, h = {h})", array(
                'w' => $width,
                'h' => $newHeight
            ));
        }

        return array($width, $height, $newWidth, $newHeight);
    }

    /**
     * Shrinks the image to the particular size given by percentage
     *
     * @throws \InvalidArgumentException
     * @throws \Generics\FileNotFoundException
     * @throws \Nkey\WebcamFetch\InvalidFileDataException
     * @throws \Nkey\WebcamFetch\WriteLocalFileException
     */
    public function shrink()
    {
        if (! $this->needToShrink || intval($this->shrinkTo) == 0) {
            return;
        }

        $this->validateShrink();

        list($width, $height, $newWidth, $newHeight) = $this->calculateDimensions();


        $imgJpg = imagecreatefromjpeg($this->imageFileName);
        if (! $imgJpg) {
            throw new InvalidFileDataException("Could not read the image data out of remote file!");
        }

        $imgNew = imagecreatetruecolor($newWidth, $newHeight);
        if (! $imgNew) {
            imagedestroy($imgJpg);
            throw new InvalidFileDataException("Could not allocate destination buffer for image file!");
        }

        if (! imagecopyresized($imgNew, $imgJpg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
            imagedestroy($imgJpg);
            imagedestroy($imgNew);
            throw new InvalidFileDataException("Could not copy data to resize!");
        }

        if (! imagejpeg($imgNew, $this->imageFileName)) {
            imagedestroy($imgJpg);
            imagedestroy($imgNew);
            throw new WriteLocalFileException("Could not write the shrinked data into file!");
        }

        imagedestroy($imgJpg);
        imagedestroy($imgNew);

        $this->needToShrink = false;
    }

    /**
     * Send image data to client
     *
     * @throws \Nkey\WebcamFetch\ReadLocalFileException
     * @throws \Generics\FileNotFoundException
     */
    public function sendToClient()
    {
        if (file_exists($this->imageFileName)) {
            $data = file_get_contents($this->imageFileName);
            if (! $data) {
                throw new ReadLocalFileException("Could not open local file for reading!");
            }

            $localDate = new DateTime('UTC');
            $localDate->setTimestamp(filemtime($this->imageFileName));

            header('Content-Type: image/jpeg');
            header('Content-Length: ' . strlen($data));
            header('Last-Modified: ' . $localDate->format('D, d M Y H:i:s \G\M\T'));
            header('Cache-Control: public');
            header('ETag: "' . md5($localDate->getTimestamp() . $this->imageFileName) . '"');

            echo $data;
        } else {
            throw new FileNotFoundException("Local file does not exist!");
        }
    }

    /**
     * Removes the local file (if it exists)
     */
    public function removeLocalFile()
    {
        if (file_exists($this->imageFileName)) {
            unlink($this->imageFileName);
        }
    }

    /**
     * Retrieve the remote file and store it locally
     *
     * @throws \Nkey\WebcamFetch\FetchException
     * @throws \Nkey\WebcamFetch\WriteLocalFileException
     * @throws \Generics\Socket\SocketException
     * @throws \Generics\Streams\StreamException
     * @throws \InvalidUrlException
     */
    public function retrieve()
    {
        if (! $this->needToFetch) {
            return;
        }

        if (file_exists($this->imageFileName)) {
            $this->archive();
        }

        $client = new HttpClient($this->url);
        $client->connect();
        $client->request('GET');
        $imageData = "";

        while ($client->getPayload()->ready()) {
            $imageData = $client->getPayload()->read(
                $client->getPayload()
                ->count()
            );
        }

        if ($client->isConnected()) {
            $client->disconnect();
        }

        if (substr($imageData, 0, 3) != "\xFF\xD8\xFF") {
            throw new InvalidFileDataException("The retrieved data is not a valid jpeg!");
        }

        $imageDataLen = strlen($imageData);

        $fos = new FileOutputStream($this->imageFileName);
        $fos->write($imageData);
        $fos->flush();
        if ($fos->count() != $imageDataLen) {
            throw new WriteLocalFileException("Could not write the data to local file!");
        }
        $fos->close();

        $this->needToFetch = false;
        $this->needToShrink = true;
    }

    /**
     * Retrieve the date time when the remote image has expired
     *
     * @return DateTime
     */
    public function getRemoteExpiredDate()
    {
        return $this->remoteExpired;
    }
}
