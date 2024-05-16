<?php

namespace AcMarche\PivotSearch\Data;

class Cleaner
{
    public static function cleandata(string $data): string
    {
        $data = wp_strip_all_tags($data);
        $data = preg_replace('#&nbsp;#', ' ', $data);
        $data = preg_replace('#&amp;#', ' ', $data); //&
        $data = preg_replace('#&#', ' ', $data);
        $data = preg_replace('#<#', '', $data);
        $data = preg_replace('#’#', "'", $data);
        $data = preg_replace(["#\(#", "#\)#"], '', $data);
        $special_chars = [
            '?',
            '[',
            ']',
            '/',
            '\\',
            '=',
            '<',
            '>',
            ':',
            ';',
            ',',
            '"',
            '&',
            '$',
            '#',
            '*',
            '|',
            '~',
            '`',
            '!',
            '{',
            '}',
            \chr(0),
        ];
        $data = str_replace($special_chars, ' ', $data);

        return trim($data);
    }
}
