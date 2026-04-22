<?php
// core/DatabaseFactory.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DB.php';

class DatabaseFactory
{
    public static function make(): DB
    {
        $cfg = require __DIR__ . '/../config/database.php';

        return new DB(
            $cfg['host'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['name'],
            $cfg['port']
        );
    }
}
