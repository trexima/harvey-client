<?php

declare(strict_types = 1);

namespace Trexima\HarveyClient;

/**
 * Formats for printf functions and regular expresions
 * defining structure of complex codes.
 */
final class Format
{
    public const KOV_SCHOOL_FORMAT = "%2s.%9s.%7s",
        KOV_SCHOOL_REGEX = '/^[0-9]{2}\.[0-9a-zA-Z]{9}\.[0-9a-zA-Z]{7}$/';
}
