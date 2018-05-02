<?php
// https://www.slimframework.com/docs/v3/tutorial/first-app.html

require './vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once './config_file.php';
require_once './DatabaseHandler.php';
require_once './localeHandler.php';
require_once './myDateTimeInterval.php';
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
    if (isset($_SESSION["login"])){
        $templateTransmission["login"]=$_SESSION["login"];
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
//save to scan table from the fact of scanning
$app->post('/recvbarcode[/]', function(Request $request, Response $response, array $args){
    $localtime = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
    
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
//save to scan table from manual entry
$app->post('/recvbarcodemanual[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = $request->getParsedBody();
    $datePickNewItem = $body['datepick_newitm'];
    $timePickNewItem = $body['timepick_newitm'];
    $entityIDNewItem = $body['entitypick_newitm'];
    $entityRawText = $dbInstance->getBarcodeTextByID($entityIDNewItem);
    if ((isset($entityRawText) == false)||($entityRawText=="")) {
       //notify about failure
        $_SESSION['manualcodeentrystatus']='NOTFOUND';
    } else {
       //notify about success 
       $time1 = date_create_from_format("d.m.Y H:i:s", $datePickNewItem." ".$timePickNewItem.sprintf(":%02d",rand(0,59)), new DateTimeZone($this->get('settings')['timezonestring']));
       $dbInstance->saveScanTime($entityRawText, intval($entityIDNewItem) ,$time1->format('Y-m-d H:i:s'));
       $_SESSION['manualcodeentrystatus']='OK';
    }
    
    $newResponse = $response;
    return $newResponse->withRedirect('/list');
});
//remove barcode scan event from listings
$app->get('/recvbarcoderemove', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    //require authorization
    if (isset($_SESSION["login"]) ) { 
        if (isset($_GET['scanentryid']) ) {
            $dbInstance->removeSingleScanEntry($_GET['scanentryid']);
            $_SESSION['manualcoderemovestatus']='OK';
        } else {
            $_SESSION['manualcoderemovestatus']='PARAMREQUIRED';
        }
    } else {
        $_SESSION['manualcoderemovestatus']='AUTHREQUIRED';
    }
    $newResponse = $response;
    return $newResponse->withRedirect('/list');
});
//update info about barcode
$app->post('/recvbarcodeupdate[/]', function(Request $request, Response $response, array $args){ 
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = $request->getParsedBody();
        $newResponse = $response; 
        //$newResponse->getBody()->write("HEH");
    $datePickupdItem = $body['datepick_upditm'];
    $timePickupdItem = $body['timepick_upditm'];
    $entityIDupdItem = $body['entitypick_upditm'];
    $rcrdToUpdate=$body['currentscanrecord'];
    
    $entityRawText = $dbInstance->getBarcodeTextByID($entityIDupdItem);
    
    if ((isset($entityRawText) == false)||($entityRawText=="")) {
       //notify about failure
        $_SESSION['manualcodeupdatestatus']='NOTFOUND';
    } else {
        $time1 = date_create_from_format("Y-m-d H:i:s", $datePickupdItem." ".$timePickupdItem, new DateTimeZone($this->get('settings')['timezonestring']));
        $dbInstance->updateScanRecord($rcrdToUpdate, $entityRawText, $time1->format('Y-m-d H:i:s'), $entityIDupdItem);
        $_SESSION['manualcodeupdatestatus']='OK';
    }
    return $newResponse->withRedirect('/list');
});

///*********************
function sortArrayOfScannedItemsByBarcode($in_initialUnsortedStruct) {
    /// The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second
    function cmp($a, $b) {
        //return strcmp( $a->{'RAWBARCODE'}, $b->{'RAWBARCODE'} );
        $stringcomparisonresult = strcmp( $a->{'RAWBARCODE'}, $b->{'RAWBARCODE'} );
        if ($stringcomparisonresult < 0) {
            return -2;
        } elseif ($stringcomparisonresult == 0) {
            $date1php = DateTime::createFromFormat('Y-m-d H:i:s', $a->{'SCANDATETIME'}, new DateTimeZone('Europe/Kiev'));
            $date2php = DateTime::createFromFormat('Y-m-d H:i:s', $b->{'SCANDATETIME'}, new DateTimeZone('Europe/Kiev'));
            if ($date1php < $date2php) {
                return -1;
            } elseif ($date1php > $date2php) {
                return +1;
            } else {
                return 0;
            }
        } else {
            return +2;
        }
    }
    $out_sortedStruct = $in_initialUnsortedStruct;
    usort($out_sortedStruct, "cmp");
    return $out_sortedStruct;
}
function prepareDataStructure($in_initialStruct) { //prepare scan history for displaying them in good way
    $resultStructure = [];
    $step1PreparedArray = sortArrayOfScannedItemsByBarcode($in_initialStruct);
    $previousValue = null; $previousDate = null;
    $itercounter=0; $preparedArraySize = count($step1PreparedArray);
    while ($itercounter<$preparedArraySize) { //array is monotonous by barcode data
            $valueCurrent = $step1PreparedArray[$itercounter];
            if ($previousValue!=$valueCurrent->{"RAWBARCODE"}) { //add new object to resulting structure
                $resultStructure[] = (object)['tableheader'=>"", 'timedarray'=>[]];
                $previousValue = $valueCurrent->{"RAWBARCODE"};
                $resultStructure[count($resultStructure)-1]->{'tableheader'}="[".$valueCurrent->{'KNOWNBARCODE_ID'}.':'.$valueCurrent->{'RAWBARCODE'}.']'.$valueCurrent->{'FIELD1'}.' '.$valueCurrent->{'FIELD2'}.' '.$valueCurrent->{'FIELD3'};
            } //else { //modify last added object
                $currentDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $valueCurrent->{'SCANDATETIME'});
                //$currentDate = date_time_set($currentDateTime, 0, 0, 1);
                if (array_key_exists($currentDateTime->format("d.m.Y"), $resultStructure[count($resultStructure)-1]->{"timedarray"} ) == FALSE) {
                    $resultStructure[count($resultStructure)-1]->{"timedarray"}[$currentDateTime->format("d.m.Y")]=[];
                }
                $resultStructure[count($resultStructure)-1]->{"timedarray"}[$currentDateTime->format("d.m.Y")][] = $currentDateTime->format("H:i:s");                     
            //}        
        $itercounter++; //to next record
    }
    return $resultStructure;
}
//include also timespan calculation in  datastructure for render
//WICKED!
function calculateHoursDataStructure($in_Structure, DataBaseHandler $in_injectedDBstructure, string $in_injectedLocalTimeZone) {
    $resultModifiedStructure = [];
    $itercounter=0; $preparedArraySize = count($in_Structure);
    while ($itercounter<$preparedArraySize) { //iterate over the whole structure
       $resultModifiedStructure[$itercounter] = (object)["timedarray" => [], "tableheader"=>"", "totaltime"=>""];
       $resultModifiedStructure[$itercounter]->{"tableheader"}=$in_Structure[$itercounter]->{"tableheader"};
       $datespanTotal = new TotalHourspan();
       $defaultScheduleToUse = [];
       $configurationsOfAlgorithm = $in_injectedDBstructure->getExistingSettings();
       $injectedUseSchedule = $configurationsOfAlgorithm["USESCHEDULE"];
       $injectedLimitByWorkDayTime = $configurationsOfAlgorithm["LIMITBYWORKDAYTIME"];
       if (filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN)==TRUE) {
            $defaultScheduleToUse=$in_injectedDBstructure->getDefaultCompanySchedule();
       }
       foreach ($in_Structure[$itercounter]->{"timedarray"} as $keydate => $valuetimearray) {
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate] = (object)["timelist"=>[],"subtotaltime"=>"", "additionalstatus"=>[]];
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"timelist"} = $valuetimearray;
           //sum up time between spans
                  // http://fi2.php.net/manual/en/dateinterval.construct.php
           //$datespanSubtotal = new DateInterval("P0000-00-00T00:00:00");
           $datespanSubtotal = new TotalHourspan();
           $intervalCounter = 0; $totaltimescount = count($valuetimearray); 
           while ($intervalCounter<$totaltimescount-1) {
               $value1 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter], new DateTimeZone($in_injectedLocalTimeZone));
               $value2 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter+1], new DateTimeZone($in_injectedLocalTimeZone));
               $intrvl = date_diff($value2, $value1);
               
               //sum up interval. Documentation does not show a built-in function
               $datespanSubtotal->addDateIntervalToThis($intrvl); 
               $intervalCounter+=2;
           }
           //if we are using this and we have exactly one item left in time array and day has finished already (we are not working with the current day)
           if ((filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN)==TRUE)&&(abs($intervalCounter-$totaltimescount) == 1)) { 
               $dateMissed = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter], new DateTimeZone($in_injectedLocalTimeZone));
               $injectedLocalTime = new DateTime("now",new DateTimeZone($in_injectedLocalTimeZone));
               $intrvl2 = date_diff($injectedLocalTime, $dateMissed, TRUE);
               $endOfDay = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultScheduleToUse["TIMEEND"].':00', new DateTimeZone($in_injectedLocalTimeZone));
               if (($intrvl2->d!=0)&&($endOfDay>=$dateMissed) ){
                   
                   //if a remaining unprocessed datetime remains beyond the end of day then discard it.
                   $intrvl3= date_diff($dateMissed, $endOfDay, TRUE);
                   $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"timelist"}[] = $defaultScheduleToUse["TIMEEND"].':00';
                   $datespanSubtotal->addDateIntervalToThis($intrvl3); 
                   $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"additionalstatus"}[0]="closedate";
               }
           }
           $datespanTotal->addTotalHourspanToThis($datespanSubtotal);
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"subtotaltime"} = $datespanSubtotal->myToString();
       }
       $resultModifiedStructure[$itercounter]->{"totaltime"}=$datespanTotal->myToString();
       
       $itercounter++; //to next record
    }
    return $resultModifiedStructure;
}
/**
 * used to generate data for rendering /list/v3
 * @param mixed $in_Structure - associative array from listScanTimesInRange2
 * @param DateTime $in_dateTimeStart - start of range
 * @param DateTime $in_dateTimeEnd - end of range
 * @return stdClass fields: 'AllDates' (each item is array with 2 elements:date string and number of week (1 is monday, 7 is sunday)) and 'AllUsers'. 
 * 'AllUsers' is associative array with keys of BarcodeText and values of stdClass object. Each stdClass object has 2 properties: 'timedarray' and 'display'.
 * 'display' is a string and 'timedarray' is array with 2 items.
 */
