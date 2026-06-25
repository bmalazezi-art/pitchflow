<?php

namespace App\Services;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $normalized = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+'.substr($normalized, 2);
        }

        return $normalized;
    }
}
