tl;dr
==
This package provides a simple access to check, retrieve, store locally, shrink and archive jpeg images from a remote webcam. The Webcam must be accessible using HTTP protocol.


Details
==
It is the first application which makes use of my phpGenerics component framework, with intention to test the components. You may want to use Ant to perform tests and create documentation.

It uses composer to resolve dependencies and Ant for test and documentation creation lifecycle. Please install Ant and composer (don't forget to add a composer.bat or unix shell script for easy access, which calls "php composer.phar"), and type

	ant
  
Using
==

You can use it in a cronjob, shell script or inside your own webpage. See the unit tests and documentation to get an idea how to use it. Here is a small concept code for demonstration purposes:

    <?php
    require 'vendor/autoload.php';
    
    $url = UrlParser::parseUrl("http://webcamsdemexico.net/acapulco1/live.jpg");
    
    $wcf = new WebcamFetch($url, 80, $this->localFileName, 300);
    
    $wcf->retrieve();
    
    $wcf->shrink();
    
    // Send it to web browser (in case of web page integration)
    $wcf->sendToClient();



License
==

The package is published under the terms of the BSD-2-Clause. You may find a copy of it in LICENSE.md attached to this package.


Source and API documentation
==

The full source is published at github available at http://www.github.com/maikgreubel/phpwebcamfetch

The API documention can be build using the phpdocumentor (which is added as dev-dependency) by installing it first

	composer install-dev
	
and generate the documentation in "output" folder by typing

	vendor/bin/phpdoc
	
or on MS Windows

	vendor\bin\phpdoc.bat

As mentioned before, you can also use Ant to generate the documentation. After successful exection of ant command you will find a new folder "API_Documentation".


Issues
==

If you need further assistance or find a bug, please use the issue tracker on github project page.


Continuous Integration status is published at my jenkins site available at http://www.nkey.de:8080/job/phpWebcamFetch/
