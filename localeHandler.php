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
            "scannedlist3"=>"[List Of registered Scans - Variant3]",
            "optionsentry" => "[Options]",
            "meaningless" => "[This item does nothing]",
            "showlist"=>"&#9660; SHOW LIST &#9660;",
            "hidelist"=>"&#9650; HIDE LIST &#9650;",
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
            
            "ncodefieldgender"=>'Field 4',
            "ncodefieldposition"=>'Position',
            "ncodefieldmale"=>'male',
            "ncodefieldfemale"=>'female',
            
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
            "tableheaderv3"=>"List of scanned items, aggregated, v3",
            "scanlisttotal"=>"Total amount: ",
            "tooltipartificialentry"=>"This entry was added because company's schedule is used",
            "overtimetext"=>"Overtime",
            "listv3timesheet" => "Time Sheet",
            "listv3headernumber" => "N ord.",
            "listv3headerposition" => "Personnel Entry",
            "listv3headername" => "Name, Position",
            "listv3headeraboutdates" => "Data of presence/absence by days (in hours)",
            "listv3headerformonth" => "Total",
            "listv3headerdaysformonth" => "days",
            "listv3headerhoursformonth" => "hours",
            "listv3headerdetailed" => "including:",
            "listv3headerovertime" => "overtime",
            "listv3headernight" => "night hours",
            "listv3headerevening" => "evening hours",
            "listv3headerholiday" => "holidays, weekends",
        ],
        "page-scanlist-special"=>[
            "weekdays"=>['1'=>'Monday', '2'=>'Tuesday', '3'=>'Wednesday', '4'=>'Thursday', '5'=>'Friday', '6'=>'Saturday', '7'=>'Sunday']
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
            "optsdefaultbreak" => "Common Break Time",
            "optsuseworkschedule"=>"Involve company work schedule in calculations",
            "optsusetimeonlylimitedbyworkday"=>"Use time of break in calculation (it is subtracted)",
            "optsmsgless8hours"=>"Difference between time is less than 8 hours. Is it OK?",
            "optsmsgnowrokersindb" => "No registered barcodes detected. Check Registered Barcodes page",
            
            "optscustomtimeheader"=>"Custom work time for worker (overrides a common schedule)",
            "optscurrentdayselect"=>"Current day",
            "optsnextdayselect"=>"Next day",
            "optscustomtimestart"=>"Start time",
            "optscustomtimeend"=>"End time",
            "optssavecommonschedule"=>"Save Common Company Worktime",
            "optssavecustomworktime"=>"Save Custom Worktime",
            
            "optsmsgnoitem"=>"No item selected for custom worktime",
            "optsmsgnextday"=>"Both datetimes cannot relate to next day",
            "optsmsgtimemismatch"=>"Time start cannot exceed time end",
            "optscustomworktimesuccess"=>"Added new custom work time...",
            "optscustomworktimefailure"=>"Failed to add record about custom schedule, probably one already exists",
            
            "customworktimeremovedOK"=>"Successfully removed a custom work time for worker",
            "customworktimeremovedDBFAILURE"=>"FAILED TO REMOVE CUSTOM WORK TIME. DB QUERY FAILURE. CHECK removeCustomWorkTimeDB()",
            "customworktimeremovedNOAUTH"=>"UNAUTHORIZED",
            
            "optscustomworktimeheaderentity" => "Entry",
            "optscustomworktimeheadertimestart" => "Starting time",
            "optscustomworktimeheadertimeend" => "Finishing time",
        ]
        ),
    "ru" => array(
        "common"=>["header"=>"SM_NM Сканнер",
            "scannedlist"=>"[Список отсканированных элементов]",
            "registeredlist"=>"[Список зарегистрированных элементов]",
            "scannedlist2"=>"[Список отсканированных элементов - Вариант2]",
            "scannedlist3"=>"[Список элементов - Вариант3]",
            "optionsentry" => "[Настройки]",
            "meaningless" => "[Этот пункт ни на что не влияет]",
            "showlist"=>"&#9660; Показать список &#9660;",
            "hidelist"=>"&#9650; Скрыть список &#9650;",
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
            "ncodefieldgender"=>'Поле 4',
            "ncodefieldposition"=>'Должность',
            "ncodefieldmale"=>'муж.',
            "ncodefieldfemale"=>'жен.',
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
            "tableheaderv2"=>"Список фактов сканирования штрихкода - сгруппирован, вариант 2",
            "tableheaderv3"=>"Список фактов сканирования штрихкода - сгруппирован, обработан, вариант 3",
            "scanlisttotal"=>"Всего: ",
            "tooltipartificialentry"=>"Эта запись была добавлена потому что используется график работы компании (ее нету в списке сканирования)",
            "overtimetext"=>"Переработка",
            "listv3timesheet" => "ТАБЕЛЬ ОБЛІКУ РОБОЧОГО ЧАСУ",
            "listv3headernumber" => "N п/п",
            "listv3headerposition" => "Табельний номер",
            "listv3headername" => "П. І. Б., Посада",
            "listv3headeraboutdates" => "Відмітки про явки та неявки за числами місяця (годин)",
            "listv3headerformonth" => "Відпрацьовано за місяць",
            "listv3headerdaysformonth" => "днів",
            "listv3headerhoursformonth" => "годин",
            "listv3headerdetailed" => "з них:",
            "listv3headerovertime" => "надурочно",
            "listv3headernight" => "нічних",
            "listv3headerevening" => "вечірніх",
            "listv3headerholiday" => "вихідних, святкових",
            ],
        "page-scanlist-special"=>[
            "weekdays"=>['1'=>'Понедельник', '2'=>'Вторник', '3'=>'Среда', '4'=>'Четверг', '5'=>'Пятница', '6'=>'Суббота', '7'=>'Воскресенье']
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
            "optsdefaultbreak" => "Перерыв в течении дня",
            "optsuseworkschedule"=>"Применять график работы предприятия при вычислениях",
            "optsusetimeonlylimitedbyworkday"=>"Применять время перерыва в вычислениях (оно вычитается от общего значения для дня)",
            "optsmsgless8hours"=>"Разница между значениями времени составляет меньше 8 часов? Правильно?",
            "optsmsgnowrokersindb" => "Не обнаружено записей о присвоенных штрихкодах. См. [Список зарегистрированных элементов]",
            
            "optscustomtimeheader"=>"Особый рабочий график (переопределяет общий рабочий график)",
            "optscurrentdayselect"=>"Текущий день",
            "optsnextdayselect"=>"Следующий день",
            "optscustomtimestart"=>"Начало работы",
            "optscustomtimeend"=>"Окончание работы",
            "optssavecommonschedule"=>"Сохранить график работы компании",
            "optssavecustomworktime"=>"Сохранить особый график работы",
            
            "optsmsgnoitem"=>"Не выбрана запись для особого графика ",
            "optsmsgnextday"=>"Оба значения времени не могут относиться к следующему дню",
            "optsmsgtimemismatch"=>"Дата и время начала особого графика не может превышать дату и время окончания",
            "optscustomworktimesuccess"=>"Добавлена запись об особом рабочем графике",
            "optscustomworktimefailure"=>"Не получилось добавить запись об особом рабочем графике, возможно запись уже существует",
            
            "customworktimeremovedOK"=>"Запись об особом рабочем времени удалена",
            "customworktimeremovedDBFAILURE"=>"Не получилось удалить Запись об особом рабочем времени, см. в removeCustomWorkTimeDB()",
            "customworktimeremovedNOAUTH"=>"Не получилось удалить Запись об особом рабочем времени, что-то с аутентификацией",
            
            "optscustomworktimeheaderentity" =>  "Запись",
            "optscustomworktimeheadertimestart" => "Время начала",
            "optscustomworktimeheadertimeend" => "Время окончания",
            "optssingleremovebtn" => "Отменить график"
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
