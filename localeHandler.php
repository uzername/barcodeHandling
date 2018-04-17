<?php
/**
 * Handle strings from different locales
 * @author Transfer
 */
class localeHandler {
    private $completeLocalization = [
    "en" => array(
        "common"=>["header"=>"SM_NM Scanner",
            "scannedlist"=>"[List Of registered Scans]",
            "registeredlist"=>"[List of Registered Barcodes]",
            ],
        "page-main"=>[
            "invitation"=>"Scan some barcode!",
            "invitationclick"=>"[or click here to show form and type in data with keypad]",
            "invitationcodenotfound"=>"This barcode is not found in database",
            "invitationokresult"=>"Scanned!"
            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[List of Registered Barcodes]",
            "registeredlistnewitemdescrean13"=>"Contains 13 digits (12 usable digits + 1 check digit);",
            "registeredlistnewitemdescrean8" => "Contains 7 usable digits + 1 check digit",
            "registeredlistnewitemdescrcode128"=>"Contains up to 15-20 characters - latin letters, digits",
            "ncodefield1"=>'Field 1', //used in table too
            "ncodefield2"=>'Field 2', //used in table too
            "ncodefield3"=>'Field 3', //used in table too
            "ncodebarcode"=>'Barcode Data', //used in table too
            "ncodebarcodetype"=>'Barcode Type', //used in table too
            "registeredlistprint"=>"Print Selected", //used in table too
            "registeredlistbarcodecapt"=>"Barcode", //used in table too
            "registeredlistnoitemstoprint"=>"No items selected",
            "registeredlistnorawcode"=>"Data in barcode cannot be empty!",
            "registeredlistalreadyexist"=>"Item Already Exist",
            "registeredlistsingleremovebtn"=>"[Remove]",
            "registeredlistsingleeditbtn"=>"[Edit]",
            "registeredlistsinglesavebtn"=>"[Save]",
            ],
        "page-printpage"=>[
            "registeredlistprintwndcaption"=>"Barcode Printing"
        ]
        ),
    "ru" => array(
        "common"=>["header"=>"SM_NM Сканнер",
            "scannedlist"=>"[Список отсканированных элементов]",
            "registeredlist"=>"[Список зарегистрированных элементов]"
            ],
        "page-main"=>[
            "invitation"=>"Отсканируйте штрихкод!",
            "invitationclick"=>"[Или нажмите здесь чтобы показать строчку и набрать самому (если сканер штрихкодов не работает)]",
            "invitationcodenotfound"=>"Этого штрихкода нету в базе",
            "invitationokresult"=>"Отсканировано!"
            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[Список зарегистрированных элементов]",
            "registeredlistnewitemdescrean13"=>"Содержит всего 13 цифр (необходимо ввести 12 цифр. 1 цифра - контрольная);",
            "registeredlistnewitemdescrean8"=>"Содержит всего 8 цифр (необходимо ввести 7 цифр. 1 цифра - контрольная);",
            "registeredlistnewitemdescrcode128"=>"Содержит (желательно) до 15-20 символов - латинских букв, цифр",
            "ncodefield1"=>'Поле 1', //used in table too
            "ncodefield2"=>'Поле 2', //used in table too
            "ncodefield3"=>'Поле 3', //used in table too
            "ncodebarcode"=>'Данные в штрихкоде', //used in table too
            "ncodebarcodetype"=>'Тип штрихкода', //used in table too
            "registeredlistprint"=>"Распечатать отмеченные", //used in table too
            "registeredlistbarcodecapt" => "Штрихкод", //used in table too
            "registeredlistnoitemstoprint"=>"Ничего не выбрано для печати",
            "registeredlistalreadyexist"=>"Такой штрихкод уже существует",
            "registeredlistsingleremovebtn"=>"[Удалить]",
            "registeredlistsingleeditbtn"=>"[Править]",
            "registeredlistsinglesavebtn"=>"[Сохранить]",
            "registeredlistnorawcode"=>"Данные в штрихкоде должны быть заполнены",
            
            ],
        "page-printpage"=>[
            "registeredlistprintwndcaption"=>"Печать штрихкодов"
        ]
        )
    ];

    private $defaultLocale = "en";
    public function getLocaleSubArray($inLocaleID, $pageID) {
        $localeused = $this->defaultLocale;
        if ( array_key_exists(strtolower($inLocaleID), $this->completeLocalization) ) {
            $localeused = strtolower($inLocaleID);
        } else {

        }
        if (array_key_exists($pageID, $this->completeLocalization[$localeused] ) == FALSE) {
            //throw new Exception("locale data not found");
            return null;
        }
        $result= /*$this->completeLocalization[$localeused]["common"]+*/$this->completeLocalization[$localeused][$pageID];
        return $result;
    }

    public function validateLocale($param) {
        if (isset($this->completeLocalization[$param]) && array_key_exists($param, $this->completeLocalization ) ) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function getDefaultLocale() {
        return "en";
    }
}
