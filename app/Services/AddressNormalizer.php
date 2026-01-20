<?php

namespace App\Services;

class AddressNormalizer
{
    /**
     * Find a UK postcode in the given text.
     *
     * @param string $text
     * @return string|null
     */
    public function findPostcode(string $text): ?string
    {
        // UK postcode regex pattern
        $pattern = '/\b([A-Z]{1,2}\d{1,2}[A-Z]?\s?\d[A-Z]{2})\b/i';
        
        if (preg_match($pattern, $text, $matches)) {
            return strtoupper($matches[1]);
        }
        
        return null;
    }

    /**
     * Extract house number and street name from address text.
     *
     * @param string $address
     * @return array
     */
    public function extractHouseAndStreet(string $address): array
    {
        $result = [
            'house_number' => null,
            'street_name' => null,
        ];

        // Remove postcode first
        $postcode = $this->findPostcode($address);
        if ($postcode) {
            $address = str_replace($postcode, '', $address);
        }

        // Trim and clean
        $address = trim($address);
        $address = preg_replace('/\s+/', ' ', $address);

        // Try to extract house number (digits at the start, possibly with letter suffix)
        if (preg_match('/^(\d+[A-Za-z]?)\s+(.+)$/', $address, $matches)) {
            $result['house_number'] = $matches[1];
            $result['street_name'] = trim($matches[2]);
        } else {
            // No clear house number, treat entire string as street name
            $result['street_name'] = $address;
        }

        return $result;
    }

    /**
     * Normalize text for searching: transliterate, remove punctuation, collapse spaces, lowercase.
     *
     * @param string $text
     * @return string
     */
    public function normText(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text);
        
        // Transliteration - replace common accented characters
        $transliteration = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $text = strtr($text, $transliteration);
        
        // Remove punctuation except spaces
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Collapse multiple spaces into one
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }

    /**
     * Make a normalized key from extracted address parts.
     *
     * @param array $parts
     * @return string
     */
    public function makeKey(array $parts): string
    {
        $key = '';
        
        if (!empty($parts['street_name'])) {
            $key = $this->normText($parts['street_name']);
        }
        
        return $key;
    }
}
