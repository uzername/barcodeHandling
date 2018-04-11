<?php
/**
 * DataBaseHandler operates with databases on server
 */
class DataBaseHandler {
    public $scanHistoryTableName = "scanhistory";
    public $existingBarcodesTableName = "registeredbarcodes";
    public $pdoInstance;
    public function __construct($in_pdoInstance) {
        $this->pdoInstance = $in_pdoInstance;
        //$this->dependentLoggerInstance = null;
        
        $this->reCreateDataStructure();
    }
    public function reCreateDataStructure() {
        $commandList = [
            'Create table if not exists '.$this->existingBarcodesTableName.
            '(ID INTEGER PRIMARY KEY, PATHTOBARCODE TEXT, RAWBARCODEREGISTERED TEXT)',
            
            'Create table if not exists '.$this->scanHistoryTableName.
            '(ID INTEGER PRIMARY KEY, KNOWNBARCODE_ID INTEGER, RAWBARCODE TEXT, SCANDATETIME TEXT, FOREIGN KEY(KNOWNBARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) )'
        ];
        foreach ($commandList as $command) {
            $this->pdoInstance->exec($command);
        }
    }
    public function saveScanTime($inSavedBarcodeText, $inSavedTime) {
        $stmt = $this->pdoInstance->prepare("Insert INTO ".$this->scanHistoryTableName."(RAWBARCODE, SCANDATETIME) VALUES (:rawtext, :rawtime)");
        $stmt->bindParam(":rawtext", $inSavedBarcodeText, PDO::PARAM_STR);
        $stmt->bindParam(":rawtime", $inSavedTime, PDO::PARAM_STR);
        $stmt->execute();
    }
    public function listAllScanTime() {
        $stmt = $this->pdoInstance->query("SELECT * FROM ".$this->scanHistoryTableName);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['ID'=>$row['ID'], 'KNOWNBARCODE_ID'=>$row['KNOWNBARCODE_ID'], 'RAWBARCODE'=>$row['RAWBARCODE'], 'SCANDATETIME'=>$row['SCANDATETIME'] ];
            
        }
        return $allScan;
    }
    public function listAllBarcodes() {
        $stmt = $this->pdoInstance->query("SELECT * FROM ".$this->existingBarcodesTableName);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['ID'=>$row['ID'], 'PATHTOBARCODE'=>$row['PATHTOBARCODE'], 'RAWBARCODEREGISTERED'=>$row['RAWBARCODEREGISTERED'] ];
            
        }
        return $allScan;
    }
    public function saveCodeEntry($in_data, $in_pathToBarcode) {
        $stmt = $this->pdoInstance->prepare("Insert INTO ".$this->existingBarcodesTableName."(PATHTOBARCODE, RAWBARCODEREGISTERED) VALUES (:path, :rawcode)");
        $stmt->bindParam(":path", $in_pathToBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":rawcode", $in_data, PDO::PARAM_STR);
        $stmt->execute();
    }
}