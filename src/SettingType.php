<?php

declare(strict_types=1);

namespace Vaslv\LaravelSettings;

enum SettingType: string
{
    case BOOLEAN = 'boolean';
    case FLOAT = 'float';
    case INTEGER = 'integer';
    case JSON = 'json';
    case MARKDOWN = 'markdown';
    case STRING = 'string';
}
