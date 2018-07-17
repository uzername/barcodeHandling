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
$sheet->setCellValue('A1', 'Hello World !');
$datesInRow = 5; //number of dates in a single row. $datesInRow indicates a size of Tier
$currentRow = 2;
foreach ($in_preparedStructureForV2["scanlist"] as $valueScanlist) { //iterate over all persons
    $spreadsheet->getActiveSheet()->mergeCells("A$currentRow:K$currentRow");
    $sheet->setCellValue("A$currentRow", $valueScanlist->{'tableheader'});
    $currentRowMaximum = 1; //how many rows does a list of scans require?
    $currentRowMaximum_Tier = 0;
    $currentIterCntr = 0; $currentIterTier = 0; $currentTierXLSXcntr = 'B'; /*that is a counter*/
    
    foreach ($valueScanlist->{"timedarray"} as $timedArrayKey => $timedArrayEntity) { //setting up dates        
        if ($currentIterCntr % $datesInRow == 0) { //tier switching?
            $currentIterTier+=1;
            $currentRow+=1;
            $currentTierXLSXcntr = 'B'; 
        }
        $currentTierXLSXcntrnext = getPHPIncrementedXLSIndex($currentTierXLSXcntr);
        $spreadsheet->getActiveSheet()->mergeCells("$currentTierXLSXcntr$currentRow:".$currentTierXLSXcntrnext."$currentRow");
        $sheet->setCellValue("$currentTierXLSXcntr$currentRow", $timedArrayKey);
        $localRowIter = $currentRow+1; $localArrIter = 0;
        foreach ( $timedArrayEntity->{'timelist'} as $singleScanTime) {
            if ($localArrIter % 2 == 0) {
                $sheet->setCellValue("$currentTierXLSXcntr$localRowIter", $singleScanTime);
            } else {
                $currentTierXLSXcntrnext = getPHPIncrementedXLSIndex($currentTierXLSXcntr);
                $sheet->setCellValue("$currentTierXLSXcntrnext$localRowIter", $singleScanTime);
                $localRowIter+=1;
            }
            $localArrIter+=1;
        }
        $currentTierXLSXcntr = getPHPIncrementedXLSIndex($currentTierXLSXcntrnext);
        $currentIterCntr += 1;
    }
}
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
