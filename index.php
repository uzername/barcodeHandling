<?php
// https://www.slimframework.com/docs/v3/tutorial/first-app.html

require './vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once './config_file.php';
require_once './DatabaseHandler.php';
require_once './localeHandler.php';

//default date time format is d.m.Y

function validateDate($date, $format = 'd.m.Y') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

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
    //get current language from session and set of localized strings. just like in opencart
    session_start();
    $privateLocaleHandler = new localeHandler();
    $templateTransmission = [];
    $templateTransmission['wayback'] = "";
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
        
    }
    $mainpagesubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-main");
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $templateTransmission["localizedmessages"] = $mainpagesubarray+$commonsubarray;
    return $this->view->render($response, "scaninvitation.twig", $templateTransmission);
});

$app->get('/changelanguage[/]', function(Request $request, Response $response, array $args) {
    session_start();
    $paramValue = $request->getQueryParam('newlang');
    $paramValueWayBack = $request->getQueryParam('wayback');
    $localeHandlerInstance = new localeHandler();
    if ($paramValue!=null && $paramValue!="" && $localeHandlerInstance ->validateLocale($paramValue)) {
        $_SESSION["lang"] = $paramValue;
    }
    return $response->withRedirect($paramValueWayBack."/");
});

$app->post('/recvbarcode[/]', function(Request $request, Response $response, array $args){
    $localtime = new DateTime("now", new DateTimeZone('Europe/Kiev'));
    
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    if ($dbInstance->checkExistenceBarcodeByData( $body->{'scannedbarcode'} )==0) {
        $data = array(['status' => 'FAIL_NOTALREADYEXIST' ]);
        $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
    }
    $knownBarcodeIDFound = $dbInstance->obtainKnownBarcodeIDByText($body->{'scannedbarcode'});
    $dbInstance->saveScanTime($body->{'scannedbarcode'}, $knownBarcodeIDFound ,$localtime->format('Y-m-d H:i:s'));
    $data = array(['status' => 'OK', 'time'=>($localtime->format('Y-m-d H:i:s'))]);
    $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
///*********************
function sortArrayOfScannedItemsByBarcode($in_initialUnsortedStruct) {
    /// The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second
    function cmp($a, $b) {
        return strcmp( $a->{'RAWBARCODE'}, $b->{'RAWBARCODE'} );
    }
    $out_sortedStruct = $in_initialUnsortedStruct;
    usort($out_sortedStruct, "cmp");
    return $out_sortedStruct;
}
function prepareDataStructure($in_initialStruct) {
    $resultStructure = [];
    $step1PreparedArray = sortArrayOfScannedItemsByBarcode($in_initialStruct);
    $previousValue = null;
    foreach ($step1PreparedArray as $value) { //array is monotonous
        
    }
    return $resultStructure;
}
///*********************
$app->get('/list/v2[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist");
    $templateTransmission['wayback'] = "/list/v2";
        $dateStartString = null; $dateEndString = null; $sqlitedateStart = null; $sqlitedateEnd = null;
    if (isset($_GET) ) {    //// date and time fiddling
        $fromDateEnabled = ( isset($_GET["from"])&& validateDate(urldecode($_GET["from"]) ) );
        $toDateEnabled = ( isset($_GET["to"])&& validateDate(urldecode($_GET["to"])) );
        
        if ($fromDateEnabled && $toDateEnabled) {
            $dateStartString = urldecode($_GET["from"]); 
            $dateEndString = urldecode($_GET["to"]);
        }
        if ( ($fromDateEnabled === TRUE) && ($toDateEnabled === FALSE) ) { //2nd date is to be used as current date
            $dateStartString = urldecode($_GET["from"]); 
            $tmplocaldate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocaldate->format("d.m.Y");
        }
        if ( ($fromDateEnabled === FALSE) && ($toDateEnabled === TRUE) ) { //2nd date is to be used as current date
            $dateEndString = urldecode($_GET["to"]); 
            $tmplocaldate = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone('Europe/Kiev'));
            $tmpprevdate = $tmplocaldate->sub(new DateInterval("P1M"));
            $dateStartString = $tmpprevdate->format("d.m.Y");
        }
        if (($fromDateEnabled === FALSE) && ($toDateEnabled === FALSE)) {
            $tmplocalenddate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
        }
    } else {
            $tmplocalenddate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
    }
    $sqlitedateStart = date_create_from_format("d.m.Y", $dateStartString, new DateTimeZone('Europe/Kiev'))->format("Y-m-d");
    $sqlitedateEnd = date_time_set(date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone('Europe/Kiev')),23,59)->format("Y-m-d H:i");
    $rawscanTimeValues = $dbInstance->listScanTimesInRange($sqlitedateStart, $sqlitedateEnd);
    $rawscanTimeValues = prepareDataStructure($rawscanTimeValues);
    
    $templateTransmission["localizedmessages"] = $commonsubarray+$langsubarray;
    $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    $templateTransmission["scanlist"] = $rawscanTimeValues;
    $templateTransmission["datetime"]["from"] = $dateStartString;
    $templateTransmission["datetime"]["to"] = $dateEndString;
    
    $templateTransmission["datetime"]["fromstring"] = urlencode($dateStartString);
    $templateTransmission["datetime"]["tostring"] = urlencode($dateEndString);    
    return $this->view->render($response, "listbarcode2.twig",$templateTransmission);
});

