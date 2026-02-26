<?php

if (!function_exists('formatTimeRange')) {
    function formatTimeRange(string $timeFrom, string $timeTo): string
    {
        $from = substr($timeFrom, 0, 5);
        $to = substr($timeTo, 0, 5);
        if (substr($from, -3) === ':00') {
            $from = substr($from, 0, -3);
        }
        if (substr($to, -3) === ':00') {
            $to = substr($to, 0, -3);
        }
        return $from . '-' . $to . ' Uhr';
    }
}

if (!function_exists('formatTime')) {
    /** Einzelne Uhrzeit z. B. "13 Uhr" oder "13:30 Uhr" */
    function formatTime(string $time): string
    {
        $t = substr($time, 0, 5);
        if (substr($t, -3) === ':00') {
            $t = substr($t, 0, -3);
        }
        return $t . ' Uhr';
    }
}
