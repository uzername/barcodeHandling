<?php
$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['sqlite']['pathtofile'] = './data/simplebase.db';
$config['logfilepath'] = './logs/restapi.log';
//special feature configuration
$config['restrictAccessSpecial']=TRUE; //apply restriction access policy
$config['calculateTime']=TRUE; //calculate timespans? If it is false then we'll just display a table with registered values 'as-is'
$config['timezonestring'] = 'Europe/Kiev';
// here are default values for params. Better store these in database
/*
$config['calculateTimeUseSchedule'] = TRUE; //company starts working at 8:00, finishes at 16:30... Should we use this feature?
$config['calculateTimeLimitedByWorkDay'] = FALSE;
*/
?>