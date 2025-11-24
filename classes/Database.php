<?php

/**
 * Backwards-compatible loader for the Database singleton.
 * Several admin pages still include this file, so we simply
 * bootstrap the original implementation from config/database.php.
 */
require_once __DIR__ . '/../config/database.php';
