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
        KOV_FORMAT = "%4s%1s%2s";

    public const SCHOOL_REGEX = '/^[0-9]{9}$/',
        KOV_REGEX = '/^[0-9a-zA-Z]{7}$/',
        KODFAK_REGEX = '/^[0-9]{4}$/',
        EDUID_REGEX = '/^[0-9]{9}$/',
        KOV_SCHOOL_REGEX = '/^[0-9]{2}\.[0-9a-zA-Z]{9}\.[0-9a-zA-Z]{7}$/';
}
