<?php

namespace ZuggrCloud;

class Helpers
{
    /**
     * Parse uri into a specific format
     *
     * @param string $uri
     * @param boolean $withSlash
     * @return string
     */
    public static function parseURI(string $uri, bool $withSlash = true): string
    {
        if ($uri[0] == '/' && !$withSlash) {
            $uri = ltrim($uri, '/');
        } elseif ($uri[0] != '/' && $withSlash) {
            $uri = '/'.$uri;
        }
        return rtrim($uri, '/');
    }
}