function aggregateDataStructure($in_Structure, DateTime $in_dateTimeStart, DateTime $in_dateTimeEnd) {
    $rawResult = (object)['AllDates'=>[], 'AllUsers'=>[]];
    $dateIterator = $in_dateTimeStart; $dateNumericIterator = 0;
    while ($dateIterator<=$in_dateTimeEnd) {
        $rawResult->{'AllDates'}[]=[$dateIterator->format("d.m.Y"), intval( $dateIterator->format("N") )];
        $dateIterator->add(new DateInterval('P1D'));
        $dateNumericIterator++;
    }
    $prevusrID = null; $currentSubtotal = new TotalHourSpan();
    foreach ($in_Structure as $valueFromStructure) {
        //have we met this user before ?
        if (isset($rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]) == FALSE) {
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}] = (object)['timedarray'=>[], 'display'=>''];
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'display'}=$valueFromStructure->{"FIELD1"}." ".$valueFromStructure->{"FIELD2"}." ".$valueFromStructure->{"FIELD3"}
                                                                              ."[".$valueFromStructure->{"BCODE"}.",".$valueFromStructure->{"RAWBARCODE"}."]";
        }
        // http://php.net/manual/ru/function.property-exists.php
        if ((property_exists($valueFromStructure, "SCANID")==FALSE)||($valueFromStructure->{"SCANID"} == NULL)) {
            //it shows that this user has no scans in period. If we have not done it, fill corresponding array with zeros
            if ( count($rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}) == 0) {
                for ($i=0; i<$dateNumericIterator; $i++) {
                    $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[] = array(new TotalHourSpan(), 0.0);
                }
            }
        } else {  //this user has scan
            if ($prevusrID != $valueFromStructure->{"BCODE"}) {  //switched to new user
                
            } else {
                
            }
        }
    }
    return $rawResult;
}
///*********************

