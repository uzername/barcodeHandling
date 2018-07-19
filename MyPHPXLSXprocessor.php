<?php
// https://gist.github.com/odan/a7a1eb3c876c9c5b2ffd2db55f29fdb8
// https://phpspreadsheet.readthedocs.io/en/develop/topics/recipes/
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// check existence of xlsx folder
function checkExistenceOfXLSXFolderAndRecreateIt($in_folderToSaveXLSX) {
      // Get canonicalized absolute pathname
    $path = realpath($in_folderToSaveXLSX);

    // If it exist, check if it's a directory
    if($path !== false AND is_dir($path))
    {
        // Return canonicalized absolute pathname
        return $path;
    } else { // Path/folder does not exist
        mkdir($in_folderToSaveXLSX, 0777);
        $path = realpath($in_folderToSaveXLSX);
        return $path;
    }

}
function getPHPIncrementedXLSIndex($in_str) {
    $out_str = $in_str;
    ++$out_str;
    return $out_str;
}
function renderV2asXLSX($in_preparedStructureForV2, $in_folderToSaveXLSX,$in_timezonestr) {
    $pathToSave = checkExistenceOfXLSXFolderAndRecreateIt($in_folderToSaveXLSX);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$customHeaderTable=$in_preparedStructureForV2["localizedmessages"]["scannedlist2"]."( ".$in_preparedStructureForV2["datetime"]["from"]." - ".$in_preparedStructureForV2["datetime"]["to"]." )";
$sheet->setCellValue('A1', $customHeaderTable);
$datesInRow = 5; //number of dates in a single row. $datesInRow indicates a size of Tier
$currentRow = 2;
foreach ($in_preparedStructureForV2["scanlist"] as $valueScanlist) { //iterate over all persons
    $spreadsheet->getActiveSheet()->mergeCells("A$currentRow:K$currentRow");
    $sheet->setCellValue("A$currentRow", $valueScanlist->{'tableheader'});
    $currentRowMaximum = 1; //how many rows does a list of scans require?
    $currentRowMaximum_Tier = 0;
    $currentIterCntr = 0; $currentIterTier = 0; $currentTierXLSXcntr = 'B'; /*that is a counter*/
    $currentTierKeys = [];
    foreach ($valueScanlist->{"timedarray"} as $timedArrayKey => $timedArrayEntity) { //setting up dates        
        if ($currentIterCntr % $datesInRow == 0) { //tier switching?
            $currentIterTier+=1;
            $currentRow+=1;
            $currentRowMaximum_Tier = $currentRow;
            $currentTierXLSXcntr = 'B'; 
            $currentTierKeys = [];
        }
        $currentTierKeys[] = $timedArrayKey;
        $currentTierXLSXcntrnext = getPHPIncrementedXLSIndex($currentTierXLSXcntr);
        $spreadsheet->getActiveSheet()->mergeCells("$currentTierXLSXcntr$currentRow:".$currentTierXLSXcntrnext."$currentRow");
        $sheet->setCellValue("$currentTierXLSXcntr$currentRow", $timedArrayKey);
        $localRowIter = $currentRow+1; $localArrIter = 0;
        foreach ( $timedArrayEntity->{'timelist'} as $singleScanTime) { //that is just a render for a single date
            if ($localArrIter % 2 == 0) {
                $sheet->setCellValue("$currentTierXLSXcntr$localRowIter", $singleScanTime);
            } else {
                $currentTierXLSXcntrnext = getPHPIncrementedXLSIndex($currentTierXLSXcntr);
                $sheet->setCellValue("$currentTierXLSXcntrnext$localRowIter", $singleScanTime);
                $localRowIter+=1;
            }
            $localArrIter+=1;
        }
        if ($localRowIter>$currentRowMaximum_Tier) {
            $currentRowMaximum_Tier = $localRowIter;
        }
        $currentTierXLSXcntr = getPHPIncrementedXLSIndex($currentTierXLSXcntrnext);
        $currentIterCntr += 1;
        if (($currentIterCntr % $datesInRow == 0)||($currentIterCntr == count($valueScanlist->{"timedarray"}) )) { 
           //we are almost ready for tier switching or for end of operation, just render subtotal values with a propr offset
           $currentTierXLSXcntr2 = 'B';
           //$currentRowMaximum_Tier +=1;
           $currentRowMaximum_TierNextOne = $currentRowMaximum_Tier+1;
           $sheet->setCellValue("A$currentRowMaximum_Tier", $in_preparedStructureForV2["localizedmessages"]["scandatetime_header"]);
           $sheet->setCellValue("A$currentRowMaximum_TierNextOne", $in_preparedStructureForV2["localizedmessages"]["overtimetext"]);
           foreach ($currentTierKeys as $currentTierKeysItem) {
               $currentTierXLSXcntrnext2 = getPHPIncrementedXLSIndex($currentTierXLSXcntr2);
               $spreadsheet->getActiveSheet()->mergeCells("$currentTierXLSXcntr2$currentRowMaximum_Tier:".$currentTierXLSXcntrnext2."$currentRowMaximum_Tier");
               $sheet->setCellValue("$currentTierXLSXcntr2$currentRowMaximum_Tier", $valueScanlist->{"timedarray"}[$currentTierKeysItem]->{"subtotaltime"});
               
               $spreadsheet->getActiveSheet()->mergeCells("$currentTierXLSXcntr2$currentRowMaximum_TierNextOne:".$currentTierXLSXcntrnext2."$currentRowMaximum_TierNextOne");
               $sheet->setCellValue("$currentTierXLSXcntr2$currentRowMaximum_TierNextOne", $valueScanlist->{"timedarray"}[$currentTierKeysItem]->{"subtotalovertime"});
               
               $currentTierXLSXcntr2 = getPHPIncrementedXLSIndex($currentTierXLSXcntrnext2);
               
           }
           $currentRow = $currentRowMaximum_Tier+2;
        }
    }
}
$currentRow -=1;
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getVertical()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$spreadsheet->getActiveSheet()->getStyle("A2:K$currentRow")->getBorders()
    ->getHorizontal()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
// Rename worksheet
$spreadsheet->getActiveSheet()->setTitle('V2 Form');
$spreadsheet->getProperties()->setCreator("SomeName")
    ->setTitle("XLSX Form for V2 report")
    ->setSubject("XLSX Form for V2 report")
    ->setDescription(
        "XLSX Form for V2 report in date range: [".$in_preparedStructureForV2["datetime"]["from"].";".$in_preparedStructureForV2["datetime"]["to"]."]"
    );
$spreadsheet->getActiveSheet()->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$spreadsheet->getActiveSheet()->getPageSetup()
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
$filepath = ( new DateTime("now", new DateTimeZone($in_timezonestr)) )->format("dmY_his")."_v2.xlsx";
$writer->save($pathToSave.'/'.$filepath);
return $filepath;
}

