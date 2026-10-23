<?php

function loadAppEnvironment()
{
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) return;

    $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (!is_array($values)) return;

    foreach ($values as $name => $value) {
        if (!is_string($name) || getenv($name) !== false) continue;
        $value = (string)$value;
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

function appEnvironmentValue($name, $default = '')
{
    $value = getenv($name);
    return $value === false ? $default : trim((string)$value);
}

