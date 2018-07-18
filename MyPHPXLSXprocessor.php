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
?>
