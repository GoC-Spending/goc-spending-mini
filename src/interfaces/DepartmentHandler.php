<?php

namespace GoCSpending\Interfaces;

interface DepartmentHandler {
    public static function scrape();

    public static function parse($html);
}