$app->get('/list/v3[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    $templateTransmission['wayback'] = "/list/v3";
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    } 
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist");
    $langsubarrayspecial = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist-special");
    $restrictaccessenabled = $this->get('settings')['restrictAccessSpecial']; //see config_file.php
    if ($restrictaccessenabled) { //perform some page restriction handling
        if (isset($_SESSION["login"])) {
            $templateTransmission["login"]=$_SESSION["login"];
        } else { //render restriction
            $translationSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-restricted");
            $templateTransmission["localizedmessages"] = $commonsubarray+$translationSubarray;
            $templateTransmission["waytoproceed"] = '/list/v3';
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
        }
    }   
    
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
            $tmplocaldate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocaldate->format("d.m.Y");
        }
        if ( ($fromDateEnabled === FALSE) && ($toDateEnabled === TRUE) ) { //2nd date is to be used as current date
            $dateEndString = urldecode($_GET["to"]); 
            $tmplocaldate = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
            $tmpprevdate = $tmplocaldate->sub(new DateInterval("P1M"));
            $dateStartString = $tmpprevdate->format("d.m.Y");
        }
        if (($fromDateEnabled === FALSE) && ($toDateEnabled === FALSE)) {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
        }
    } else {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
    }
    $time1 = date_create_from_format("d.m.Y", $dateStartString, new DateTimeZone($this->get('settings')['timezonestring']));
    $time2 = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
    if ($time2<$time1) {
        $time3= $time1;
        $time1 = $time2;
        $time2 = $time3;
    }
    $sqlitedateStart = date_time_set($time1,00,01)->format("Y-m-d H:i");
    $sqlitedateEnd = date_time_set($time2,23,59)->format("Y-m-d H:i");
    

          $preparedUseSchedule = $this->get('settings')['calculateTimeUseSchedule'];
          $preparedCalculateTime = $this->get('settings')['calculateTime'];
    
    $templateTransmission["localizedmessages"] = $commonsubarray+$langsubarray+$langsubarrayspecial;
    $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    $templateTransmission["datetime"]["from"] = $dateStartString;
    $templateTransmission["datetime"]["to"] = $dateEndString;
    
    $templateTransmission["datetime"]["fromstring"] = urlencode($dateStartString);
    $templateTransmission["datetime"]["tostring"] = urlencode($dateEndString);    
    if ($preparedCalculateTime == FALSE) {
            $rawscanTimeValues = $dbInstance->listScanTimesInRange($sqlitedateStart, $sqlitedateEnd);
            $rawscanTimeValues = prepareDataStructure($rawscanTimeValues);
        $templateTransmission["scanlist"] = $rawscanTimeValues;
        return $this->view->render($response, "listbarcode2.twig",$templateTransmission);
    } else {
            $rawscanTimeValues = $dbInstance->listScanTimesInRange2($sqlitedateStart, $sqlitedateEnd);
            $updatedscanTimeValues = aggregateDataStructure($rawscanTimeValues, date_time_set($time1,00,01), date_time_set($time2,23,59) );
        $templateTransmission["scanlist"] = $updatedscanTimeValues;
        return $this->view->render($response, "listbarcode3.twig",$templateTransmission);
        //$debugLine = "<html><head></head><body>HERE BE EXPANDED TABLE OF REGISTERED ITEMS</body> </html>";
        //return $response->withHeader('Content-type', 'text/html')->write($debugLine);
    }
});

