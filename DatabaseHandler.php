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
    public $customWorkTimeTableName = "customworkscheduleworker";
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
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, PATHTOBARCODE TEXT, RAWBARCODEREGISTERED TEXT NOT NULL UNIQUE, FIELD1 TEXT, FIELD2 TEXT, FIELD3 TEXT, FIELDPOSITION TEXT, FIELDGENDER CHARACTER, BARCODETYPE VARCHAR(10) )',
            
            'Create table if not exists '.$this->scanHistoryTableName.
            '(ID INTEGER PRIMARY KEY AUTOINCREMENT, KNOWNBARCODE_ID INTEGER, RAWBARCODE TEXT, SCANDATETIME TEXT, FOREIGN KEY(KNOWNBARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) ON DELETE CASCADE ON UPDATE CASCADE )',
            
            'Create table if not exists '.$this->accessRolesTableName.
            '(ACCESSID INTEGER PRIMARY KEY AUTOINCREMENT, ACCESSROLE TEXT NOT NULL UNIQUE, LANGUAGE VARCHAR(3))',
            
            //table for company schedule. TIMETYPE shows whether this record relates to worktime (value of 0) or breaktime (value of 1)
            'Create table if not exists '.$this->companyWorkTimeTableName.
            '(WORKID INTEGER PRIMARY KEY AUTOINCREMENT, DATEUSED TEXT NOT NULL , TIMESTART TEXT NOT NULL, TIMEEND TEXT NOT NULL, TIMETYPE INTEGER)',
            
            // a special table for custom schedule for worker. FK_BARCODEID relates to worker. STARTORENDTIME is a time of start or end of work. 
            // TIMETYPE == 0 if it is start time or 1 if it is end time. 
            // CURRENTDAY == 0 if this time is related to next day or CURRENTDAY == 1 if this time is related to current day
            'Create table if not exists '.$this->customWorkTimeTableName.
            '(IDWORKTIME INTEGER PRIMARY KEY AUTOINCREMENT, BARCODE_ID INTEGER, STARTORENDTIME TEXT NOT NULL, TIMETYPE INTEGER, CURRENTDAY INTEGER , FOREIGN KEY(BARCODE_ID) REFERENCES '.$this->existingBarcodesTableName.'(ID) ON DELETE CASCADE ON UPDATE CASCADE)',
            
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
        $predefinedScheduleItem = ["08:00", "16:30", "0001-01-02", 0];
        $predefinedBreaktimeItem = ["12:00", "12:30", "0001-01-02", 1];
        $reconScheduleQuery = "SELECT COUNT(*) AS FOUND FROM ".$this->companyWorkTimeTableName." WHERE DATEUSED = :date AND TIMETYPE=:intimetype";
            $stmt3 = $this->pdoInstance->prepare($reconScheduleQuery);
            $stmt3->bindParam(":date",$predefinedScheduleItem[2], PDO::PARAM_STR);
            $stmt3->bindParam(":intimetype",$predefinedScheduleItem[3], PDO::PARAM_INT);
            $stmt3->execute();
            $count_itms=0;
            while ($row=$stmt3->fetch(\PDO::FETCH_ASSOC)) {
                $count_itms = $row['FOUND'];
            }
            if ($count_itms == 0) {//add this item
                $this->addNewCompanyScheduleDay($predefinedScheduleItem);
            }
            $stmt3v1 = $this->pdoInstance->prepare($reconScheduleQuery);
            $stmt3v1->bindParam(":date",$predefinedScheduleItem[2], PDO::PARAM_STR);
            $stmt3v1->bindParam(":intimetype",$predefinedBreaktimeItem[3], PDO::PARAM_INT);
            $stmt3v1->execute(); $count_itms3v1=0;
            while ($row=$stmt3v1->fetch(\PDO::FETCH_ASSOC)) {
                $count_itms3v1 = $row['FOUND'];
            }
            if ($count_itms == 0) {//add this item
                $this->addNewCompanyScheduleDay($predefinedBreaktimeItem);
            }
            
        //predefined  settings
        $predefinedSettingsItem = ["USESCHEDULE"=>1,"LIMITBYWORKDAYTIME"=>1];
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
     * @param array $arrayNewSchedule associative array with at least 4 items: time start and time end and date and timetype (0 for workhours and 1 for breaktime)
     * @return boolean the addition went smoothly
     */
    public function addNewCompanyScheduleDay(array $arrayNewSchedule) {        
        $insertScheduleQuery = "Insert Into ".$this->companyWorkTimeTableName."(TIMESTART, TIMEEND, DATEUSED, TIMETYPE) VALUES (:timestart, :timeend, :dateused, :timetype)";
        $stmt = $this->pdoInstance->prepare($insertScheduleQuery);
        $stmt->bindParam(":timestart",$arrayNewSchedule[0], PDO::PARAM_STR);
        $stmt->bindParam(":timeend",$arrayNewSchedule[1], PDO::PARAM_STR);
        $stmt->bindParam(":dateused",$arrayNewSchedule[2], PDO::PARAM_STR);
        $usedtimetype = isset($arrayNewSchedule[3])?$arrayNewSchedule[3]:0;
        $stmt->bindParam(":timetype", $usedtimetype, PDO::PARAM_INT);
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
        $reconScheduleQuery = "SELECT * FROM ".$this->companyWorkTimeTableName." WHERE DATEUSED = :date AND TIMETYPE=0";
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
    //TODO: refactor this routie and unite it with previous one to getDefaultCompanyScheduleOrBreak
    public function getDefaultCompanyBreak() {
        $reconScheduleQuery = "SELECT * FROM ".$this->companyWorkTimeTableName." WHERE DATEUSED = :date AND TIMETYPE=1";
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
     * Update default company schedule or default break. A default company's work schedule is the one which relates to date 0001-01-02
     * @param array $in_CompanySchedule - associative array with at least 4 keys: "timestart" and "timeend" and "dateused" and "timetype";
     *  values should conform to regex \d\d:\d\d
     */
    public function updateCompanySchedule(array $in_CompanySchedule) {
        $updateQuery = "UPDATE ".$this->companyWorkTimeTableName." SET TIMESTART=:timestart, TIMEEND=:timeend WHERE DATEUSED=:in_date AND timetype = :in_timetype"; 
        $stmt = $this->pdoInstance->prepare($updateQuery);
        $stmt->bindParam(":timestart",$in_CompanySchedule["timestart"], PDO::PARAM_STR);
        $stmt->bindParam(":timeend",$in_CompanySchedule["timeend"], PDO::PARAM_STR);
        $stmt->bindParam(":in_date",$in_CompanySchedule["dateused"], PDO::PARAM_STR);
        $usedtimetype = isset($in_CompanySchedule["timetype"])?$in_CompanySchedule["timetype"]:0;
        $stmt->bindParam(":in_timetype", $usedtimetype, PDO::PARAM_INT);
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
     * get scan entries combined with all registered entities. Used for generating aggregated table (list3). ! Do not take into account entities with special schedule !
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
        $CWTN = $this->customWorkTimeTableName;
        /* This query was not working properly
        $sqlQuery = "select ".$SHTN.".ID as SCANID, ".$RBTN.".ID AS BCODE, ".$RBTN.".RAWBARCODEREGISTERED AS RAWBCODE, ".$RBTN.".FIELD1, ".$RBTN.".FIELD2, ".$RBTN.".FIELD3, ".$SHTN.".SCANDATETIME"
                . " FROM ( (".$RBTN." left join ".$SHTN." on ".$SHTN.".KNOWNBARCODE_ID = ".$RBTN.".ID) LEFT JOIN ".$CWTN." ON ".$CWTN.".BARCODE_ID = ".$RBTN.".ID )  WHERE ((".$CWTN.".BARCODE_ID IS NULL) AND ( (SCANDATETIME IS NULL) OR (SCANDATETIME BETWEEN :val1 AND :val2 ) ) ) order by RAWBCODE, SCANDATETIME";
         * 
         */
        //it may be useful to optimize this. It selects scans within a certain range, and complements the result with any other entries from barcode scans table
        $sqlQuery = "select SCANID, ".$RBTN.".ID AS BCODE2, ".$RBTN.".RAWBARCODEREGISTERED AS RAWBCODE2, FIELD1, FIELD2, FIELD3, SCANDATETIME, ".$RBTN.".FIELDGENDER AS GENDER, ".$RBTN.".FIELDPOSITION AS POSITION "
                . "FROM ".$RBTN." LEFT JOIN (select ".$SHTN.".ID as SCANID, ".$RBTN.".ID AS BCODE, ".$RBTN.".RAWBARCODEREGISTERED AS RAWBCODE, ".$RBTN.".FIELD1 as FLD1, ".$RBTN.".FIELD2 as FLD2, ".$RBTN.".FIELD3 as FLD3, ".$SHTN.".SCANDATETIME FROM "
                . "( (".$RBTN." left join ".$SHTN." on ".$SHTN.".KNOWNBARCODE_ID = ".$RBTN.".ID) LEFT JOIN ".$CWTN." ON ".$CWTN.".BARCODE_ID = ".$RBTN.".ID )  WHERE ((".$CWTN.".BARCODE_ID IS NULL) AND ( (SCANDATETIME IS NULL) OR (SCANDATETIME BETWEEN :val1 AND :val2 ) ) ) order by RAWBCODE, SCANDATETIME)  ON BCODE=".$RBTN.".ID";
        $stmt = $this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":val1", $in_dateTimeStart);
        $stmt->bindParam(":val2", $in_dateTimeEnd);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['SCANID'=>$row['SCANID'], 'BCODE'=>$row['BCODE2'], 'RAWBARCODE'=>$row['RAWBCODE2'], 'SCANDATETIME'=>$row['SCANDATETIME'],
                "FIELD1"=>$row["FIELD1"], "FIELD2"=>$row["FIELD2"], "FIELD3"=>$row["FIELD3"], "GENDER"=>$row["GENDER"], "POSITION"=>$row["POSITION"] ];            
        }
        return $allScan;
    }
    
    public function listAllBarcodes() {
        $stmt = $this->pdoInstance->query("SELECT * FROM ".$this->existingBarcodesTableName);
        $stmt->execute();
        $allScan=[];        
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            $allScan[] = (object)['ID'=>$row['ID'], 'PATHTOBARCODE'=>$row['PATHTOBARCODE'], 'RAWBARCODEREGISTERED'=>$row['RAWBARCODEREGISTERED'],
                'FIELD1'=>$row['FIELD1'], 'FIELD2'=>$row['FIELD2'], 'FIELD3'=>$row['FIELD3'], 'BARCODETYPE'=>$row['BARCODETYPE'], 'FIELDGENDER'=>$row['FIELDGENDER'], 'FIELDPOSITION'=>$row['FIELDPOSITION'] ];
            
        }
        return $allScan;
    }
    public function saveCodeEntry($in_data, $in_pathToBarcode, $in_field1, $in_field2, $in_field3, $in_barcodetype, $in_position, $in_gender) {
        $stmt = $this->pdoInstance->prepare("Insert INTO ".$this->existingBarcodesTableName."(PATHTOBARCODE, RAWBARCODEREGISTERED, FIELD1, FIELD2, FIELD3, BARCODETYPE, FIELDPOSITION, FIELDGENDER) VALUES (:path, :rawcode, :field1, :field2, :field3, :barcodetype, :position, :gender)");
        $stmt->bindParam(":path", $in_pathToBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":rawcode", $in_data, PDO::PARAM_STR);
        $stmt->bindParam(":field1", $in_field1, PDO::PARAM_STR);
        $stmt->bindParam(":field2", $in_field2, PDO::PARAM_STR);
        $stmt->bindParam(":field3", $in_field3, PDO::PARAM_STR);
        $stmt->bindParam(":position", $in_position, PDO::PARAM_STR);
        $stmt->bindParam(":gender", $in_gender, PDO::PARAM_STR);
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
                'FIELD1'=>$row['FIELD1'], 'FIELD2'=>$row['FIELD2'], 'FIELD3'=>$row['FIELD3'], 'BARCODETYPE'=>$row['BARCODETYPE'], 'FIELDPOSITION'=>$row["FIELDPOSITION"] ];
            
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
        $usedFieldGender = $in_barcodeObject->{"gender"};
        $usedFieldPosition = $in_barcodeObject->{"position"};
        $stmt = $this->pdoInstance->prepare("UPDATE ".$this->existingBarcodesTableName." SET PATHTOBARCODE=:usepath, RAWBARCODEREGISTERED=:usebarcode, FIELD1=:fld1, FIELD2=:fld2, FIELD3=:fld3, fieldgender=:fieldgender, fieldposition=:fieldposition WHERE ID=:useID");
        $stmt->bindParam(":usepath", $newPathToBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":usebarcode", $usedRawBarcode, PDO::PARAM_STR);
        $stmt->bindParam(":fld1", $usedField1, PDO::PARAM_STR);
        $stmt->bindParam(":fld2", $usedField2, PDO::PARAM_STR);
        $stmt->bindParam(":fld3", $usedField3, PDO::PARAM_STR);
        $stmt->bindParam(":useID", $usedID, PDO::PARAM_INT);
        $stmt->bindParam(":fieldgender", $usedFieldGender, PDO::PARAM_STR);
        $stmt->bindParam(":fieldposition", $usedFieldPosition, PDO::PARAM_STR);
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
    /**
     * Obtain all custom schedules for ALL workers, where it is possible. It returns already PREPARED array of objects.
     * @return ASSOCIATIVE_ARRAY of stdClass objects. each object: BARCODE_ID - id of related record; START_TIME - start time of period; END_TIME - end time of period
     * START_TIME_TYPE - string with following values: either 'current' or 'next' day
     * END_TIME_TYPE - string with following values: either 'current' or 'next'
     * Keys of array are BARCODE_ID
     */
    public function getAllCustomSchedules() {
        $sqlQuery = "SELECT ".$this->customWorkTimeTableName.".*, ".$this->existingBarcodesTableName.".FIELD1, "
                .$this->existingBarcodesTableName.".FIELD2, ".$this->existingBarcodesTableName.".FIELD3 "
                ." FROM ".$this->customWorkTimeTableName." INNER JOIN ".$this->existingBarcodesTableName." ON "
                .$this->customWorkTimeTableName.".BARCODE_ID = ".$this->existingBarcodesTableName.".ID".
                " ORDER BY BARCODE_ID, TIMETYPE";
        $stmt=$this->pdoInstance->prepare($sqlQuery);
        $stmt->execute();
        $arrayToReturn = [];
        $nextObject = (object)['BARCODE_ID'=>'', "BARCODE_FLD1"=>'', "BARCODE_FLD2"=>'', "BARCODE_FLD3"=>'', 'START_TIME'=>'', 'END_TIME'=>'', 'START_TIME_TYPE'=>'', 'END_TIME_TYPE'=>''];
        $previousQueryEntry = NULL;
        //we use assumption that only start and end time defines a custom schedule.
        while ($row=$stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($previousQueryEntry != $row['BARCODE_ID']) {
                $nextObject = (object)['BARCODE_ID'=>'', 'START_TIME'=>'', 'END_TIME'=>'', 'START_TIME_TYPE'=>'', 'END_TIME_TYPE'=>''];
                $previousQueryEntry = $row['BARCODE_ID'];
                $currentIndex = '';
                //$row['TIMETYPE'] should be always 0, taking into account of  assumption in addNewCustomSchedule
                if ($row['TIMETYPE']==1) { $currentIndex = 'END_TIME';} 
                elseif ($row['TIMETYPE']==0) { $currentIndex = 'START_TIME';}
                $nextObject->{'BARCODE_ID'} = $row['BARCODE_ID'];
                $nextObject->{'BARCODE_FLD1'} = $row["FIELD1"];
                $nextObject->{'BARCODE_FLD2'} = $row["FIELD2"];
                $nextObject->{'BARCODE_FLD3'} = $row["FIELD3"];
                $nextObject->{$currentIndex} = $row["STARTORENDTIME"];
                $nextObject->{$currentIndex.'_TYPE'} =( ($row['CURRENTDAY'] == 1)?/*'current'*/'optscurrentdayselect':(($row['CURRENTDAY'] == 0)?/*'next'*/'optsnextdayselect':null) );
                $arrayToReturn[ $row['BARCODE_ID'] ] = $nextObject;
            } else {
                //got entry considering the entity with ID from our array. Add related data to already added entry
                $currentIndex = '';
                //$row['TIMETYPE'] should be always 1, taking into account of  assumption in addNewCustomSchedule
                if ($row['TIMETYPE']==1) { $currentIndex = 'END_TIME';} 
                elseif ($row['TIMETYPE']==0) { $currentIndex = 'START_TIME';}
                assert( $arrayToReturn[$row['BARCODE_ID'] ]->{'BARCODE_ID'} == $row['BARCODE_ID'] );
                $arrayToReturn[$row['BARCODE_ID'] ]->{$currentIndex} = $row["STARTORENDTIME"];
                $arrayToReturn[$row['BARCODE_ID'] ]->{$currentIndex.'_TYPE'} =( ($row['CURRENTDAY'] == 1)?/*'current'*/'optscurrentdayselect':(($row['CURRENTDAY'] == 0)?/*'next'*/'optsnextdayselect':null) );
                
            }
        }        
        return $arrayToReturn;
    }
    /**
     * Add new custom schedule for worker. Consider the fact that record has passed all the validation
     * @param stdClass $in_newObjectWithCustomSchedule - object with fields: 
     *  START_TIME - start time of period;
     *  END_TIME - end time of period;
     *  START_TIME_TYPE - string with following values: either 'current' or 'next' day;
     *  END_TIME_TYPE - string with following values: either 'current' or 'next';
     *  ENTITY_ID - related ID from barcodes table
     * @return Boolean - status of addition
     */
    public function addNewCustomSchedule($in_newObjectWithCustomSchedule) {
        //assumption: adding more than single custom work schedule for certain worker is not supported by now . And for single worker only 2 records may relate .
        $sqlQueryRecon = "SELECT COUNT(BARCODE_ID) AS ENTRIES_NUM FROM ".$this->customWorkTimeTableName." WHERE BARCODE_ID = :IN_BARCODE";
        $stmtRecon=$this->pdoInstance->prepare($sqlQueryRecon);
        $stmtRecon->bindParam(":IN_BARCODE", $in_newObjectWithCustomSchedule->{'ENTITY_ID'}, PDO::PARAM_INT);
        $stmtRecon->execute();
        while ($row=$stmtRecon->fetch(\PDO::FETCH_ASSOC)) {
            if (intval($row["ENTRIES_NUM"])>=2) {
                return false;
            }
        }
        //check done, proceed to entry addition
        $sqlQuery = "INSERT INTO ".$this->customWorkTimeTableName." (BARCODE_ID,STARTORENDTIME,TIMETYPE,CURRENTDAY) values (:IN_BARCODE, :IN_STARTORENDTIME, :IN_TIMETYPE, :IN_CURRENTDAY)";
        $stmt=$this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":IN_BARCODE", $in_newObjectWithCustomSchedule->{'ENTITY_ID'}, PDO::PARAM_INT);
        $stmt->bindParam(":IN_STARTORENDTIME", $in_newObjectWithCustomSchedule->{'START_TIME'}, PDO::PARAM_STR);
        $intpass1= 0; $stmt->bindParam(":IN_TIMETYPE", $intpass1, PDO::PARAM_INT);
        $intpass2= (($in_newObjectWithCustomSchedule->{'START_TIME_TYPE'}=='current')?0:1);
        $stmt->bindParam(":IN_CURRENTDAY", $intpass2, PDO::PARAM_INT);
        $stmt->execute();
        $stmt2=$this->pdoInstance->prepare($sqlQuery);
        $stmt2->bindParam(":IN_BARCODE", $in_newObjectWithCustomSchedule->{'ENTITY_ID'}, PDO::PARAM_INT);
        $stmt2->bindParam(":IN_STARTORENDTIME", $in_newObjectWithCustomSchedule->{'END_TIME'}, PDO::PARAM_STR);
        $intpass3= 1; $stmt2->bindParam(":IN_TIMETYPE", $intpass3, PDO::PARAM_INT);
        $intpass4 = (($in_newObjectWithCustomSchedule->{'END_TIME_TYPE'}=='current')?0:1);
        $stmt2->bindParam(":IN_CURRENTDAY", $intpass4, PDO::PARAM_INT);
        $stmt2->execute();
        return true;
    }
    /**
     * remove all custom worktime entries which relate to certain worker
     * @param int $in_intBarcodeID
     */
    public function removeCustomWorkTimeDB($in_intBarcodeID) {
        $sqlQuery = "DELETE FROM ".$this->customWorkTimeTableName." WHERE BARCODE_ID = :IN_BARCODE";
        $stmt = $this->pdoInstance->prepare($sqlQuery);
        $stmt->bindParam(":IN_BARCODE", $in_intBarcodeID, PDO::PARAM_INT);
        $stmt->execute();
    }
}