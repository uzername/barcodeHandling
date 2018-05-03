<?php
/**
 * DataBaseHandler operates with databases on server
 */
class DataBaseHandler {
    public $scanHistoryTableName = "scanhistory";
    public $existingBarcodesTableName = "registeredbarcodes";
    public $accessRolesTableName = "accessroles";
    public $companyWorkTimeTableName = "companyworktime";
    public $settingsTableName = "assortedsettings";
    public $pdoInstance;
    public function __construct($in_pdoInstance) {
        $this->pdoInstance = $in_pdoInstance;
        //$this->dependentLoggerInstance = null;
        
        $this->reCreateDataStructure();
    }
    //https://www.sqlite.org/datatype3.html#date_and_time_datatype
    //SQLite does not have a storage class set aside for storing dates and/or times. Instead, the built-in Date And Time Functions of SQLite are capable of storing dates and times as TEXT, REAL, or INTEGER values
    public function reCreateDataStructure() {
        $commandList = [
            'Create table if not exists '.$this->existingBarcodesTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, PATHTOBARCODE TEXT, RAWBARCODEREGISTERED TEXT NOT NULL UNIQUE, FIELD1 TEXT, FIELD2 TEXT, FIELD3 TEXT, BARCODETYPE VARCHAR(10) )',
            
            'Create table if not exists '.$this->scanHistoryTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, KNOWNBARCODE_ID INTEGER, RAWBARCODE TEXT, SCANDATETIME TEXT, FOREIGN KEY(KNOWNBARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) ON DELETE CASCADE ON UPDATE CASCADE )',
            
            'Create table if not exists '.$this->accessRolesTableName.
            '(ACCESSID INTEGER PRIMARY KEY AUTOINCREMENT, ACCESSROLE TEXT NOT NULL UNIQUE, LANGUAGE VARCHAR(3))',
            
            'Create table if not exists '.$this->companyWorkTimeTableName.
            '(WORKID INTEGER PRIMARY KEY AUTOINCREMENT, DATEUSED TEXT NOT NULL , TIMESTART TEXT NOT NULL, TIMEEND TEXT NOT NULL)',
            
