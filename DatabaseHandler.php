<?php
/**
 * DataBaseHandler operates with databases on server
 */
class DataBaseHandler {
    public $scanHistoryTableName = "scanhistory";
    public $existingBarcodesTableName = "registeredbarcodes";
    public $accessRolesTableName = "accessroles";
    public $pdoInstance;
    public function __construct($in_pdoInstance) {
        $this->pdoInstance = $in_pdoInstance;
        //$this->dependentLoggerInstance = null;
        
        $this->reCreateDataStructure();
    }
    public function reCreateDataStructure() {
        $commandList = [
            'Create table if not exists '.$this->existingBarcodesTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, PATHTOBARCODE TEXT, RAWBARCODEREGISTERED TEXT NOT NULL UNIQUE, FIELD1 TEXT, FIELD2 TEXT, FIELD3 TEXT, BARCODETYPE VARCHAR(10) )',
            
            'Create table if not exists '.$this->scanHistoryTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, KNOWNBARCODE_ID INTEGER, RAWBARCODE TEXT, SCANDATETIME TEXT, FOREIGN KEY(KNOWNBARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) ON DELETE CASCADE ON UPDATE CASCADE )',
            
            'Create table if not exists '.$this->accessRolesTableName.
            '(ACCESSID INTEGER PRIMARY KEY AUTOINCREMENT, ACCESSROLE TEXT NOT NULL UNIQUE, LANGUAGE VARCHAR(3))'
        ];
        foreach ($commandList as $command) {
            $this->pdoInstance->exec($command);
        }
        //predefined access roles
        $predefinedAccessItems = [["accountant","en"], ["engineer","en"], ["бухгалтер","ru"], ["инженер","ru"]];
        $predefinedAccessItemsCount = count($predefinedAccessItems);
            $epicQuery = "SELECT DISTINCT ACCESSROLE FROM ".$this->accessRolesTableName." GROUP BY ACCESSROLE";
            $stmt = $this->pdoInstance->prepare($epicQuery);
            $stmt->execute();
            $availableAccessRole=[];
            while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
                $availableAccessRole[]=$row['ACCESSROLE'];
            }    
            foreach ( $predefinedAccessItems as $valuePredefined ) {
                $itmaccessfound = FALSE;
                foreach ($availableAccessRole as $valueFromQuery) {
                    if ($valuePredefined[0]==$valueFromQuery) {
                        $itmaccessfound = TRUE;
                        break;
                    }
                }
                if ($itmaccessfound == FALSE) { //add access item to table
                $stmt2 = $this->pdoInstance->prepare("INSERT INTO ".$this->accessRolesTableName."(ACCESSROLE, LANGUAGE) VALUES (:accessrole, :lang)");
                $stmt2->bindParam(":accessrole", $valuePredefined[0], PDO::PARAM_STR);
                $stmt2->bindParam(":lang", $valuePredefined[1], PDO::PARAM_STR);
                $stmt2->execute();
                }
            }      
    }
    
    public function validateAccessRole($inAccessRole) {
        $stmt2 = $this->pdoInstance->prepare("SELECT COUNT(ACCESSID) as FOUND FROM ".$this->accessRolesTableName." WHERE ACCESSROLE = :accessrole");
        $stmt2->bindParam(":accessrole", $inAccessRole, PDO::PARAM_STR);
        $stmt2->execute();
        while ($row=$stmt2->fetch(\PDO::FETCH_ASSOC)) {
            return $row['FOUND'];
        }
    }
    
    //it is possible to combine the following routine to a single one. In this case in saveScanTime parameter $inKnownBarcodeID is removed and query is rewritten as follows: https://stackoverflow.com/a/21152791
    public function obtainKnownBarcodeIDByText($inRawText) {
        $stmt = $this->pdoInstance->prepare("SELECT ID FROM registeredbarcodes WHERE RAWBARCODEREGISTERED = :rawtext");
        $stmt->bindParam(":rawtext", $inRawText, PDO::PARAM_STR);
        $stmt->execute();
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $row["ID"];
        }
    }
    //sqlite saves datetime in format of YYYY-MM-DD. $inSavedTime is time a string formatted that way
    public function saveScanTime($inSavedBarcodeText, $inKnownBarcodeID, $inSavedTime) {
        $stmt = $this->pdoInstance->prepare("Insert INTO ".$this->scanHistoryTableName."(RAWBARCODE, SCANDATETIME, KNOWNBARCODE_ID) VALUES (:rawtext, :rawtime, :rawknownID)");
        $stmt->bindParam(":rawtext", $inSavedBarcodeText, PDO::PARAM_STR);
        $stmt->bindParam(":rawtime", $inSavedTime, PDO::PARAM_STR);
        $stmt->bindParam(":rawknownID", $inKnownBarcodeID, PDO::PARAM_STR);
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
    //sqlite saves datetime in format of YYYY-MM-DD. $in_dateTimeStart and $in_dateTimeEnd are time strings formatted that way
    //https://stackoverflow.com/a/8187455/
    public function listScanTimesInRange($in_dateTimeStart, $in_dateTimeEnd) {
        $stmt = $this->pdoInstance->prepare("SELECT ".$this->scanHistoryTableName.".ID, SCANDATETIME, KNOWNBARCODE_ID, RAWBARCODE, FIELD1, FIELD2, FIELD3 FROM ".$this->scanHistoryTableName." INNER JOIN ".$this->existingBarcodesTableName.
                " ON (".$this->scanHistoryTableName.".KNOWNBARCODE_ID = ".$this->existingBarcodesTableName.".ID) WHERE (SCANDATETIME BETWEEN :val1 AND :val2 )");
        $stmt->bindParam(":val1", $in_dateTimeStart);
        $stmt->bindParam(":val2", $in_dateTimeEnd);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['ID'=>$row['ID'], 'KNOWNBARCODE_ID'=>$row['KNOWNBARCODE_ID'], 'RAWBARCODE'=>$row['RAWBARCODE'], 'SCANDATETIME'=>$row['SCANDATETIME'],
                "FIELD1"=>$row["FIELD1"], "FIELD2"=>$row["FIELD2"], "FIELD3"=>$row["FIELD3"] ];            
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
    /**
     * remove barcodes identified by list of IDs
     * @param type $in_barcodeListID
     */
    public function removeSavedBarcodes($in_barcodeListID) {
        $in  = str_repeat('?,', count($in_barcodeListID) - 1) . '?';
        $sqlstatement = "DELETE FROM ".$this->existingBarcodesTableName." WHERE ID IN ($in)";
        $stmt = $this->pdoInstance->prepare( $sqlstatement );
        $stmt = $this->pdoInstance->prepare( $sqlstatement );
        $stmt->execute($in_barcodeListID);
    }
    
    public function updateSingleBarcode($in_barcodeObject, $newPathToBarcode) {
        $usedID = intval($in_barcodeObject->{"ID"});
        $usedRawBarcode = $in_barcodeObject->{"rawbarcode"};
        $usedField1 = $in_barcodeObject->{"field1"};
        $usedField2 = $in_barcodeObject->{"field2"};
        $usedField3 = $in_barcodeObject->{"field3"};
        $stmt = $this->pdoInstance->prepare("UPDATE ".$this->existingBarcodesTableName." SET PATHTOBARCODE=:usepath, RAWBARCODEREGISTERED=:usebarcode, FIELD1=:fld1, FIELD2=:fld2, FIELD3=:fld3 WHERE ID=:useID");
        $stmt->bindParam(":usepath", $newPathToBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":usebarcode", $usedRawBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":fld1", $usedField1, PDO::PARAM_STR);
        $stmt->bindParam(":fld2", $usedField2, PDO::PARAM_STR);
        $stmt->bindParam(":fld3", $usedField3, PDO::PARAM_STR);
        $stmt->bindParam(":useID", $usedID, PDO::PARAM_INT);
        $stmt->execute();
        
    }
    
    public function getSingleBarcodeTypeAndPathByID($in_barcodeID) {
        $objectToReturn = [];
        $usedID = intval($in_barcodeID);
        $stmt=$this->pdoInstance->prepare("SELECT BARCODETYPE, PATHTOBARCODE FROM ".$this->existingBarcodesTableName." WHERE ID=:usedID");
        $stmt->bindParam(":usedID", $usedID, PDO::PARAM_INT);
        $stmt->execute();
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $objectToReturn= (object)["BARCODETYPE" => $row["BARCODETYPE"], "PATHTOBARCODE" => $row["PATHTOBARCODE"]];
            return $objectToReturn;
        }
    }
}