$app->get('/list/v2[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    $templateTransmission['wayback'] = "/list/v2";
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    } 
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist");
    
    $restrictaccessenabled = $this->get('settings')['restrictAccessSpecial']; //see config_file.php
    if ($restrictaccessenabled) { //perform some page restriction handling
        if (isset($_SESSION["login"])) {
            $templateTransmission["login"]=$_SESSION["login"];
        } else { //render restriction
            $translationSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-restricted");
            $templateTransmission["localizedmessages"] = $commonsubarray+$translationSubarray;
            $templateTransmission["waytoproceed"] = '/list/v2';
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
        }
    }   
    
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
            $tmplocaldate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocaldate->format("d.m.Y");
        }
        if ( ($fromDateEnabled === FALSE) && ($toDateEnabled === TRUE) ) { //2nd date is to be used as current date
            $dateEndString = urldecode($_GET["to"]); 
            $tmplocaldate = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
            $tmpprevdate = $tmplocaldate->sub(new DateInterval("P1M"));
            $dateStartString = $tmpprevdate->format("d.m.Y");
        }
        if (($fromDateEnabled === FALSE) && ($toDateEnabled === FALSE)) {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
        }
    } else {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
    }
    $time1 = date_create_from_format("d.m.Y", $dateStartString, new DateTimeZone($this->get('settings')['timezonestring']));
    $time2 = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
    if ($time2<$time1) {
        $time3= $time1;
        $time1 = $time2;
        $time2 = $time3;
    }
    $sqlitedateStart = date_time_set($time1,00,01)->format("Y-m-d H:i");
    $sqlitedateEnd = date_time_set($time2,23,59)->format("Y-m-d H:i");
    
    $rawscanTimeValues = $dbInstance->listScanTimesInRange($sqlitedateStart, $sqlitedateEnd);
    $rawscanTimeValues = prepareDataStructure($rawscanTimeValues);
          $preparedUseSchedule = $this->get('settings')['calculateTimeUseSchedule'];
          $preparedCalculateTime = $this->get('settings')['calculateTime'];
    if ($preparedCalculateTime == TRUE) {
        $updatedscanTimeValues = calculateHoursDataStructure($rawscanTimeValues, $dbInstance, $this->get('settings')['timezonestring'] );
    }
    
    $templateTransmission["localizedmessages"] = $commonsubarray+$langsubarray;
    $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    $templateTransmission["datetime"]["from"] = $dateStartString;
    $templateTransmission["datetime"]["to"] = $dateEndString;
    
    $templateTransmission["datetime"]["fromstring"] = urlencode($dateStartString);
    $templateTransmission["datetime"]["tostring"] = urlencode($dateEndString);    
    if ($preparedCalculateTime == FALSE) {
        $templateTransmission["scanlist"] = $rawscanTimeValues;
        return $this->view->render($response, "listbarcode2.twig",$templateTransmission);
    } else {
        $templateTransmission["scanlist"] = $updatedscanTimeValues;
        return $this->view->render($response, "listbarcode2usetime.twig",$templateTransmission);
        //$debugLine = "<html><head></head><body>HERE BE EXPANDED TABLE OF REGISTERED ITEMS</body> </html>";
        //return $response->withHeader('Content-type', 'text/html')->write($debugLine);
    }
});

