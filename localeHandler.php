<?php
/**
 * Handle strings from different locales
 * @author Transfer
 */
class localeHandler {
    private $completeLocalization = [
    "en" => array(
        "common"=>["header"=>"SM_NM Scanner" ],
        "page-main"=>[
            "invitation"=>"Scan some barcode!",
            "invitationclick"=>"[or click here to show form and type in data with keypad]",
            "scannedlist"=>"[List Of registered Scans]",
            "registeredlist"=>"[List of Registered Barcodes]",
            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[List of Registered Barcodes]"

            ]
        ),
    "ru" => array(
        "common"=>["header"=>"SM_NM Сканнер" ],
        "page-main"=>[
            "invitation"=>"Отсканируйте штрихкод!",
            "invitationclick"=>"[Или нажмите здесь чтобы показать строчку и набрать самому (если сканер штрихкодов не работает)]",
            "scannedlist"=>"[Список отсканированных элементов]",
            "registeredlist"=>"[Список зарегистрированных элементов]"
            ],
        "page-registeredbarcodes"=>[
            "registeredlistcaption"=>"[Список зарегистрированных элементов]"

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