$app->get('/list[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    //$rawscanTimeValues = $dbInstance->listAllScanTime();
    
    $templateTransmission = [];
    
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist");

        $templateTransmission['wayback'] = "/list";

    $dateStartString = null; $dateEndString = null; $sqlitedateStart = null; $sqlitedateEnd = null;
    if (isset($_GET) ) {    //// date and time fiddling
        $fromDateEnabled = ( isset($_GET["from"])&& validateDate(urldecode($_GET["from"]) ) );
        $toDateEnabled = ( isset($_GET["to"])&& validateDate(urldecode($_GET["to"])) );
        
        if ($fromDateEnabled && $toDateEnabled) {
            $dateStartString = urldecode($_GET["from"]); 
            $dateEndString = urldecode($_GET["to"]);
        }
        if ( ($fromDateEnabled === TRUE) && ($toDateEnabled === FALSE) ) { //2nd date is to be used as current date
            $dateStartString = urldecode($_GET["from"]); 
            $tmplocaldate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocaldate->format("d.m.Y");
        }
        if ( ($fromDateEnabled === FALSE) && ($toDateEnabled === TRUE) ) { //2nd date is to be used as current date
            $dateEndString = urldecode($_GET["to"]); 
            $tmplocaldate = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone('Europe/Kiev'));
            $tmpprevdate = $tmplocaldate->sub(new DateInterval("P1M"));
            $dateStartString = $tmpprevdate->format("d.m.Y");
        }
        if (($fromDateEnabled === FALSE) && ($toDateEnabled === FALSE)) {
            $tmplocalenddate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
        }
    } else {
            $tmplocalenddate = new DateTime("now", new DateTimeZone('Europe/Kiev'));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
    }
    $sqlitedateStart = date_create_from_format("d.m.Y", $dateStartString, new DateTimeZone('Europe/Kiev'))->format("Y-m-d");
    $sqlitedateEnd = date_time_set(date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone('Europe/Kiev')),23,59)->format("Y-m-d H:i");
    $rawscanTimeValues = $dbInstance->listScanTimesInRange($sqlitedateStart, $sqlitedateEnd);

    $templateTransmission["localizedmessages"] = $commonsubarray+$langsubarray;
    $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    $templateTransmission["scanlist"] = $rawscanTimeValues;
    $templateTransmission["datetime"]["from"] = $dateStartString;
    $templateTransmission["datetime"]["to"] = $dateEndString;
    
    $templateTransmission["datetime"]["fromstring"] = urlencode($dateStartString);
    $templateTransmission["datetime"]["tostring"] = urlencode($dateEndString);
        return $this->view->render($response, "listbarcode.twig",$templateTransmission);
});

$app->get('/registeredbarcodes[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    $templateTransmission['wayback'] = "/registeredbarcodes";
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $registeredCodesSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-registeredbarcodes");
    $barcodeslist = $dbInstance->listAllBarcodes();
    $templateTransmission["registeredinfo"] = $barcodeslist;
    $templateTransmission["localizedmessages"] = $commonsubarray+$registeredCodesSubarray;

    return $this->view->render($response, "registeredbarcodes.twig", $templateTransmission);
});

