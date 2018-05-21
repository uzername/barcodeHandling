<?php
/**
 * How we can add two date intervals in PHP
 * @link http://stackoverflow.com/q/11556731/367456
 * @link http://codepad.viper-7.com/oBW2le
 *
 * NOTE: This code is rough.
 */
class MyDateInterval extends DateInterval
{
    /**
     * @param DateInterval $from
     * @return MyDateInterval
     */
    public static function fromDateInterval(DateInterval $from)
    {
        return new MyDateInterval($from->format('P%yY%dDT%hH%iM%sS'));
    }
    public function add(DateInterval $interval)
    {
        foreach (str_split('ymdhis') as $prop) {
            $this->$prop += $interval->$prop;
        }
        $this->i += (int)($this->s / 60);
        $this->s = $this->s % 60;
        $this->h += (int)($this->i / 60);
        $this->i = $this->i % 60;
    }
}
//php documentation http://fi2.php.net/manual/en/class.dateinterval.php does not show a way to do this
// https://stackoverflow.com/a/29161599/
function compare_dateInterval($interval1, $operator ,$interval2){
    $interval1_str = $interval1->format("%Y%M%D%H%I%S");
    $interval2_str = $interval2->format("%Y%M%D%H%I%S");
    switch($operator){
        case "<":
            return $interval1 < $interval2;
        case ">":
            return $interval1 > $interval2;
        case "==" :
            return $interval1 == $interval2;
        default:
            return NULL;
    }
}
function add_dateInterval($interval1, $interval2){
    //variables
    $new_value= [];
    $carry_val = array(
                    's'=>['value'=>60,'carry_to'=>'i'],
                    'i'=>['value'=>60,'carry_to'=>'h'],
                    'h'=>['value'=>24,'carry_to'=>'d'],
                    'm'=>['value'=>12,'carry_to'=>'y']
                );

    //operator selection
    $operator = ($interval1->invert == $interval2->invert) ? '+' : '-';

    //Set Invert
    if($operator == '-'){
        $new_value['invert'] = compare_dateInterval($interval1,">",$interval2)?$interval1->invert:$interval2->invert;
    }else{
        $new_value['invert'] = $interval1->invert;
    }

    //Evaluate
    foreach( str_split("ymdhis") as $property){
        $expression = 'return '.$interval1->$property.' '.$operator.' '.$interval2->$property.';';
        $new_value[$property] = eval($expression); // raw arithmetic operation
        $new_value[$property] = ($new_value[$property] > 0) ? $new_value[$property] : -$new_value[$property];
        }

    //carry up
    foreach($carry_val as $property => $option){
        if($new_value[$property] >= $option['value']){
            //Modulus
            $new_value[$property] = $new_value[$property] % $option['value'];
            //carry over
            $new_value[$option['carry_to']]++;
        }
    }

    $nv = $new_value;
    $result = new DateInterval("P$nv[y]Y$nv[m]M$nv[d]DT$nv[h]H$nv[i]M$nv[s]S");
    $result->invert = $new_value['invert'];
    return $result;
}
/**
 * The actually used class to store and calculate total and subtotal values of hours
 */
class TotalHourSpan {
    public $hours;
    public $minutes;
    public $seconds;
    function __construct() {
        $this->hours = 0;        $this->minutes = 0;        $this->seconds = 0;
    }
    public function addDateIntervalToThis(DateInterval $in_Interval) {
        assert($in_Interval->m == 0, "Use TotalHourSpan with smaller DateIntervals, within 1 month");
        assert($in_Interval->y == 0, "Use TotalHourSpan with smaller DateIntervals, within 1 month, not a year");
        $this->hours+=$in_Interval->d*24; $this->hours+=$in_Interval->h;
        //add seconds
        
        $this->seconds+=$in_Interval->s;
        $this->minutes+=$in_Interval->i;
        $carryValue = 0; $carryValue2 = 0;
        if ($this->seconds >= 60) {
            $carryValue = intdiv($this->seconds, 60);
            $this->seconds = $this->seconds % 60;
        }
        $this->minutes+=$carryValue;
        if ($this->minutes >= 60) {
            $carryValue2 = intdiv($this->minutes, 60);
            $this->minutes = $this->minutes % 60;
        }
        $this->hours += $carryValue2;
    }
    public function subtractDateIntervalToThis(DateInterval $in_Interval) {
        assert($in_Interval->m == 0, "Use TotalHourSpan with smaller DateIntervals, within 1 month");
        assert($in_Interval->y == 0, "Use TotalHourSpan with smaller DateIntervals, within 1 month, not a year");
        $this->hours-=$in_Interval->d*24; $this->hours-=$in_Interval->h;
        assert($this->hours>=0, "TODO: handle negative values of TotalHourSpan");
        //subtract seconds and minutes                        
        if ($this->seconds < $in_Interval->s) {
            $this->seconds = $this->seconds + 60 - $in_Interval->s;
            $this->minutes-=1;
            if ($this->minutes<0) {
                $this->minutes= 59;
                $this->hours = 0;
            }
        } else {
            $this->seconds -= $in_Interval->s;
        }        
        if ($this->minutes < $in_Interval->i) {
            $this->minutes = $this->minutes + 60 - $in_Interval->i;
            $this->hours -=1;
        } else {
            $this->minutes -= $in_Interval->i;
        }
        assert($this->hours>=0, "TODO: handle negative values of TotalHourSpan");
    }
    public function addTotalHourspanToThis(TotalHourSpan $in_TotalHourspanValue) {
        $this->hours+=$in_TotalHourspanValue->hours;
        $this->minutes+=$in_TotalHourspanValue->minutes;
        $this->seconds+=$in_TotalHourspanValue->seconds;
        $carryValue = 0; $carryValue2 = 0;
        if ($this->seconds >= 60) {
            $carryValue = intdiv($this->seconds, 60);
            $this->seconds = $this->seconds % 60;
        }
        $this->minutes+=$carryValue;
        if ($this->minutes >= 60) {
            $carryValue2 = intdiv($this->minutes, 60);
            $this->minutes = $this->minutes % 60;
        }
        $this->hours += $carryValue2;
    }
    public function myToString() {
        return sprintf("%02d",$this->hours).":".sprintf("%02d",$this->minutes).":".sprintf("%02d",$this->seconds);
    }
    public function __toString() {
        return $this->myToString();
    }
    public function myToFloat() {
        return $this->hours + ($this->minutes + $this->seconds/60.0)/60.0;
    }
}

?>