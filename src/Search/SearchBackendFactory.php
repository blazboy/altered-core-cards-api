<?php

namespace App\Search;

/**
 * The factory in charge of choosing the SearchBackendInterface implementation to choose
 */
class SearchBackendFactory
{
    public static function create(string $url, $meilisearchBackendClass, $nullSearchBackendClass): SearchBackendInterface
    {
        if($url === "") {
            return $nullSearchBackendClass;
        }
        return $meilisearchBackendClass;
    }
}
