<?php
// https://www.slimframework.com/docs/v3/tutorial/first-app.html

require './vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once './config_file.php';
require_once './DatabaseHandler.php';

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();
// Register Twig View helper
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('./webtemplates' ,[
        'cache' => './templatecache'
    ]);
    
    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new \Slim\Views\TwigExtension($c['router'], $basePath));
    return $view;
};

$container['db'] = function ($c) {
        $db = $c['settings']['db'];
        $pdo = new \PDO("sqlite:" . $db['sqlite']['pathtofile']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
};

//With the logger in place, I can use it from inside my route code with a line like this:
//    $this->logger->addInfo('Something interesting happened');
$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler($c['settings']['logfilepath']);
    $formatter = new \Monolog\Formatter\LineFormatter(null, null, false, true);
    $file_handler->setFormatter($formatter);
    $logger->pushHandler($file_handler);
    return $logger;
};

$app->get('/',function(Request $request, Response $response, array $args){
    return $this->view->render($response, "scaninvitation.twig");
});
$app->post('/recvbarcode[/]', function(Request $request, Response $response, array $args){
    $localtime = new DateTime("now", new DateTimeZone('Europe/Kiev'));
    
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    $dbInstance->saveScanTime($body->{'scannedbarcode'}, $localtime->format('Y-m-d H:i:s'));
    $data = array(['status' => 'OK', 'time'=>($localtime->format('Y-m-d H:i:s'))]);
    $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
$app->get('/list[/]', function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $rawscanTimeValues = $dbInstance->listAllScanTime();
    return $this->view->render($response, "listbarcode.twig",["scanlist"=>$rawscanTimeValues]);
});
$app->get('/barcodes-list[/]', function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $barcodeslist = $dbInstance->listAllBarcodes();
    return $this->view->render($response, "registeredbarcodes.twig",["registeredinfo"=>$barcodeslist]);
});
$app->post('/newbarcode[/]', function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
     $localtime = new DateTime("now", new DateTimeZone('Europe/Kiev'));
     $subpathToBarcode = "/data/barcodes/".$body->{'newbarcode'}.$localtime->format('Ymd_His').".png";
     $pathToBarcode = __DIR__.$subpathToBarcode;
     $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
     $generatedBytes = $generator->getBarcode($body->{'newbarcode'}, $generator::TYPE_CODE_128);
     file_put_contents($pathToBarcode, $generatedBytes);
    $dbInstance->saveCodeEntry($body->{'newbarcode'}, $subpathToBarcode);
    $data = array(['status' => 'OK', 'addedfilepath'=>$request->getUri()->getBasePath().$subpathToBarcode]);
    $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
$app->run();

?>