<?php
// https://www.slimframework.com/docs/v3/tutorial/first-app.html

require './vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once './config_file.php';
require_once './DatabaseHandler.php';
require_once './localeHandler.php';
require_once './myDateTimeInterval.php';
require_once './myPHPXLSXProcessor.php';
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

/**
 * include also timespan calculation in  datastructure for render. used in /list/v2
 * @param type $in_Structure - obtained after prepareDataStructure
 * @param DataBaseHandler $in_injectedDBstructure
 * @param string $in_injectedLocalTimeZone
 * @return array of stdclassobject. fields: 'tableheader' - string with name of entity for which the aggregated time is calculated. 'totaltime' - total time for this entity calculated over the all dates. 'totalovertime' - total overtime for this entity calculated over the all dates
 * 'timedarray' - associative array of timestamps of scans (may also contain records added by this routine, with end-of-day working time). 
 * Key is date for which we are assembling scan facts, value is stdclass object with fields: 
 * 'subtotaltime' - aggregated value of time for this date.
 * 'subtotalovertime' - aggregated value of overtime for this date.
 * 'timelist' - array with timestamps in string
 * 'additionalstatus' - if 'timelist' contains entries which have been artificially added then [0] has 'closedate' entry
 */
function calculateHoursDataStructure($in_Structure, DataBaseHandler $in_injectedDBstructure, string $in_injectedLocalTimeZone) {
    $resultModifiedStructure = [];
    $itercounter=0; $preparedArraySize = count($in_Structure);
       $defaultScheduleToUse = []; $defaultBreakToUse = []; 
       $configurationsOfAlgorithm = $in_injectedDBstructure->getExistingSettings();
       $injectedUseSchedule = $configurationsOfAlgorithm["USESCHEDULE"];
       $injectedInvolveBreakTime = $configurationsOfAlgorithm["LIMITBYWORKDAYTIME"];
       $refinedInvolveBreakTime = filter_var($injectedInvolveBreakTime, FILTER_VALIDATE_BOOLEAN);
       if (filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN)==TRUE) {
            $defaultScheduleToUse=$in_injectedDBstructure->getDefaultCompanySchedule();
            if ( filter_var($injectedInvolveBreakTime, FILTER_VALIDATE_BOOLEAN)==TRUE ) {
                $defaultBreakToUse = $in_injectedDBstructure->getDefaultCompanyBreak();
            }
       }
       
    while ($itercounter<$preparedArraySize) { //iterate over the whole structure
       $resultModifiedStructure[$itercounter] = (object)["timedarray" => [], "tableheader"=>"", "totaltime"=>"", "totalovertime"=>""];
       $resultModifiedStructure[$itercounter]->{"tableheader"}=$in_Structure[$itercounter]->{"tableheader"};
       $datespanTotal = new TotalHourspan();
       
       foreach ($in_Structure[$itercounter]->{"timedarray"} as $keydate => $valuetimearray) {
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate] = (object)["timelist"=>[],"subtotaltime"=>"", "subtotalovertime"=>"", "additionalstatus"=>[]];
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"timelist"} = $valuetimearray;
           //sum up time between spans
                  // http://fi2.php.net/manual/en/dateinterval.construct.php
           //$datespanSubtotal = new DateInterval("P0000-00-00T00:00:00");
           $datespanSubtotal = new TotalHourspan();
           $datespanSubtotalOvertime = new TotalHourSpan();
           $intervalCounter = 0; $totaltimescount = count($valuetimearray); 
           $detalizedBreak=(object)['breakstart'=>null, 'breakend'=>null, 'breakintrvl'=>null];
           //define break in terms of datetime class
            if ( $refinedInvolveBreakTime==TRUE ) {
                $detalizedBreak->{'breakstart'} = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultBreakToUse["TIMESTART"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                $detalizedBreak->{'breakend'} = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultBreakToUse["TIMEEND"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                $detalizedBreak->{'breakintrvl'} = date_diff($detalizedBreak->{'breakend'}, $detalizedBreak->{'breakstart'});
            }
           //collection of switches, indicating a usage of break.
            //[0] - scan occured before break start. [1] - scan occured after break start but before end. [2] - scan occured after break end
           $breakUsed = [false, false, false]; 
           $heuristicsSubtractBreakTime = TRUE;
           while ($intervalCounter<$totaltimescount-1) {
               $value1 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter], new DateTimeZone($in_injectedLocalTimeZone));
               $value2 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter+1], new DateTimeZone($in_injectedLocalTimeZone));
               $startOfDay = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultScheduleToUse['TIMESTART'].':00', new DateTimeZone($in_injectedLocalTimeZone));
               $endOfDay = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultScheduleToUse['TIMEEND'].':00', new DateTimeZone($in_injectedLocalTimeZone));
               
               $intrvl = date_diff($value2, $value1);
               // BREAK TIME CALCULATION IS BEING RESOLVED HERE. most people here use break, so this case would be rare. It may be incorrect, but who knows.
               if($refinedInvolveBreakTime==TRUE) {
                   //last scan was done before break, there are no more scans
                   if (($value2<$detalizedBreak->{'breakstart'})&&($intervalCounter+2>=$totaltimescount-1)) {
                       $heuristicsSubtractBreakTime = FALSE;
                   } else {
                       //end of previous time segment relates to time before break, start of next (current, on this iteration) segment (indicated by $value1) relates to time after break. 
                       //it is too tidy for a worker! He just got a little bit longer break.
                       if (isset($valuetimearray[$intervalCounter-1])) {
                           $valueprev1 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter-1], new DateTimeZone($in_injectedLocalTimeZone));
                           assert($value1>$valueprev1);
                           if ( ($valueprev1<$detalizedBreak->{'breakstart'})&&($value1>$detalizedBreak->{'breakend'}) ) {
                               $heuristicsSubtractBreakTime = FALSE;
                           }
                       } else {
                           //scan was done after break start, 
                           if ( isset($value2)&&($value2>$detalizedBreak->{'breakstart'}) ) {
                               if ( $intervalCounter+1<=$totaltimescount-1 ) {// but no scans were done after that. do not subtract break time, if $value2 is not in range of break
                                    if ($value2<$detalizedBreak->{'breakend'}) {
                                    $heuristicsSubtractBreakTime = FALSE; 
                                    } else {
                                        
                                    }
                               } else {  
                                    $valuenext1 = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter+1], new DateTimeZone($in_injectedLocalTimeZone));
                                    if ($valuenext1<$detalizedBreak->{'breakend'}) { //scan was done right before the end of break
                                        $heuristicsSubtractBreakTime = FALSE; 
                                    } else { //scan (it indicates start of next period, btw) was done right after the period end. (do not) subtract the break.
                                        $heuristicsSubtractBreakTime = FALSE; 
                                    }
                               }
                           } else {
                               
                               
                           }
                       }
                       
                   }
               }               
                    //check overtime here
                       if ( (isset($value1))&&($value1 < $startOfDay) )  {
                            if ((isset($value2)) && ($value2<=$startOfDay)) {
                                assert($value1<=$value2, "Start of period is less than its end");
                                $datespanSubtotalOvertime->addDateIntervalToThis(date_diff($value1, $value2));                       
                            } else {
                                $datespanSubtotalOvertime->addDateIntervalToThis(date_diff($value1, $startOfDay));
                            }

                       }
                       if ( (isset($value2))&&($value2 > $endOfDay) ) {
                          if (isset($value1)) {
                            if ($value1<=$endOfDay) {
                                $datespanSubtotalOvertime->addDateIntervalToThis(date_diff($endOfDay, $value2));
                            } else {
                                $datespanSubtotalOvertime->addDateIntervalToThis(date_diff($value1, $value2));
                            }
                          }
                       }
               
               //sum up interval. Documentation does not show a built-in function
               $datespanSubtotal->addDateIntervalToThis($intrvl); 
               $intervalCounter+=2;
           }
           //if we are using this and we have exactly one item left in time array and day has finished already (we are not working with the current day)
           
           if ( (filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN)==TRUE) ) {
               //check missed scan on end of day here. Worker gone home and forgot to scan...
            if ( (abs($intervalCounter-$totaltimescount) == 1) ) { 
                $dateMissed = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$valuetimearray[$intervalCounter], new DateTimeZone($in_injectedLocalTimeZone));
                $injectedLocalTime = new DateTime("now",new DateTimeZone($in_injectedLocalTimeZone));
                $intrvl2 = date_diff($injectedLocalTime, $dateMissed, TRUE);
                $endOfDay = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultScheduleToUse["TIMEEND"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                $startOfDay = DateTime::createFromFormat('d.m.Y H:i:s', $keydate.' '.$defaultScheduleToUse["TIMESTART"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                if (($intrvl2->d!=0)&&($endOfDay>=$dateMissed) ){

                    //if a remaining unprocessed datetime remains beyond the end of day then discard it.
                    $intrvl3= date_diff($dateMissed, $endOfDay, TRUE);
                    $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"timelist"}[] = $defaultScheduleToUse["TIMEEND"].':00';
                    $datespanSubtotal->addDateIntervalToThis($intrvl3); 
                    $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"additionalstatus"}[0]="closedate";
                }
                if ($totaltimescount == 1) { // a single entry was on that day. apply overtime, only if applicable
                    if ($dateMissed<$startOfDay) {
                        $datespanSubtotalOvertime->addDateIntervalToThis(date_diff($dateMissed, $startOfDay));
                    }
                    if($refinedInvolveBreakTime==TRUE) { //subtract break time, if it was a last scan and it happened before break time start
                        if ($dateMissed<$detalizedBreak->{'breakstart'}) {
                            $heuristicsSubtractBreakTime = TRUE; 
                        }
                    }
                    
                }
            }
            
            
           }
           
           if (($refinedInvolveBreakTime==TRUE)&&($heuristicsSubtractBreakTime)) {
               //subtract break time!
               $datespanSubtotal->subtractDateIntervalToThis($detalizedBreak->{'breakintrvl'});
               $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"additionalstatus"}[1] = "break";
           }
           $datespanTotal->addTotalHourspanToThis($datespanSubtotal);
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"subtotaltime"} = $datespanSubtotal->myToString();
           $resultModifiedStructure[$itercounter]->{"timedarray"}[$keydate]->{"subtotalovertime"} = $datespanSubtotalOvertime->myToString();
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
 * @param $in_injectedLocalTimeZone - string with timezone for dates calculation
 * @param DataBaseHandler $in_injectedDBstructure - it is handy to have Database structure around
 * @return stdClass fields: 'AllDates' (each item is array with 2 elements:date string and number of week (1 is monday, 7 is sunday)) and 'AllUsers'. 
 * 'AllUsers' is associative array with keys of BarcodeText and values of stdClass object. Each stdClass object has 3 properties: 'timedarray' and 'display' and 'xlsx'.
 * 'display' is a string and 'timedarray' is array with numerical indexes. These indexes correspond to entries in AllDates Array. If some index is not set then it means that there is no entries for this date.
 * 'xlsx' is stdClass object
 * If some index is set then the entry is array. 
 * Item[0] is TotalHourSpan for total time, Item[1] is its converted float value. Item[2] is 0 (break time not used) or 1 (break time used). Item[3] is TotalHourSpan for total overtime, Item[4] is float
 */
