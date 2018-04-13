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

            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[List of Registered Barcodes]",
            "registeredlistnewitemdescrean13"=>"Contains 13 digits (12 usable digits + 1 check digit);",
            "registeredlistnewitemdescrean8" => "Contains 7 usable digits + 1 check digit",
            "registeredlistnewitemdescrcode128"=>"Contains up to 15 characters - latin letters, digits",
            "ncodefield1"=>'Field 1',
            "ncodefield2"=>'Field 2',
            "ncodefield3"=>'Field 3',
            "ncodebarcode"=>'Barcode Data',
            "ncodebarcodetype"=>'Barcode Type',
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

            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[Список зарегистрированных элементов]",
            "registeredlistnewitemdescrean13"=>"Содержит всего 13 цифр (необходимо ввести 12 цифр. 1 цифра - контрольная);",
            "registeredlistnewitemdescrean8"=>"Содержит всего 8 цифр (необходимо ввести 7 цифр. 1 цифра - контрольная);",
            "registeredlistnewitemdescrcode128"=>"Содержит (желательно) до 15 символов - латинских букв, цифр",
            "ncodefield1"=>'Поле 1',
            "ncodefield2"=>'Поле 2',
            "ncodefield3"=>'Поле 3',
            "ncodebarcode"=>'Данные в штрихкоде',
            "ncodebarcodetype"=>'Тип штрихкода',
            
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
