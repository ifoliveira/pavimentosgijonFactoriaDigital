<?php

namespace App\Service;

final class BotDetectorService
{
    public function isBot(?string $ua): bool
    {
        if (!$ua) return true;

        $patterns = [
            'aiohttp',
            'python',
            'bot',
            'crawler',
            'spider',
            'curl',
            'wget',
            'httpclient',
            'scrapy',
        ];

        foreach ($patterns as $p) {
            if (stripos($ua, $p) !== false) {
                return true;
            }
        }

        return false;
    }
}