function aggregateDataStructure($in_Structure, DateTime $in_dateTimeStart, DateTime $in_dateTimeEnd, $in_injectedLocalTimeZone, DataBaseHandler $in_injectedDBstructure) {
    $rawResult = (object)['AllDates'=>[], 'AllUsers'=>[]];
    $dateIterator = $in_dateTimeStart; $dateNumericIterator = 0;
       //by default we assume that break time is subtracted
       $defaultScheduleToUse = []; $defaultBreakToUse = []; $heuristicsSubtractBreakTime = TRUE;
       $configurationsOfAlgorithm = $in_injectedDBstructure->getExistingSettings();
       $injectedUseSchedule = $configurationsOfAlgorithm["USESCHEDULE"];
       $injectedLimitByWorkDayTime = $configurationsOfAlgorithm["LIMITBYWORKDAYTIME"];
       $injectedUseSchedule = filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN);
       $refinedInvolveBreakTime = filter_var($injectedLimitByWorkDayTime, FILTER_VALIDATE_BOOLEAN);
       if ($injectedUseSchedule==TRUE) {
            $defaultScheduleToUse=$in_injectedDBstructure->getDefaultCompanySchedule();
       }
       if ($refinedInvolveBreakTime == TRUE) {
           $defaultBreakToUse = $in_injectedDBstructure->getDefaultCompanyBreak();
       }
       $detalizedBreak=(object)['breakstart'=>null, 'breakend'=>null, 'breakintrvl'=>null];
    
    while ($dateIterator<=$in_dateTimeEnd) { //initialize dates for header
        $rawResult->{'AllDates'}[]=[$dateIterator->format("Y-m-d"), intval( $dateIterator->format("N") )];
        $dateIterator->add(new DateInterval('P1D'));
        $dateNumericIterator++;
    }
    $prevTimeStamp = null; $currentPeriodClosed = TRUE; $prevIndex = -1; $storedBCODE = null;
    foreach ($in_Structure as $valueFromStructure) { //process all the records
        $storedBCODE = $valueFromStructure->{"BCODE"};
        //+++++++++++++ the period is still opened
            if (( $injectedUseSchedule==TRUE ) && ($currentPeriodClosed === FALSE)) {
               $dateMissed = $prevTimeStamp;    
               $valueFromStructurePrev = $in_Structure[$prevIndex];
               //checking switchout to the another date
               $brassKeyOfDateTimeSwitching = (explode(" ",$valueFromStructurePrev->{"SCANDATETIME"})[0] != explode(" ",$valueFromStructure->{"SCANDATETIME"})[0]);
               //checking switchout to another user
               $bronzeKeyOfUserSwitching = ($valueFromStructurePrev->{"BCODE"} != $valueFromStructure->{"BCODE"});
               
               if (($refinedInvolveBreakTime == TRUE)&&($brassKeyOfDateTimeSwitching)&&(FALSE) ) { //make a break for current datetime. do not use it here
                    $detalizedBreak->{'breakstart'} = DateTime::createFromFormat('d.m.Y H:i:s', explode(" ",$valueFromStructure->{"SCANDATETIME"})[0].' '.$defaultBreakToUse["TIMESTART"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                    $detalizedBreak->{'breakend'} = DateTime::createFromFormat('d.m.Y H:i:s', explode(" ",$valueFromStructure->{"SCANDATETIME"})[0].' '.$defaultBreakToUse["TIMEEND"].':00', new DateTimeZone($in_injectedLocalTimeZone));
                    $detalizedBreak->{'breakintrvl'} = date_diff($detalizedBreak->{'breakend'}, $detalizedBreak->{'breakstart'});
               }
               //above two conditions indicate that we should sum up a terms of time. Also resolve here break time
               if ($brassKeyOfDateTimeSwitching || $bronzeKeyOfUserSwitching) {               
               $injectedLocalTime = new DateTime("now",new DateTimeZone($in_injectedLocalTimeZone));
               $endTimeAsArray = explode(":", $defaultScheduleToUse["TIMEEND"] );
               $intrvl2 = date_diff($injectedLocalTime, $dateMissed, TRUE);
               $endOfDay = clone $dateMissed;
               date_time_set($endOfDay, intval($endTimeAsArray[0]), intval($endTimeAsArray[1]), 0);
                if (($intrvl2->d!=0)&&($endOfDay>=$dateMissed) ) {                   
                   //if a remaining unprocessed datetime remains beyond the end of day then discard it. Else use it
                   $intrvl3= date_diff($dateMissed, $endOfDay, TRUE);
                   
                   $indexFound = null; $currentIndexOfDate = 0;
                    foreach ($rawResult->{'AllDates'} as $valueDate) {
                        if ( $valueDate[0] == explode(" ", $valueFromStructurePrev->{'SCANDATETIME'})[0]) {
                            $indexFound = $currentIndexOfDate;
                            break;
                        }
                        $currentIndexOfDate++;
                    }
                    if ($indexFound === NULL) { throw new OutOfBoundsException("DATE ".$valueFromStructurePrev->{'SCANDATETIME'}." NOT  IN RANGE"); return; }
                   
                   
                    $rawResult->{'AllUsers'}[$valueFromStructurePrev->{"BCODE"}]->{'timedarray'}[$indexFound][0]->addDateIntervalToThis($intrvl3);                    
                    //SUBTRACT BREAK TIME HERE. (NOPE)
                    if (($heuristicsSubtractBreakTime == TRUE)&&($refinedInvolveBreakTime==TRUE)&&(FALSE)) {
                        $rawResult->{'AllUsers'}[$valueFromStructurePrev->{"BCODE"}]->{'timedarray'}[$indexFound][0]->subtractDateIntervalToThis($detalizedBreak->{'breakintrvl'});
                    }
                    $rawResult->{'AllUsers'}[$valueFromStructurePrev->{"BCODE"}]->{'timedarray'}[$indexFound][1] = $rawResult->{'AllUsers'}[$valueFromStructurePrev->{"BCODE"}]->{'timedarray'}[$indexFound][0]->myToFloat();
                    //BREAK TIME HANDLING ENDS
                    $currentPeriodClosed = TRUE;
                    $heuristicsSubtractBreakTime = TRUE; //back to defaults
                }
                
               }
            }
        //+++++++++++++
        
        //have we met this user before ? SET HERE THE BASIC USER IDENTIFICATION INFO
        if (isset($rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]) == FALSE) { //...no, we have not                        
            $prevTimeStamp = null; $currentPeriodClosed = TRUE;
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}] = (object)['timedarray'=>[], 'display'=>'','xlsx'=>(object)['gender'=>0, 'position'=>'']];
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'display'}=$valueFromStructure->{"FIELD1"}." ".$valueFromStructure->{"FIELD2"}." ".$valueFromStructure->{"FIELD3"}
                                                                              ."[".$valueFromStructure->{"BCODE"}.",".$valueFromStructure->{"RAWBARCODE"}."]";
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'xlsx'}->{'gender'} = $valueFromStructure->{"GENDER"};
            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'xlsx'}->{'position'} = $valueFromStructure->{"POSITION"};
            $heuristicsSubtractBreakTime = TRUE;
        }
        // http://php.net/manual/ru/function.property-exists.php
        if ((property_exists($valueFromStructure, "SCANID")==FALSE)||($valueFromStructure->{"SCANID"} === NULL)) {
            //it shows that this user has no scans in period, so SCANID is set to NULL. By query,the result contains only one record for user with SCANID == NULL if database is healthy
        } else {  //this user has scan
            //get the corresponding index in date array
            $indexFound = null; $currentIndex = 0;
            foreach ($rawResult->{'AllDates'} as $valueDate) {
                if ( $valueDate[0] == explode(" ", $valueFromStructure->{'SCANDATETIME'})[0]) {
                    $indexFound = $currentIndex;
                    break;
                }
                $currentIndex++;
            }
            if ($indexFound === NULL) { throw new OutOfBoundsException("DATE ".$valueFromStructure->{'SCANDATETIME'}." NOT  IN RANGE"); return; }
            if (key_exists($indexFound, $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}) == FALSE) {
                $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound] = [new TotalHourSpan(),0.0,0,new TotalHourSpan(),0.0];
            } //else { //we have some recordings with this period and this user
                if ($currentPeriodClosed === TRUE) { //got new entry, and we have this period indicated as closed; reopen it
                    $prevTimeStamp = DateTime::createFromFormat("Y-m-d H:i:s", $valueFromStructure->{"SCANDATETIME"}, new DateTimeZone($in_injectedLocalTimeZone));
                    $currentPeriodClosed = FALSE;
                    $heuristicsSubtractBreakTime = TRUE;
                } else { //find difference between dates, close the period
                    $value2 = DateTime::createFromFormat("Y-m-d H:i:s", $valueFromStructure->{"SCANDATETIME"}, new DateTimeZone($in_injectedLocalTimeZone));
                    $value1 = $prevTimeStamp;
                    
                    $intrvln = date_diff($value2, $value1);
                    $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][0]->addDateIntervalToThis($intrvln);
                    $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][1] = $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][0]->myToFloat();
                    $currentPeriodClosed = TRUE;
                    
                    
                    $startOfDay = DateTime::createFromFormat("Y-m-d H:i:s", $dateMissed->format("Y-m-d")." ".$defaultScheduleToUse['TIMESTART'].":00", new DateTimeZone($in_injectedLocalTimeZone));
                    $endOfDayRedefined = DateTime::createFromFormat("Y-m-d H:i:s", $dateMissed->format("Y-m-d")." ".$defaultScheduleToUse['TIMEEND'].":00", new DateTimeZone($in_injectedLocalTimeZone));
                    //add here overtime handling
                        if ( (isset($value1))&&($value1 < $startOfDay) )  {
                          if ((isset($value2)) && ($value2<=$startOfDay)) {
                            assert($value1<=$value2, "Start of period is less than its end");
                            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->addDateIntervalToThis(date_diff($value1, $value2));
                            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][4] = $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->myToFloat();
                          } else {
                            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->addDateIntervalToThis(date_diff($value1, $startOfDay));
                            $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][4] = $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->myToFloat();
                          }
                        }
                        
                        if ( (isset($value2))&&($value2 > $endOfDayRedefined) ) {
                          if (isset($value1)) {
                            if ($value1<=$endOfDayRedefined) {
                                $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->addDateIntervalToThis(date_diff($endOfDayRedefined, $value2));
                                $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][4] = $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->myToFloat();
                            } else {
                                $rawResult->{'AllUsers'}[$valueFromStructure->{"BCODE"}]->{'timedarray'}[$indexFound][3]->addDateIntervalToThis(date_diff($value1, $value2));
                            }
                          }
                        }

                    //overtime!
                    
                }
            //}
            
        }
        $prevIndex++;
    }
    //aww, we are done with records (check that there was at least one record), but period is still opened. Period openness is checked at the beginning of iteration at previous cycle , but in this case we do not get to it, so check it here
    //do not involve these calculations if it exceeds end of day
    //seems like that this fragment is not necessary any more. Or not? Hmmmm~
    $fragmentSealed = FALSE;
    if (isset($prevTimeStamp)&&($prevTimeStamp!=NULL)&&($fragmentSealed == FALSE)) {
    $missedDateStr = $prevTimeStamp->format("Y-m-d");
    $endOfDayTimeStamp = DateTime::createFromFormat("Y-m-d H:i:s", $missedDateStr." ".$defaultScheduleToUse['TIMEEND'].":00", new DateTimeZone($in_injectedLocalTimeZone));
    $startOfDayTimeStamp = DateTime::createFromFormat("Y-m-d H:i:s", $missedDateStr." ".$defaultScheduleToUse['TIMESTART'].":00", new DateTimeZone($in_injectedLocalTimeZone));
    $finishedDayInterval = date_diff($prevTimeStamp, new DateTime("now",new DateTimeZone($in_injectedLocalTimeZone)), true);
    if (( $injectedUseSchedule==TRUE ) && ($currentPeriodClosed === FALSE) && ( $finishedDayInterval->d >= 1) && ($endOfDayTimeStamp>$prevTimeStamp)) {
        //close period with autosubstitution on end of day.
        $indexFound = null; $currentIndex = 0;
            foreach ($rawResult->{'AllDates'} as $valueDate) {
                if ( $valueDate[0] == $missedDateStr ) {
                    $indexFound = $currentIndex;
                    break;
                }
                $currentIndex++;
            }
        if ($indexFound === NULL) { throw new OutOfBoundsException("DATE ".$prevTimeStamp->format("Y-m-d H:i:s")." NOT  IN RANGE"); return; }
        $intrvlfinalizedaftercycle = date_diff($endOfDayTimeStamp, $prevTimeStamp, TRUE);
        $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][0]->addDateIntervalToThis($intrvlfinalizedaftercycle);
        $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][1] = $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][0]->myToFloat();
        
        //actually, overtime cause may happen here too. But only for start of day
                        if ( (isset($prevTimeStamp))&&($prevTimeStamp < $startOfDayTimeStamp) )  {
                          $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][3]->addDateIntervalToThis(date_diff($prevTimeStamp, $startOfDayTimeStamp, TRUE));  
                          $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][4] = $rawResult->{'AllUsers'}[$storedBCODE]->{'timedarray'}[$indexFound][3]->myToFloat();
                        }
        //overtime!
        
    }
    }
    return $rawResult;
}
/**
 * used to find index in AllDates by date string in date array of $in_Structure2 used in postcalculateAggregatedDataStructure
 */
