tl;dr
==
This package provides a simple access to check, retrieve, store locally, shrink and archive jpeg images from a remote webcam. The Webcam must be accessible using HTTP protocol.


Details
==
This package is the first application which makes use of my phpGenerics component framework, with intention to test the components.

It uses composer to resolve dependencies. Please install (don't forget to add a composer.bat or unix shell script for easy access, which calls "php composer.phar"), and type

	composer install

Then you are able to start the unit test by typing

	vendor/bin/phpunit
  
or

	vendor\bin\phpunit.bat
  
on MS Windows respectively.

You can use it in a cronjob, shell script or inside your own webpage.


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


Issues
==

If you need further assistance or find a bug, please use the issue tracker on github project page.