            'Create table if not exists '.$this->settingsTableName.
            '(USESCHEDULE INTEGER NOT NULL, LIMITBYWORKDAYTIME INTEGER NOT NULL)'
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
        //predefined work schedules 
        $predefinedScheduleItem = ["08:00", "16:30", "0001-01-02"];
            $reconScheduleQuery = "SELECT COUNT(*) AS FOUND FROM ".$this->companyWorkTimeTableName." WHERE DATEUSED = :date";
            $stmt3 = $this->pdoInstance->prepare($reconScheduleQuery);
            $stmt3->bindParam(":date",$predefinedScheduleItem[2], PDO::PARAM_STR);
            $stmt3->execute();
            $count_itms=0;
            while ($row=$stmt3->fetch(\PDO::FETCH_ASSOC)) {
                $count_itms = $row['FOUND'];
            }
            if ($count_itms == 0) {//add this item
                $this->addNewCompanyScheduleDay($predefinedScheduleItem);
            }
        //predefined  settings
        $predefinedSettingsItem = ["USESCHEDULE"=>1,"LIMITBYWORKDAYTIME"=>0];
            $reconSettingsQuery = "SELECT COUNT(*) AS FOUND FROM ".$this->settingsTableName;
            $stmt4 = $this->pdoInstance->prepare($reconSettingsQuery);
            $stmt4->execute();
            $count_itms2=0;
            while ($row=$stmt4->fetch(\PDO::FETCH_ASSOC)) {
                $count_itms2 = $row['FOUND'];
            }
            if ($count_itms2 == 0) {//add this item
                $this->addNewSettings($predefinedSettingsItem);
            }
    }
    //!!schedule
    /**
     * A default company's work schedule is the one which relates to date 0001-01-02
     * @param array $arrayNewSchedule associative array with at least 3 items: time start and time end and date;
     * @return boolean the addition went smoothly
     */
    public function addNewCompanyScheduleDay(array $arrayNewSchedule) {
        $insertScheduleQuery = "Insert Into ".$this->companyWorkTimeTableName."(TIMESTART, TIMEEND, DATEUSED) VALUES (:timestart, :timeend, :dateused)";
        $stmt = $this->pdoInstance->prepare($insertScheduleQuery);
        $stmt->bindParam(":timestart",$arrayNewSchedule[0], PDO::PARAM_STR);
        $stmt->bindParam(":timeend",$arrayNewSchedule[1], PDO::PARAM_STR);
        $stmt->bindParam(":dateused",$arrayNewSchedule[2], PDO::PARAM_STR);
        try {
            $stmt->execute();
        } catch (PDOException $exc) {
            return false;
        }
        return TRUE;
    }
    /**
     * get Company Schedule
     * @return associative array with fields: TIMESTART, TIMEEND, DATEUSED (a special meaningless date is used to indicate that this schedule is a default one)
     */
    public function getDefaultCompanySchedule() {
        $reconScheduleQuery = "SELECT * FROM ".$this->companyWorkTimeTableName." WHERE DATEUSED = :date";
        $predefinedScheduleItemDate = "0001-01-02";
        $stmt = $this->pdoInstance->prepare($reconScheduleQuery);
        $stmt->bindParam(":date",$predefinedScheduleItemDate, PDO::PARAM_STR);
        $stmt->execute();
        $predefinedScheduleItem = ["TIMESTART"=>"", "TIMEEND"=>"", "DATEUSED"=>""];
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
                $predefinedScheduleItem["TIMESTART"] = $row['TIMESTART'];
                $predefinedScheduleItem["TIMEEND"] = $row['TIMEEND'];
                $predefinedScheduleItem["DATEUSED" ] = $row['DATEUSED'];
                return $predefinedScheduleItem;
        }
    }
    /**
     * A default company's work schedule is the one which relates to date 0001-01-02
     * @param array $in_CompanySchedule - associative array with at least 3 keys: "timestart" and "timeend" and "dateused";
     *  values should conform to regex \d\d:\d\d
     */
    public function updateCompanySchedule(array $in_CompanySchedule) {
        $updateQuery = "UPDATE ".$this->companyWorkTimeTableName." SET TIMESTART=:timestart, TIMEEND=:timeend WHERE DATEUSED=:in_date"; 
        $stmt = $this->pdoInstance->prepare($updateQuery);
        $stmt->bindParam(":timestart",$in_CompanySchedule["timestart"], PDO::PARAM_STR);
        $stmt->bindParam(":timeend",$in_CompanySchedule["timeend"], PDO::PARAM_STR);
        $stmt->bindParam(":in_date",$in_CompanySchedule["dateused"], PDO::PARAM_STR);
        $stmt->execute();
    }
    //!!additional settings
    /**
     * 
     * @param array $in_newSettings an example: ["USESCHEDULE"=>1,"LIMITBYWORKDAYTIME"=>0] 
     */
    public function addNewSettings(array $in_newSettings) {
        $insertSettingsQuery = "Insert Into ".$this->settingsTableName."(USESCHEDULE, LIMITBYWORKDAYTIME) VALUES (:param1, :param2)";
        $stmt = $this->pdoInstance->prepare($insertSettingsQuery);
        $stmt->bindParam(":param1",$in_newSettings["USESCHEDULE"], PDO::PARAM_INT);
        $stmt->bindParam(":param2",$in_newSettings["LIMITBYWORKDAYTIME"], PDO::PARAM_STR);
        $stmt->execute();
    }
    public function getExistingSettings() {
        $selectSettingsQuery = "Select * FROM ".$this->settingsTableName;
        $stmt = $this->pdoInstance->prepare($selectSettingsQuery);
        $stmt->execute();
        $predefinedSettingsItem = ["USESCHEDULE"=>1,"LIMITBYWORKDAYTIME"=>0];
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $predefinedSettingsItem["USESCHEDULE"] = $row["USESCHEDULE"];
            $predefinedSettingsItem["LIMITBYWORKDAYTIME"] = $row["LIMITBYWORKDAYTIME"];
            return $predefinedSettingsItem;
        }
        
        }
    public function updateSettings(array $in_newSettings) {
        $updateQuery = "UPDATE ".$this->settingsTableName." SET USESCHEDULE=:param1, LIMITBYWORKDAYTIME=:param2"; 
        $stmt= $this->pdoInstance->prepare($updateQuery);
        $stmt->bindParam(":param1",$in_newSettings["USESCHEDULE"], PDO::PARAM_INT);
        $stmt->bindParam(":param2",$in_newSettings["LIMITBYWORKDAYTIME"], PDO::PARAM_INT);
        $stmt->execute();
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
    /**
     * get scan entries combined with all registered entities. Used for generating aggregated table (list3)
     * @param type $in_dateTimeStart - start of range
     * @param type $in_dateTimeEnd - end of range
     * @return array Associative array with keys: ID (from scan history table), BCODE (ID from registered barcodes), RAWBARCODE from registered barcodes, 
     * FIELD1 registered barcodes, FIELD2 registered barcodes FIELD3 registered barcodes, SCANDATETIME. ALL VALUES EXCEPT BCODE AND FIELD1,FIELD2,FIELD3 MAY BE NULL (it was observed that those nullable properties may not be present in result)
     * Array comes out sort of "sorted": users come out in "groups" (entries relating to same user are located close, one to another) and time is sorted inside the group 
     * (pay attention that datetime in sqlite is stored that string, so sorting result may be not that predictable. It may be worth to re-sort it in app code). Date comes in format "Y-m-d H:i:s"
     */
    public function listScanTimesInRange2($in_dateTimeStart, $in_dateTimeEnd) {
        $SHTN = $this->scanHistoryTableName;
        $RBTN = $this->existingBarcodesTableName;
        $sqlQuery = "select ".$SHTN.".ID as SCANID, ".$RBTN.".ID AS BCODE, ".$RBTN.".RAWBARCODEREGISTERED AS RAWBCODE, ".$RBTN.".FIELD1, ".$RBTN.".FIELD2, ".$RBTN.".FIELD3, ".$SHTN.".SCANDATETIME"
                . " FROM ".$RBTN." left join ".$SHTN." on ".$SHTN.".KNOWNBARCODE_ID = ".$RBTN.".ID WHERE ((SCANDATETIME IS NULL) OR (SCANDATETIME BETWEEN :val1 AND :val2 )) order by RAWBCODE, SCANDATETIME";
        $stmt = $this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":val1", $in_dateTimeStart);
        $stmt->bindParam(":val2", $in_dateTimeEnd);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['SCANID'=>$row['SCANID'], 'BCODE'=>$row['BCODE'], 'RAWBARCODE'=>$row['RAWBCODE'], 'SCANDATETIME'=>$row['SCANDATETIME'],
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
     * @param array $in_barcodeListID
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

    public function getBarcodeTextByID($in_entityID) {
        $usedID = intval($in_entityID);
        $stmt=$this->pdoInstance->prepare("SELECT RAWBARCODEREGISTERED FROM ".$this->existingBarcodesTableName." WHERE ID=:usedID");
        $stmt->bindParam(":usedID", $usedID, PDO::PARAM_INT);
        $stmt->execute();
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
           
            return $row["RAWBARCODEREGISTERED"];
        }        
        return null;
    }
    /**
     * update info about the fact of scanned barcode. ALL PARAMETERS ARE PASSED AS STRING
     * @param type $rcrdToUpdate - ID of scanhistory record
     * @param type $entityRawText - newly set raw scanned barcode
     * @param type $datetimePickupdItem - newly set time
     * @param type $entityIDupdItem - ID of related barcode entry
     */
    public function updateScanRecord($rcrdToUpdate, $entityRawText, $datetimePickupdItem, $entityIDupdItem) {
        $sqlQuery = "UPDATE ".$this->scanHistoryTableName." SET KNOWNBARCODE_ID=:KNOWNBARCODE_ID, RAWBARCODE=:RAWBARCODE, SCANDATETIME=:SCANDATETIME WHERE ID=:scanentryID";
        $stmt=$this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":KNOWNBARCODE_ID", intval($entityIDupdItem), PDO::PARAM_INT);
        $stmt->bindParam(":SCANDATETIME", $datetimePickupdItem, PDO::PARAM_STR);
        $stmt->bindParam(":RAWBARCODE", $entityRawText, PDO::PARAM_STR);
        $stmt->bindParam(":scanentryID", intval($rcrdToUpdate), PDO::PARAM_INT);
        $stmt->execute();
    }
    /**
     * remove single item from scan history
     * @param string $in_singleScanEntryID
     */
    public function removeSingleScanEntry($rcrdToUpdate) {
        $sqlQuery = "DELETE FROM ".$this->scanHistoryTableName." WHERE ID=:scanentryID";
        $stmt=$this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":scanentryID", intval($rcrdToUpdate), PDO::PARAM_INT);
        $stmt->execute();
    }

}