function obtainIndexFromStructure($in_inStructure2, $in_dateString) {
    $dateToLookup = explode(" ", $in_dateString->format("Y-m-d"))[0];
    for ($i = 0; $i<count($in_inStructure2->{'AllDates'}); $i++) {
        if ($in_inStructure2->{'AllDates'}[$i][0] == $dateToLookup) {
            return $i;
        }
    }
    
}
/**
 * Since aggregateDataStructure does not calculate break time, utilize a separate subroutine for it
 * it is assumed that this array is sorted
 * @param $in_Structure1 - a structure, sent to aggregateDataStructure. A raw one?
 * @param $in_Structure2 - a structure, obtained from aggregateDataStructure. A prefinal one?
 */
function postcalculateAggregatedDataStructure($in_Structure1, $in_Structure2, DataBaseHandler $in_injectedDBstructure, $in_injectedLocalTimeZone) {
       $structureToReturn = $in_Structure2;
       $defaultScheduleToUse = []; $defaultBreakToUse = []; $heuristicsSubtractBreakTime = TRUE;
       $configurationsOfAlgorithm = $in_injectedDBstructure->getExistingSettings();
       $injectedUseSchedule = $configurationsOfAlgorithm["USESCHEDULE"];
       $injectedLimitByWorkDayTime = $configurationsOfAlgorithm["LIMITBYWORKDAYTIME"];
       $injectedUseSchedule = filter_var($injectedUseSchedule, FILTER_VALIDATE_BOOLEAN);
       $refinedInvolveBreakTime = filter_var($injectedLimitByWorkDayTime, FILTER_VALIDATE_BOOLEAN);
       if ($injectedUseSchedule==TRUE) {
            $defaultScheduleToUse=$in_injectedDBstructure->getDefaultCompanySchedule();
       }
       if ($refinedInvolveBreakTime == TRUE) {
           $defaultBreakToUse = $in_injectedDBstructure->getDefaultCompanyBreak();
       }
       //break time will be set for every date separately
       $detalizedBreak=(object)['breakstart'=>null, 'breakend'=>null, 'breakintrvl'=>null, 'breakintrvlfloat'=>0.0];
    $countOfStructure = count($in_Structure1);
    if ($injectedUseSchedule == FALSE || $refinedInvolveBreakTime == FALSE) { return $in_Structure2; }
    $prevTimeStamp = NULL; $currTimeStamp = NULL; $prevUsrStamp = NULL; $currUsrStamp = NULL;
    // $i slides over $in_Structure1 but $j serves as an internal counter for streak
    $i=0; $j = 0;
    $streakPreviousDate = NULL; //a previous date in a streak
    $subtractBreakTime = false; //should we subtract a break time?
    while ($i < $countOfStructure) { //jester-style iteration over the raw scantime structure
            //happens when no entries available for display
            if (($in_Structure1[$i]->{'SCANDATETIME'} == null)&&($in_Structure1[$i]->{'SCANID'} == null)) { $i++; 
             $j = 0;
            $streakPreviousDate = NULL;
            $subtractBreakTime = false;
            $brassKeyOfDateSwitching = false;
            $bronzeKeyOfUserSwitching = false;
            continue; 
            }
        $currTimeStamp = DateTime::createFromFormat("Y-m-d H:i:s", $in_Structure1[$i]->{'SCANDATETIME'}, new DateTimeZone($in_injectedLocalTimeZone) );
        $currUsrStamp = $in_Structure1[$i]->{'BCODE'};
        // if one of these keys is turned then we need to recalculate break time and end the streak. 
        // $brassKeyOfDateSwitching == true when the current date in scan array is 1 day more than the previous one.
        // $bronzeKeyOfUserSwitching == true when the entity for scan is different then previous one.
        $brassKeyOfDateSwitching = false; $bronzeKeyOfUserSwitching = false;
        if (isset($prevTimeStamp)&&($prevTimeStamp!=NULL) ) {
            //$finishedDayInterval = date_diff($prevTimeStamp, $currTimeStamp, true);
            $prevTimeStampStr = $prevTimeStamp->format('Y-m-d'); $currTimeStampStr = $currTimeStamp->format('Y-m-d');
            if ($prevTimeStampStr != $currTimeStampStr) {
                $brassKeyOfDateSwitching = true;
            }
        }
        if (isset($prevUsrStamp)&&($prevUsrStamp!=NULL) ) {
            if ($prevUsrStamp!=$currUsrStamp) {
                $bronzeKeyOfUserSwitching = true;
            }
        }
        if ($brassKeyOfDateSwitching || ($detalizedBreak->{'breakstart'} == null) ) {
            $detalizedBreak->{'breakstart'} = DateTime::createFromFormat('Y-m-d H:i:s', explode(" ",$in_Structure1[$i]->{"SCANDATETIME"})[0].' '.$defaultBreakToUse["TIMESTART"].':00', new DateTimeZone($in_injectedLocalTimeZone));
            $detalizedBreak->{'breakend'} = DateTime::createFromFormat('Y-m-d H:i:s', explode(" ",$in_Structure1[$i]->{"SCANDATETIME"})[0].' '.$defaultBreakToUse["TIMEEND"].':00', new DateTimeZone($in_injectedLocalTimeZone));
            $detalizedBreak->{'breakintrvl'} = date_diff($detalizedBreak->{'breakend'}, $detalizedBreak->{'breakstart'});
            $detalizedBreak->{'breakintrvlfloat'} = $detalizedBreak->{'breakintrvl'}->d*24.0+$detalizedBreak->{'breakintrvl'}->h+$detalizedBreak->{'breakintrvl'}->i/60;
        }
        //unlock the gate of breaktime subtract resolving. End a streak. Find out whether to subtract break time or not.
        if ($bronzeKeyOfUserSwitching || $brassKeyOfDateSwitching) {
            $UsrTagToUse = NULL; if ($bronzeKeyOfUserSwitching) { $UsrTagToUse = $prevUsrStamp; } else { $UsrTagToUse = $currUsrStamp; }
            $UsrStampToUse = NULL; if ($brassKeyOfDateSwitching) {$UsrStampToUse = $prevTimeStamp; } else { $UsrStampToUse = $currTimeStamp; }
            $j -=1; //compensate it, because it has incremented when we got a user switching
            //if ( isset($streakPreviousDate) ) {
                if ( (isset($streakPreviousDate) == false) || ($streakPreviousDate==NULL)) { //we are ready to recalculate the breaktime. there was only one date during streak
                    if ($currTimeStamp<$detalizedBreak->{'breakstart'}) {
                        $subtractBreakTime = true;
                    }
                } else {
                    if (($j % 2 == 0)&&($streakPreviousDate<$detalizedBreak->{'breakstart'})) {
                        $subtractBreakTime = true;
                    } else {
                        
                    }
                }
            //}
            if ($subtractBreakTime) {
                        $foundindex = obtainIndexFromStructure($in_Structure2,$UsrStampToUse);
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][2] = 1;
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][1] -= $detalizedBreak->{'breakintrvlfloat'} ;
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][0]->subtractDateIntervalToThis($detalizedBreak->{'breakintrvl'});
            }
            $j = 0;
            $streakPreviousDate = NULL;
            $subtractBreakTime = false;
            $brassKeyOfDateSwitching = false;
            $bronzeKeyOfUserSwitching = false;
        } else { //we are not ready for the gate... Continue streak
            if ($j % 2 == 1) { //it is an end of 'scan period' in streak
                if (($prevTimeStamp < $detalizedBreak->{'breakstart'} ) && ($currTimeStamp > $detalizedBreak->{'breakstart'})) {
                    $subtractBreakTime = true;
                }
            }
            $streakPreviousDate = $currTimeStamp;
            $j++;
        }
        if ( (($currTimeStamp<$detalizedBreak->{'breakstart'})&&($i+1 == $countOfStructure)) //pay attention to the last item in array
        || 
        (($currTimeStamp<$detalizedBreak->{'breakstart'})&&($in_Structure1[$i+1]->{'SCANDATETIME'} == null)&&($in_Structure1[$i+1]->{'SCANID'} == null)&&($i+2 == $countOfStructure)) ) //may happen in some rare testcases
        { 
                        $foundindex = obtainIndexFromStructure($in_Structure2,$currTimeStamp);
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][2] = 1;
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][1] -= $detalizedBreak->{'breakintrvlfloat'} ;
                        $structureToReturn->{'AllUsers'}[intval($UsrTagToUse)]->{'timedarray'}[$foundindex][0]->subtractDateIntervalToThis($detalizedBreak->{'breakintrvl'});
        }
        $prevTimeStamp = $currTimeStamp;
        $prevUsrStamp = $currUsrStamp;
        $i++;
    }
    return $structureToReturn;
}
///*********************
//render big formal template 
$app->get('/list/v4[/]', function(Request $request, Response $response, array $args){
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
    
    
} );

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
        $useHtmlRenderer = true; //render to html (set to true) or to xlsx (set to false)
    if (isset($_GET) ) {    //// date and time fiddling
        if (isset ($_GET["out"])&&($_GET["out"]=="xlsx")) {
            $useHtmlRenderer = false;
        }
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
        if ($useHtmlRenderer == true) {
            return $this->view->render($response, "listbarcode2.twig",$templateTransmission);
        } else {
            $debugLine = "<html><head></head><body>XLSX report is currently not implemented for \$preparedCalculateTime == FALSE</body> </html>";
            return $response->withHeader('Content-type', 'text/html')->write($debugLine);
        }
    } else {
            $rawscanTimeValues = $dbInstance->listScanTimesInRange2($sqlitedateStart, $sqlitedateEnd);
            $updatedscanTimeValues = aggregateDataStructure($rawscanTimeValues, date_time_set($time1,00,01), date_time_set($time2,23,59), $this->get('settings')['timezonestring'], $dbInstance );
            //expand data structure with workers with special schedule.
            //$expandedscanTimeValues = expandDataStructure($updatedscanTimeValues);
        $templateTransmission["scanlist"] = $updatedscanTimeValues;
       $updatedscanTimeValues2 = postcalculateAggregatedDataStructure($rawscanTimeValues, $updatedscanTimeValues, $dbInstance, $this->get('settings')['timezonestring']);
        $templateTransmission["scanlist"] = $updatedscanTimeValues2;
        if ($useHtmlRenderer == true) {
        return $this->view->render($response, "listbarcode3.twig",$templateTransmission);
        } else {
            $excelFileName = renderV3asXLSX($templateTransmission,$this->get('settings')['xlsxfolder'], $this->get('settings')['timezonestring']);
            $response = $response->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="'.$excelFileName.'"');
            $stream = fopen($this->get('settings')['xlsxfolder'].'/'.$excelFileName, 'r+');
            return $response->withBody(new \Slim\Http\Stream($stream));
        }
        //$debugLine = "<html><head></head><body>HERE BE EXPANDED TABLE OF REGISTERED ITEMS</body> </html>";
        //return $response->withHeader('Content-type', 'text/html')->write($debugLine);
    }

});
//show scan times with calculated time
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
        $useHtmlRenderer = true; //render to html (set to true) or to xlsx (set to false)
    if (isset($_GET) ) {    //// date and time fiddling. Also renderer.
        if (isset ($_GET["out"])&&($_GET["out"]=="xlsx")) {
            $useHtmlRenderer = false;
        }
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
    if ($useHtmlRenderer == true) {
        if ($preparedCalculateTime == FALSE) {
            $templateTransmission["scanlist"] = $rawscanTimeValues;
            return $this->view->render($response, "listbarcode2.twig",$templateTransmission);
        } else {
            $templateTransmission["scanlist"] = $updatedscanTimeValues;
            return $this->view->render($response, "listbarcode2usetime.twig",$templateTransmission);
            //$debugLine = "<html><head></head><body>HERE BE EXPANDED TABLE OF REGISTERED ITEMS</body> </html>";
            //return $response->withHeader('Content-type', 'text/html')->write($debugLine);
        }
    } else { //render to xlsx and return file path to user
        if ($preparedCalculateTime == FALSE) {
            $templateTransmission["scanlist"] = $rawscanTimeValues;
        } else {
            $templateTransmission["scanlist"] = $updatedscanTimeValues;
        }
       $excelFileName = renderV2asXLSX($templateTransmission,$this->get('settings')['xlsxfolder'], $this->get('settings')['timezonestring']);
        $response = $response->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="'.$excelFileName.'"');

        $stream = fopen($this->get('settings')['xlsxfolder'].'/'.$excelFileName, 'r+');
        
        return $response->withBody(new \Slim\Http\Stream($stream));
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
    
    if (isset($_SESSION['addcustomworktimestatus'])) {
        $templateTransmission['addcustomworktimestatus'] = $_SESSION['addcustomworktimestatus'];
        unset($_SESSION['addcustomworktimestatus']);
    }
    
    $optionsSubarray = $privateLocaleHandler->getLocaleSubArray($templateTransmission["lang"], "page-options");
    $templateTransmission["localizedmessages"] = $commonsubarray+$optionsSubarray;
    
    $defaultScheduleArray = $dbInstance->getDefaultCompanySchedule();
    $defaultBreakArray = $dbInstance->getDefaultCompanyBreak();
    $templateTransmission["defaultschedule"] = $defaultScheduleArray;
    $templateTransmission["defaultbreak"] = $defaultBreakArray;
    //$uwschd = $this->get('settings')['calculateTimeUseSchedule'];
    //$utlwd = $this->get('settings')["calculateTimeLimitedByWorkDay"];
    $commonconfigarray = $dbInstance->getExistingSettings();
    $templateTransmission["commonconfig"] = ["UWSchd"=>filter_var($commonconfigarray["USESCHEDULE"],FILTER_VALIDATE_BOOLEAN), "UTLWrkDay"=>filter_var($commonconfigarray["LIMITBYWORKDAYTIME"],FILTER_VALIDATE_BOOLEAN)];
    $templateTransmission["barcodeentrylistfrm"] = $dbInstance->listAllBarcodes();
    $templateTransmission["customworktimearray"] = $dbInstance->getAllCustomSchedules();
    return $this->view->render($response, "options.twig", $templateTransmission);
});