$app->get('/list[/]', function(Request $request, Response $response, array $args){
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    //$rawscanTimeValues = $dbInstance->listAllScanTime();
    
    $templateTransmission = [];
    $templateTransmission['wayback'] = "/list";
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],   "page-scanlist");
    $langsubarray2 = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"],  "pageform-scanlist");

    $restrictaccessenabled = $this->get('settings')['restrictAccessSpecial']; //see config_file.php
    if ($restrictaccessenabled) { //perform some page restriction handling
        if (isset($_SESSION["login"])) {
            $templateTransmission["login"]=$_SESSION["login"];
        } else { //render restriction
            $translationSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-restricted");
            $templateTransmission["localizedmessages"] = $commonsubarray+$translationSubarray;
            $templateTransmission["waytoproceed"] = '/list';
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
        }
    }   

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
            $tmplocaldate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocaldate->format("d.m.Y");
        }
        if ( ($fromDateEnabled === FALSE) && ($toDateEnabled === TRUE) ) { //2nd date is to be used as current date
            $dateEndString = urldecode($_GET["to"]); 
            $tmplocaldate = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
            $tmpprevdate = $tmplocaldate->sub(new DateInterval("P1M"));
            $dateStartString = $tmpprevdate->format("d.m.Y");
        }
        if (($fromDateEnabled === FALSE) && ($toDateEnabled === FALSE)) {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
        }
    } else {
            $tmplocalenddate = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
            $dateEndString = $tmplocalenddate->format("d.m.Y");
            $tmplocalstartdate = $tmplocalenddate->sub(new DateInterval("P1M"));
            $dateStartString = $tmplocalstartdate->format("d.m.Y");
    }
    $time1 = date_create_from_format("d.m.Y", $dateStartString, new DateTimeZone($this->get('settings')['timezonestring']));
    $time2 = date_create_from_format("d.m.Y", $dateEndString, new DateTimeZone($this->get('settings')['timezonestring']));
    if ($time2<$time1) {
        $time3= $time1;
        $time1 = $time2;
        $time2 = $time3;
    }
    $sqlitedateStart = date_time_set($time1,00,01)->format("Y-m-d H:i");
    $sqlitedateEnd = date_time_set($time2,23,59)->format("Y-m-d H:i");
    $rawscanTimeValues = $dbInstance->listScanTimesInRange($sqlitedateStart, $sqlitedateEnd);

    $templateTransmission["barcodeentrylistfrm"] = $dbInstance->listAllBarcodes();
    
    $templateTransmission["barcodeentrydefaulttime"] = $dbInstance->getDefaultCompanySchedule()["TIMESTART"];
    
    if (isset($_SESSION['manualcodeentrystatus'])) {
        $templateTransmission['manualcodeentrystatus'] = $_SESSION['manualcodeentrystatus'];
        unset($_SESSION['manualcodeentrystatus']);
    }
    
    if (isset($_SESSION['manualcodeupdatestatus'])) {
        $templateTransmission['manualcodeupdatestatus'] = $_SESSION['manualcodeupdatestatus'];
        unset($_SESSION['manualcodeupdatestatus']);
    }
    
    if (isset($_SESSION['manualcoderemovestatus'])) {
        $templateTransmission['manualcoderemovestatus'] = $_SESSION['manualcoderemovestatus'];
        unset($_SESSION['manualcoderemovestatus']);
    }
    
    $templateTransmission["localizedmessages"] = $commonsubarray+$langsubarray+$langsubarray2;
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
    
    $restrictaccessenabled = $this->get('settings')['restrictAccessSpecial']; //see config_file.php
    if ($restrictaccessenabled) { //perform some page restriction handling
        if (isset($_SESSION["login"])) {
            $templateTransmission["login"]=$_SESSION["login"];
        } else { //render restriction
            $translationSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-restricted");
            $templateTransmission["localizedmessages"] = $commonsubarray+$translationSubarray;
            $templateTransmission["waytoproceed"] = '/registeredbarcodes';
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
        }
    }
    
    $registeredCodesSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-registeredbarcodes");
    $barcodeslist = $dbInstance->listAllBarcodes();
    $templateTransmission["registeredinfo"] = $barcodeslist;
    $templateTransmission["localizedmessages"] = $commonsubarray+$registeredCodesSubarray;

    return $this->view->render($response, "registeredbarcodes.twig", $templateTransmission);
});