$app->post('/newbarcode[/]', function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    if ($dbInstance->checkExistenceBarcodeByData( $body->{'newbarcode'} )!=0) {
        $data = array(['status' => 'FAIL_ALREADYEXIST']);
        $newResponse = $response;
        $newResponse = $newResponse->withJson($data)->withStatus(200);
        return $newResponse;
    }
     $localtime = new DateTime("now", new DateTimeZone('Europe/Kiev'));
     //how should we refer to this image on site
     $subpathToBarcode = "/data/barcodes/".$body->{'newbarcode'}."_".$localtime->format('Ymd_His')."_".$body->{'barcodetype'}.".svg";
     //how we should refr to this image on disk
     $pathToBarcode = __DIR__.$subpathToBarcode;
     $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
     $generatedBytes = $generator->getBarcode($body->{'newbarcode'}, ($body->{'barcodetype'} == "CODE128")?"C128":$body->{'barcodetype'},1,125);
     file_put_contents($pathToBarcode, $generatedBytes);
     
    $dbInstance->saveCodeEntry($body->{'newbarcode'}, $subpathToBarcode, $body->{'fldinput1'}, $body->{'fldinput2'}, $body->{'fldinput3'}, $body->{'barcodetype'});
    $latestBarcodeID = $dbInstance->getLatestBarcodeAdded();
    $data = array(['status' => 'OK', 'addedfilepath'=>$request->getUri()->getBasePath().$subpathToBarcode, 
        'backtrackdata'=>[ 
            'fldinput1'=>$body->{'fldinput1'}, 'fldinput2'=>$body->{'fldinput2'}, 'fldinput3'=>$body->{'fldinput3'}, 
            'barcodetype'=>$body->{'barcodetype'}, 'newbarcode'=>$body->{'newbarcode'}, 'ID'=>$latestBarcodeID ] ]);
    
    $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
//remove several or one barcodes
$app->post('/removecode',function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    
    $dbInstance->removeSavedBarcodes( $body->{'barcodestoremove'} );
});
//update SINGLE barcode entry
$app->post("/updatecode",function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    $barcodeData = $dbInstance->getSingleBarcodeTypeAndPathByID($body->{'barcodetomodify'}->{'ID'});
    //old barcode goes away
    unlink(__DIR__.$barcodeData->{"PATHTOBARCODE"});
    //generate new barcode
    
     $localtime = new DateTime("now", new DateTimeZone('Europe/Kiev'));
     //how should we refer to this image on site
     $subpathToBarcode = "/data/barcodes/".$body->{'barcodetomodify'}->{'rawbarcode'}."_".$localtime->format('Ymd_His')."_".$barcodeData->{"BARCODETYPE"}.".svg";
     //how we should refr to this image on disk
     $pathToBarcode = __DIR__.$subpathToBarcode;
     $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
     $generatedBytes = $generator->getBarcode($body->{'barcodetomodify'}->{'rawbarcode'}, ($barcodeData->{"BARCODETYPE"} == "CODE128")?"C128":$barcodeData->{"BARCODETYPE"},1,125);
     file_put_contents($pathToBarcode, $generatedBytes);
     
    //====================
    $dbInstance->updateSingleBarcode( $body->{'barcodetomodify'}, $subpathToBarcode );
    $data = array(['status' => 'OK', 'addedfilepath'=>$request->getUri()->getBasePath().$subpathToBarcode]);
    $newResponse = $response;
    $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
//render print page with barcodes
$app->post('/printpage', function(Request $request, Response $response, array $args){
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = json_decode( $request->getBody()->getContents() );
    
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-printpage");
    
    $templateTransmission = [];
    $templateTransmission["localizedlist"] = $langsubarray;
    $barcodeslist = $dbInstance->listAllSelectedBarcodes($body->{'barcodeslist'});
    $templateTransmission["renderlist"] = $barcodeslist; $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    return $this->view->render($response, "printpage.twig", $templateTransmission);
});
$app->run();

?>