$app->post('/savecustomworktime[/]', function(Request $request, Response $response, array $args) { 
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = $request->getParsedBody();
    $newObjectWithCustomSchedule = (object)['START_TIME'=>$body['customtimestart'], 'END_TIME'=>$body['customtimeend'], 'ENTITY_ID'=>intval($body['customtimeentity']), 
        'START_TIME_TYPE'=> substr($body['customtimestarttype'], 0, strlen($body['customtimestarttype'])-3), 
        'END_TIME_TYPE'=>substr($body['customtimeendtype'], 0, strlen($body['customtimeendtype'])-3) ];
    $_SESSION['addcustomworktimestatus']=$dbInstance->addNewCustomSchedule($newObjectWithCustomSchedule);
    return $response->withRedirect('/options');
});
$app->post('/removecustomworktime[/]', function(Request $request, Response $response, array $args) { 
    session_start();
    $data = (object)(['status' => '']);
    if (isset($_SESSION["login"]) == FALSE) {
        $data->{'status'} = 'authrequired';
    } else {
    $dbInstance = new DataBaseHandler($this->db);
        if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    $body = $request->getParsedBody();
    try {
    $dbInstance->removeCustomWorkTimeDB( intval($body['bcode']) );
    $data->{'status'} = 'success';
    } catch (Exception $e) {
        $data->{'status'} = 'dbfailure';
    }
    }
      $newResponse = $response;
      $newResponse = $newResponse->withJson($data)->withStatus(200);
    return $newResponse;
});
$app->post('/saveoptions[/]', function(Request $request, Response $response, array $args) {
    session_start();
    $dbInstance = new DataBaseHandler($this->db);
    if ($dbInstance == NULL) {
        return $response->withStatus(502, "DB instance is null. Failed to get PDO instance");
    }
    if (isset($_SESSION["login"]) == FALSE) {
        return $response->withRedirect('/options'); // not authorized, so auth screen is shown
    }
    $body = $request->getParsedBody();
    $arrayToUse = ["timestart"=>$body["timestart"], "timeend"=>$body["timeend"], "dateused"=>"0001-01-02", "timetype"=>0];
    $dbInstance->updateCompanySchedule($arrayToUse);
    $arrayToUse2 = ["timestart"=>$body["timestartbreak"], "timeend"=>$body["timestartbreak"], "dateused"=>"0001-01-02", "timetype"=>1];
    $dbInstance->updateCompanySchedule($arrayToUse2);
    
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
     
    $dbInstance->saveCodeEntry($body->{'newbarcode'}, $subpathToBarcode, $body->{'fldinput1'}, $body->{'fldinput2'}, $body->{'fldinput3'}, $body->{'barcodetype'}, $body->{'fieldposition'}, $body->{'fieldgender'});
    $latestBarcodeID = $dbInstance->getLatestBarcodeAdded();
    $data = array(['status' => 'OK', 'addedfilepath'=>$request->getUri()->getBasePath().$subpathToBarcode, 
        'backtrackdata'=>[ 
            'fldinput1'=>$body->{'fldinput1'}, 'fldinput2'=>$body->{'fldinput2'}, 'fldinput3'=>$body->{'fldinput3'}, 
            'barcodetype'=>$body->{'barcodetype'}, 'newbarcode'=>$body->{'newbarcode'}, 'ID'=>$latestBarcodeID, 'fieldposition'=>$body->{'fieldposition'}, 'fieldgender'=>$body->{'fieldgender'} ] ]);
    
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