function renderV3asXLSX($in_preparedStructureForV3, $in_folderToSaveXLSX,$in_timezonestr) {
        $pathToSave = checkExistenceOfXLSXFolderAndRecreateIt($in_folderToSaveXLSX);
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
        $customHeaderTable=$in_preparedStructureForV3["localizedmessages"]["listv3timesheet"]."( ".$in_preparedStructureForV3["datetime"]["from"]." - ".$in_preparedStructureForV3["datetime"]["to"]." )";
$sheet->setCellValue('A1', $customHeaderTable);
//here is a report
    //let's construct a table header from AllDates portion of table.
    //A header always consists of rows 16 entries. At first row only first 15 entries are filled, the last one is X
    //next rows are filled normally, utilizing all 16
    $totalRowsInDatesHeader = round( ceil((count($in_preparedStructureForV3["scanlist"]->{"AllDates"})+1)/16.0) );
    $rowsHeader = 6;
    if ($totalRowsInDatesHeader > 3) { //expand 'right' part of header vertically
      $rowsHeader += $totalRowsInDatesHeader-2;
    } 
    $spreadsheet->getActiveSheet()->mergeCells("E3:T3");
    $spreadsheet->getActiveSheet()->getStyle('E3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("E3", $in_preparedStructureForV3["localizedmessages"]["listv3headeraboutdates"]);
        $spreadsheet->getActiveSheet()->mergeCells("U3:Z3");
    $spreadsheet->getActiveSheet()->getStyle('U3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("U3", $in_preparedStructureForV3["localizedmessages"]["listv3headerformonth"]);    
        $spreadsheet->getActiveSheet()->mergeCells("V4:Z4");
    $spreadsheet->getActiveSheet()->getStyle('V4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("V4", $in_preparedStructureForV3["localizedmessages"]["listv3headerhoursformonth"]);
        $spreadsheet->getActiveSheet()->mergeCells("W5:Z5");
    $spreadsheet->getActiveSheet()->getStyle('W5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("W5", $in_preparedStructureForV3["localizedmessages"]["listv3headerdetailed"]);
        $spreadsheet->getActiveSheet()->mergeCells("U4:U$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('U4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("U4", $in_preparedStructureForV3["localizedmessages"]["listv3headerdaysformonth"]);
         $spreadsheet->getActiveSheet()->mergeCells("V5:V$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('V5')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("V5", $in_preparedStructureForV3["localizedmessages"]["listv3headertotal"]);
    if ($rowsHeader>6) {
         $spreadsheet->getActiveSheet()->mergeCells("W6:W$rowsHeader");
         $spreadsheet->getActiveSheet()->mergeCells("X6:X$rowsHeader");
         $spreadsheet->getActiveSheet()->mergeCells("Y6:Y$rowsHeader");
         $spreadsheet->getActiveSheet()->mergeCells("Z6:Z$rowsHeader");
    }
    $spreadsheet->getActiveSheet()->getStyle('W6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("W6", $in_preparedStructureForV3["localizedmessages"]["listv3headerovertime"]);
    $spreadsheet->getActiveSheet()->getStyle('X6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("X6", $in_preparedStructureForV3["localizedmessages"]["listv3headernight"]);
    $spreadsheet->getActiveSheet()->getStyle('Y6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("Y6", $in_preparedStructureForV3["localizedmessages"]["listv3headerevening"]);
    $spreadsheet->getActiveSheet()->getStyle('Z6')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue("Z6", $in_preparedStructureForV3["localizedmessages"]["listv3headerholiday"]);
    $spreadsheet->getActiveSheet()->mergeCells("A3:A$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $spreadsheet->getActiveSheet()->getStyle('A3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->setCellValue("A3", $in_preparedStructureForV3["localizedmessages"]["listv3headernumber"]);
    $spreadsheet->getActiveSheet()->mergeCells("B3:B$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('B3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $spreadsheet->getActiveSheet()->getStyle('B3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->setCellValue("B3", $in_preparedStructureForV3["localizedmessages"]["listv3headerposition"]);
    $spreadsheet->getActiveSheet()->mergeCells("C3:C$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('C3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $spreadsheet->getActiveSheet()->getStyle('C3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->setCellValue("C3", $in_preparedStructureForV3["localizedmessages"]["listv3headergender"]);
    $spreadsheet->getActiveSheet()->mergeCells("D3:D$rowsHeader");
    $spreadsheet->getActiveSheet()->getStyle('D3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $spreadsheet->getActiveSheet()->getStyle('D3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $sheet->setCellValue("D3", $in_preparedStructureForV3["localizedmessages"]["listv3headername"]);
    
    $spreadsheet->getActiveSheet()->getStyle("A3:Z$rowsHeader")->getAlignment()->setWrapText(true);
    $spreadsheet->getActiveSheet()->getStyle("A3:Z$rowsHeader")->getFont()->setName("Times New Roman");
    $spreadsheet->getActiveSheet()->getStyle("A3:Z$rowsHeader")->getFont()->setSize(8.0);
    $currentGlobalRowInDatesHeader=4; $currentGlobalColumnInDatesHeader='D'; $currentIndexInAllDates = 0; $dummyDateSymbol = 'X';
    //foreach ( $in_preparedStructureForV3["scanlist"]->{"AllDates"} as $singleDateFromAll) {
    $cnt1Dates = count($in_preparedStructureForV3["scanlist"]->{"AllDates"});
    while ($currentIndexInAllDates<$cnt1Dates) {
        if ($currentGlobalColumnInDatesHeader=='T') {
            $currentGlobalColumnInDatesHeader = 'E';
            $currentGlobalRowInDatesHeader++;
        } else {
            $currentGlobalColumnInDatesHeader = getPHPIncrementedXLSIndex($currentGlobalColumnInDatesHeader);
        }
        
        $singleDateFromAll = $in_preparedStructureForV3["scanlist"]->{"AllDates"} [$currentIndexInAllDates];
        $dateSymbolToHold = '_';
        if (($currentGlobalColumnInDatesHeader == 'T')&&($currentIndexInAllDates==15)) {
            $dateSymbolToHold = $dummyDateSymbol;
            $currentIndexInAllDates-=1;
        } else {
            $dateSymbolToHold =intval( explode('-', $singleDateFromAll[0])[2] );
        }
        if (($totalRowsInDatesHeader <= 2)&&($currentGlobalRowInDatesHeader == 5)) {
            $spreadsheet->getActiveSheet()->mergeCells("$currentGlobalColumnInDatesHeader$currentGlobalRowInDatesHeader:$currentGlobalColumnInDatesHeader$rowsHeader");
        }
        $sheet->setCellValue("$currentGlobalColumnInDatesHeader$currentGlobalRowInDatesHeader", $dateSymbolToHold);
        $currentIndexInAllDates++;
    }
    $currentGlobalColumnInDatesHeader = getPHPIncrementedXLSIndex($currentGlobalColumnInDatesHeader);
    while ($currentGlobalColumnInDatesHeader!='U'){
        $sheet->setCellValue("$currentGlobalColumnInDatesHeader$currentGlobalRowInDatesHeader", $dummyDateSymbol);
        $currentGlobalColumnInDatesHeader = getPHPIncrementedXLSIndex($currentGlobalColumnInDatesHeader);
    }
    $spreadsheet->getActiveSheet()->getStyle("E3:T$currentGlobalRowInDatesHeader")->getNumberFormat()->setFormatCode('00'); // will show as number with leading 0
    //move on with buddies
    $nomerpp=0;
    $currentGlobalRowInReport = $currentGlobalRowInDatesHeader+1; $initialGlobalRowInReport = $currentGlobalRowInReport;
    foreach ($in_preparedStructureForV3["scanlist"]->{"AllUsers"} as $valueBuddy) {
        $nomerpp++;
        $currentLimitForGlobalRow = $currentGlobalRowInReport+$totalRowsInDatesHeader*2-1;
        $spreadsheet->getActiveSheet()->mergeCells("A$currentGlobalRowInReport:A$currentLimitForGlobalRow");
        $sheet->setCellValue("A$currentGlobalRowInReport", $nomerpp);
        $spreadsheet->getActiveSheet()->mergeCells("B$currentGlobalRowInReport:B$currentLimitForGlobalRow");
        $sheet->setCellValue("B$currentGlobalRowInReport", $valueBuddy->{'xlsx'}->{'position'});
        $spreadsheet->getActiveSheet()->mergeCells("C$currentGlobalRowInReport:C$currentLimitForGlobalRow");
        $sheet->setCellValue("C$currentGlobalRowInReport", $valueBuddy->{'xlsx'}->{'gender'});
        $spreadsheet->getActiveSheet()->mergeCells("D$currentGlobalRowInReport:D$currentLimitForGlobalRow");
        $sheet->setCellValue("D$currentGlobalRowInReport", explode('[',$valueBuddy->{'display'})[0] );
        
        $currentGlobalRowInDatesIterator=$currentGlobalRowInReport; $currentGlobalColumnInDatesIterator='D'; $currentIndexInAllDates = 0;
        while ($currentIndexInAllDates<$cnt1Dates) {
            if ($currentGlobalColumnInDatesIterator=='T') {
                $currentGlobalColumnInDatesIterator = 'E';
                $currentGlobalRowInDatesIterator+=2;
            } else {
                $currentGlobalColumnInDatesIterator = getPHPIncrementedXLSIndex($currentGlobalColumnInDatesIterator);
            }
            if (($currentGlobalColumnInDatesIterator == 'T')&&($currentIndexInAllDates==15)) {
                $sheet->setCellValue("$currentGlobalColumnInDatesIterator$currentGlobalRowInDatesIterator", 0 );
                $currentIndexInAllDates-=1;
            } else {
            
                if (isset($valueBuddy->{'timedarray'}[$currentIndexInAllDates])) {
                    $sheet->setCellValue("$currentGlobalColumnInDatesIterator$currentGlobalRowInDatesIterator", $valueBuddy->{'timedarray'}[$currentIndexInAllDates][1] );
                } else {
                    $sheet->setCellValue("$currentGlobalColumnInDatesIterator$currentGlobalRowInDatesIterator", 0 );
                }
            
            }
            $currentIndexInAllDates++;
        }
        $currentGlobalRowInReport = $currentLimitForGlobalRow+1;
    }
    $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(20);
    $spreadsheet->getActiveSheet()->getStyle("D$initialGlobalRowInReport:D$currentGlobalRowInReport")->getAlignment()->setWrapText(true);
    $spreadsheet->getActiveSheet()->getStyle("D$initialGlobalRowInReport:D$currentGlobalRowInReport")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    $spreadsheet->getActiveSheet()->getStyle("D$initialGlobalRowInReport:D$currentGlobalRowInReport")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
//main report code ends here
$spreadsheet->getActiveSheet()->setTitle('V3 Form');
$spreadsheet->getProperties()->setCreator("SomeName")
    ->setTitle("XLSX Form for V3 report")
    ->setSubject("XLSX Form for V3 report")
    ->setDescription(
        "XLSX Form for V3 report in date range: [".$in_preparedStructureForV3["datetime"]["from"].";".$in_preparedStructureForV3["datetime"]["to"]."]"
    );
$spreadsheet->getActiveSheet()->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$spreadsheet->getActiveSheet()->getPageSetup()
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
$filepath = ( new DateTime("now", new DateTimeZone($in_timezonestr)) )->format("dmY_His")."_v3table.xlsx";
$writer->save($pathToSave.'/'.$filepath);
return $filepath;

}

?>
