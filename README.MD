## A PHP application working with barcode scaner QuickScan DataLogic QD2330

### installation

The folder /vendor with used libraries is not included in project. If it is there - then it is a development build, which is ready to deploy, so skip this. Run `php composer.phar install` so that dependencies ( Slim php (with monolog with Twig) with barcode library ) are actually available to use in your application, they are listed in composer.json .

Create also /data/ and /data/barcodes folders in the root of your installation

### running

It works OK with built-in PHP server. Just run `PHP -S localhost:80` to run...