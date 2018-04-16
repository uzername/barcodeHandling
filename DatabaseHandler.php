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
            //TODO: store barcode type to table
            'Create table if not exists '.$this->existingBarcodesTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, PATHTOBARCODE TEXT, RAWBARCODEREGISTERED TEXT NOT NULL UNIQUE, FIELD1 TEXT, FIELD2 TEXT, FIELD3 TEXT, BARCODETYPE VARCHAR(10) )',
            
            'Create table if not exists '.$this->scanHistoryTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, KNOWNBARCODE_ID INTEGER, RAWBARCODE TEXT, SCANDATETIME TEXT, FOREIGN KEY(KNOWNBARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) )'
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
            $allScan[] = (object)['ID'=>$row['ID'], 'PATHTOBARCODE'=>$row['PATHTOBARCODE'], 'RAWBARCODEREGISTERED'=>$row['RAWBARCODEREGISTERED'],
                'FIELD1'=>$row['FIELD1'], 'FIELD2'=>$row['FIELD2'], 'FIELD3'=>$row['FIELD3'], 'BARCODETYPE'=>$row['BARCODETYPE'] ];
            
        }
        return $allScan;
    }
    public function saveCodeEntry($in_data, $in_pathToBarcode, $in_field1, $in_field2, $in_field3, $in_barcodetype) {
        $stmt = $this->pdoInstance->prepare("Insert INTO ".$this->existingBarcodesTableName."(PATHTOBARCODE, RAWBARCODEREGISTERED, FIELD1, FIELD2, FIELD3, BARCODETYPE) VALUES (:path, :rawcode, :field1, :field2, :field3, :barcodetype)");
        $stmt->bindParam(":path", $in_pathToBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":rawcode", $in_data, PDO::PARAM_STR);
        $stmt->bindParam(":field1", $in_field1, PDO::PARAM_STR);
        $stmt->bindParam(":field2", $in_field2, PDO::PARAM_STR);
        $stmt->bindParam(":field3", $in_field3, PDO::PARAM_STR);
        $stmt->bindParam(":barcodetype", $in_barcodetype, PDO::PARAM_STR);
        
        $stmt->execute();
    }
    public function getLatestBarcodeAdded() {
        $stmt = $this->pdoInstance->prepare("Select seq from sqlite_sequence where name =\"".$this->existingBarcodesTableName."\" ");
        $stmt->execute();
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $row['seq'];
        }
    }
    public function listAllSelectedBarcodes($in_BarcodesList) {
        // https://phpdelusions.net/pdo#in
        $arr = $in_BarcodesList;
        $in  = str_repeat('?,', count($arr) - 1) . '?';
        $sql = "SELECT * FROM ".$this->existingBarcodesTableName." WHERE ID IN ($in)";
        $stmt = $this->pdoInstance->prepare($sql);
        $stmt->execute($arr);
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['ID'=>$row['ID'], 'PATHTOBARCODE'=>$row['PATHTOBARCODE'], 'RAWBARCODEREGISTERED'=>$row['RAWBARCODEREGISTERED'],
                'FIELD1'=>$row['FIELD1'], 'FIELD2'=>$row['FIELD2'], 'FIELD3'=>$row['FIELD3'], 'BARCODETYPE'=>$row['BARCODETYPE'] ];
            
        }
        return $allScan;
    }
    public function checkExistenceBarcodeByData($in_InputBarcodeData) {
        $stmt = $this->pdoInstance->prepare("SELECT COUNT(*) AS TOTALITEMS FROM ".$this->existingBarcodesTableName." WHERE (RAWBARCODEREGISTERED = :itm)");
        $stmt->bindParam(":itm",$in_InputBarcodeData,PDO::PARAM_STR);
        $stmt->execute();
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $row["TOTALITEMS"];
        }
    }
}