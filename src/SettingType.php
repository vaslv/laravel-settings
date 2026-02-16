<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

enum SettingType: string
{
    case STRING = 'string';
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case JSON = 'json';
    case MARKDOWN = 'markdown';
}