$app->get('/options[/]', function(Request $request, Response $response, array $args){ 
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    $templateTransmission['wayback'] = "/options";
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }    
    $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
    $restrictaccessenabled = $this->get('settings')['restrictAccessSpecial']; //see config_file.php
    if ($restrictaccessenabled) { //perform some page restriction handling
        if (isset($_SESSION["login"])) {
            $templateTransmission["login"]=$_SESSION["login"];
        } else { //render restriction
            $translationSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-restricted");
            $templateTransmission["localizedmessages"] = $commonsubarray+$translationSubarray;
            $templateTransmission["waytoproceed"] = '/options';
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
        }
    }
    $optionsSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-options");
    $templateTransmission["localizedmessages"] = $commonsubarray+$optionsSubarray;
    
    $defaultScheduleArray = $dbInstance->getDefaultCompanySchedule();
    $templateTransmission["defaultschedule"] = $defaultScheduleArray;
    //$uwschd = $this->get('settings')['calculateTimeUseSchedule'];
    //$utlwd = $this->get('settings')["calculateTimeLimitedByWorkDay"];
    $commonconfigarray = $dbInstance->getExistingSettings();
    $templateTransmission["commonconfig"] = ["UWSchd"=>filter_var($commonconfigarray["USESCHEDULE"],FILTER_VALIDATE_BOOLEAN), "UTLWrkDay"=>filter_var($commonconfigarray["LIMITBYWORKDAYTIME"],FILTER_VALIDATE_BOOLEAN)];
    
    return $this->view->render($response, "options.twig", $templateTransmission);
});

