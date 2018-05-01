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
            "scannedlist2"=>"[List Of registered Scans - Variant2]",
            "optionsentry" => "[Options]",
            "meaningless" => "[This item does nothing]"
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
        "page-scanlist"=>[
            "rawbarcode_header"=>"Data in Barcode",
            "scandatetime_header"=>"Time",
            "fromdate"=>"Start Date",
            "enddate"=>"End Date",
            "filterbydate"=>"[By Date]",
            "tableheader"=>"List of scanned items",
            "tableheaderv2"=>"List of scanned items, v2",
            "scanlisttotal"=>"Total amount: ",
        ],
        "pageform-scanlist"=>[
            "pageform_remove"=>"[remove]",
            "pageform_edit"=>"[edit]",
            "pageform_captionnew"=>"New Entry",
            "pageform_captionedit"=>"Edit Entry",
            "pageform_apply"=>"apply changes",
            "pageform_addmsgok"=>"New scan entry added",
            "pageform_addmsgnotfound"=>"Failed to add new scan entry: failed to find a barcode",
            "pageform_editmsgok"=>"Scan entry updated",
            "pageform_editmsgnotfound"=>"Failed to update scan entry: failed to find a barcode"
        ],
        "page-printpage"=>[
            "registeredlistprintwndcaption"=>"Barcode Printing"
        ],
        "page-restricted"=>[
            "rprestricted"=>"This page should not be viewed by anyone (Only by authorized people)",
            "rpexplain"=>"What is your job position?"
        ],
        "page-options"=>[
            "optscaption"=>"Here be options",
            "optsdefaultschedule"=>"Common Working Schedule",
            "optsuseworkschedule"=>"Involve company work schedule in calculations",
            "optsusetimeonlylimitedbyworkday"=>"Include time only in the range of working schedule",
            "optsmsgless8hours"=>"Difference between time is less than 8 hours. Is it OK?",
        ]
        ),
    "ru" => array(
        "common"=>["header"=>"SM_NM Сканнер",
            "scannedlist"=>"[Список отсканированных элементов]",
            "registeredlist"=>"[Список зарегистрированных элементов]",
            "scannedlist2"=>"[Список отсканированных элементов - Вариант2]",
            "optionsentry" => "[Настройки]",
            "meaningless" => "[Этот пункт ни на что не влияет]"
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
        
        "page-scanlist"=>[
            "rawbarcode_header"=>"Штрихкод",
            "scandatetime_header"=>"Время",
            "filterbydate"=>"[По дате]",
            "fromdate"=>"Дата Начало",
            "enddate"=>"Дата Окончание",
            "tableheader"=>"Список фактов сканирования штрихкода",
            "tableheaderv2"=>"Список фактов сканирования штрихкода - сгрупирован, вариант 2",
            "scanlisttotal"=>"Всего: "
            ],
        "pageform-scanlist"=>[
            "pageform_remove"=>"[Убрать запись]",
            "pageform_edit"=>"[Править запись]",
            "pageform_captionnew"=>"Новая запись",
            "pageform_captionedit"=>"Исправьте запись",
            "pageform_apply"=>"внести изменения",
            "pageform_addmsgok"=>"Добавлена новая запись в таблицу сканирований",
            "pageform_addmsgnotfound"=>"Не получилось добавить новую запись в таблицу фактов сканирования: такого штрихкода не обнаружено",
            "pageform_editmsgok"=>"Исправлена запись в таблице сканирований",
            "pageform_editmsgnotfound"=>"Не получилось исправить запись в таблице фактов сканирования: такого штрихкода не обнаружено",
        ],
        "page-printpage"=>[
            "registeredlistprintwndcaption"=>"Печать штрихкодов"
        ],
        "page-restricted"=>[
            "rprestricted"=>"Эта страница не должна иметь возможность для публичного просмотра (только для некоторых людей)",
            "rpexplain"=>"Введите сюда должность"
        ],
        "page-options"=>[
            "optscaption"=>"Тут будут настройки для вычислений",
            "optsdefaultschedule"=>"Обычный график работы предприятия",
            "optsuseworkschedule"=>"Применять график работы предприятия при вычислениях",
            "optsusetimeonlylimitedbyworkday"=>"Учитывать только время в диапазоне рабочего дня",
            "optsmsgless8hours"=>"Разница между значениями времени составляет меньше 8 часов? Правильно?",
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
