<?php
require("../config.php");


$raw_data = file_get_contents("../data/raw/". $year . "_" . $semester . ".html");

$raw_data = explode("</head>", $raw_data)[1];
$raw_data = "<html>".$raw_data;
// $raw_data = str_replace("<body>", "<BODY>", $raw_data);

$raw_data = str_replace("<HR SIZE=2>", "", $raw_data);
$raw_data = str_replace("<HR>", "", $raw_data);
$raw_data = str_replace("<hr>", "", $raw_data);
$raw_data = str_replace("<BR>", "", $raw_data);
$raw_data = str_replace("<br>", "", $raw_data);
$raw_data = str_replace("<br />", "", $raw_data);
$raw_data = str_replace("<P>", "", $raw_data);
$raw_data = str_replace("<p>", "", $raw_data);
$raw_data = str_replace("&nbsp;", "", $raw_data);
$raw_data = str_replace("^", "", $raw_data);
$raw_data = str_replace("</FORM>", "", $raw_data);
$raw_data = str_replace("</form>", "", $raw_data);
$raw_data = str_replace("<CENTER><FONT SIZE=4 FACE=\"Arial\">", "<FONT SIZE=4 FACE=\"Arial\">", $raw_data);
$raw_data = str_replace("<CENTER>", "", $raw_data);
$raw_data = str_replace("</CENTER>", "", $raw_data);
$raw_data = str_replace("</center>", "", $raw_data);

$raw_data = str_replace("COLOR=#0000FF", "", $raw_data);
$raw_data = str_replace("COLOR=#FF00FF", "", $raw_data);
$raw_data = str_replace("SIZE=2", "", $raw_data);
$raw_data = str_replace("SIZE=4", "", $raw_data);
$raw_data = str_replace("COLOR=black", "", $raw_data);
$raw_data = str_replace("</FONT></B></B>", "</FONT></B></CENTER></B>", $raw_data);
$raw_data = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $raw_data);

$raw_data = str_replace("<body>", "<BODY>", $raw_data);
$raw_data = str_replace("</body>", "</BODY>", $raw_data);
$raw_data = str_replace("<TABLE  border>", "<TABLE>", $raw_data);
$raw_data = str_replace("<table  border>", "<TABLE>", $raw_data);
$raw_data = str_replace("<table >", "<TABLE>", $raw_data);
$raw_data = str_replace("</table>", "</TABLE>", $raw_data);
$raw_data = str_replace("<tr>", "<TR>", $raw_data);
$raw_data = str_replace("</tr>", "</TR>", $raw_data);
$raw_data = str_replace("<td>", "<TD>", $raw_data);
$raw_data = str_replace("</td>", "</TD>", $raw_data);
$raw_data = str_replace("</b>", "</B>", $raw_data);
$raw_data = str_replace("<b>", "<B>", $raw_data);

$raw_data = preg_replace("/ +/", " ", $raw_data);
// file_put_contents('test.txt', print_r($raw_data, true));


function count_duration($start, $end) {
    $hour_start = (int) ($start / 100);
    $hour_end = (int) ($end / 100);
    $minute_start = ($start % 100);
    $minute_end = ($end % 100);
    if ($minute_end - $minute_start < 0) {
        return (double) ($hour_end - $hour_start - 1) + (double) ($minute_end - $minute_start + 60)  / 60.0;
    } else {
        return (double) ($hour_end - $hour_start) + (double) ($minute_end - $minute_start) / 60.0;
    }

}


$data =  new SimpleXMLElement($raw_data);
$data = $data->BODY;
$super_data = array();
$course_list = array();
foreach ($data->TABLE as $course) {
    if ($course->TR[0]->TD[0] !== null) { // course
        $course_code = (string) $course->TR[0]->TD[0]->B->FONT[0];
        $course_name = (string) $course->TR[0]->TD[1]->B->FONT[0];
        $course_au   = (string) $course->TR[0]->TD[2]->B->FONT[0];

        array_push($course_list, array(
            "code" => $course_code,
            "name" => $course_name));
    } else { // index of the course

        $index_members = array();
        foreach ($course->TR as $index) {
            if ($index->TD[0] == null) continue; // skip

            if (!empty($index->TD[0]->B )) {
                if (isset($index_member)) {
                    array_push($index_members,array(
                        "index_number" => $index_number,
                        "details" => $index_member));
                    unset($index_member);
                }
                $index_number = (string) $index->TD[0]->B;
                $index_member = array();
            }

            $member_type = (string) $index->TD[1]->B;
            $member_group = (string) $index->TD[2]->B;
            $member_day = (string) $index->TD[3]->B;
            $member_time = (string) $index->TD[4]->B;
            if (empty($member_time)) {
                $member_time_start = "";
                $member_time_end = "";
                $member_time_duration = 0;
            } else {
                $member_time_start = explode("-", $member_time)[0];
                $member_time_end = explode("-", $member_time)[1];
                $member_time_duration = count_duration(intval($member_time_start),intval($member_time_end));
            }

            $member_location = (string) $index->TD[5]->B;
            $member_remarks = (empty($index->TD[6]->B)) ? "" : (string) $index->TD[6]->B; // start on what week?

            $member_flag = 0; // no remarks
            if (stripos($member_remarks, "-") !== false) {
                $member_flag = 0; // all weeks
            } else if (stripos($member_remarks, ",") !== false) {
                if (intval($member_remarks[2]) % 2 === 0) {
                    $member_flag = 2; // even weeks
                } else {
                    $member_flag = 3; // odd weeks
                }
            }

            array_push ($index_member, array(
                "type" => $member_type,
                "group" => $member_group,
                "day" => $member_day,
                "time" => array("full" => $member_time,
                    "start" => $member_time_start,
                    "end" => $member_time_end,
                    "duration" => $member_time_duration),
                "location" => $member_location,
                "flag" => $member_flag,
                "remarks" => $member_remarks));

            //$index_number = $index->TD[0]->B;
            // this will be very dirty
            // 1 course only got 1 table for all index
            // 1 index consists of multiple rows, for different types
            // index starts with td[0] as number, otherwise empty
            // see 2014_2_data_1006_index.txt
            //$index_number = $index->td[0]->b;

        }
        if (isset($index_member)) {
            array_push($index_members, array(
                "index_number" => $index_number,
                "details" => $index_member));
            unset($index_member);
        }
        //$course_index   = $course->tbody->tr;
        $super_data[$course_code] = array("name" => $course_name,
                                          "au" => $course_au,
                                          "index" => $index_members
                                         );
        unset($index_members);

        /*
        array_push($super_data, array("code" => $course_code,
            "name" => $course_name,
            "au" => $course_au,
            "index" => $index_members));
        */
    }


}

file_put_contents("../data/parsed/text/". $year . "_" . $semester . "_data.txt", print_r($super_data, true));
file_put_contents("../data/parsed/json/". $year . "_" . $semester . "_data.json", json_encode($super_data));

file_put_contents("../data/parsed/text/". $year . "_" . $semester . "_course_list.txt", print_r($course_list, true));
file_put_contents("../data/parsed/json/". $year . "_" . $semester . "_course_list.json", json_encode($course_list));

echo "OK";
?>