$app->post('/saveoptions[/]', function(Request $request, Response $response, array $args) {
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = $request->getParsedBody();
    $arrayToUse = ["timestart"=>$body["timestart"], "timeend"=>$body["timeend"], "dateused"=>"0001-01-02"];
    $dbInstance->updateCompanySchedule($arrayToUse);
    $dbInstance->updateSettings(["USESCHEDULE"=>isset($body["useschedule"]) ? 1 : 0, "LIMITBYWORKDAYTIME"=>isset($body["limitbyworkdaytime"]) ? 1 : 0 ]);
    return $response->withRedirect('/options');
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
     $localtime = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
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
    if(file_exists(__DIR__.$barcodeData->{"PATHTOBARCODE"})){
        unlink(__DIR__.$barcodeData->{"PATHTOBARCODE"});
    }
    //generate new barcode
    
     $localtime = new DateTime("now", new DateTimeZone($this->get('settings')['timezonestring']));
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
    $templateTransmission = [];
    $privateLocaleHandler = new localeHandler();
    if (isset($_SESSION["lang"] )) {
        $templateTransmission["lang"] = $_SESSION["lang"];
    } else {
        $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
        $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
    }
    $langsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-printpage");
    
    
    $templateTransmission["localizedlist"] = $langsubarray;
    $barcodeslist = $dbInstance->listAllSelectedBarcodes($body->{'barcodeslist'});
    $templateTransmission["renderlist"] = $barcodeslist; $templateTransmission["thishost"] = $_SERVER['SERVER_NAME'];
    return $this->view->render($response, "printpage.twig", $templateTransmission);
});

$app->post('/manualscanentry', function(Request $request, Response $response, array $args){ //type in entry manually from admin interface
    $body = json_decode( $request->getBody()->getContents() );
});

$app->post('/processvalidation', function(Request $request, Response $response, array $args){ 
    session_start();
    $body = $request->getParsedBody();
    $rawWayBack = $body['backurl'];
    $rawPosition = $body['positioninput'];
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $templateTransmission = [];
    
    if ($dbInstance->validateAccessRole($rawPosition) > 0) { //validation passed, proceeding to page defined in rawWayBack
        $_SESSION['login']=$rawPosition;
        return $response->withRedirect($rawWayBack."/");
    } else { //validation did not pass
        $privateLocaleHandler = new localeHandler();
        if (isset($_SESSION["lang"] )) {
            $templateTransmission["lang"] = $_SESSION["lang"];
        } else {
            $_SESSION["lang"] = $privateLocaleHandler->getDefaultLocale();
            $templateTransmission["lang"]=$privateLocaleHandler->getDefaultLocale();
        }
        $commonsubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "common");
        $templateTransmission["localizedmessages"] = $commonsubarray;
        $templateTransmission["waytoproceed"] = $rawWayBack;
            return $this->view->render($response, "protectpage.twig", $templateTransmission);
    }
});
// https://www.slimframework.com/docs/v3/objects/router.html#route-placeholders
$app->get('/signoff/{accessrolepath}[/]', function(Request $request, Response $response, array $args) { 
    session_start();
    if (isset($_SESSION["login"])){
        unset($_SESSION["login"]);
    }
    $paramValueWayBack = $request->getQueryParam('wayback');
    return $response->withRedirect($paramValueWayBack."/");
});


$app->run();

?>