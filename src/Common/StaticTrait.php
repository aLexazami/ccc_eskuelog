<?php

namespace src\Common;

use Exception;

trait EntQuotes
{
    # Encrypting HTML values
    public function ent_quotes($string)
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

trait SecurityTrait
{
    /**
     * Encodes an integer ID into a secure, randomized, case-insensitive hexadecimal string.
     */
    public function trait_encode($id, $secret_key = "CCC_Secret_Salt_2026_!")
    {
        try {
            $key = hash('sha256', $secret_key, true);

            // Generate a random 16-byte initialization vector for CBC mode
            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($iv_length);

            $encrypted = openssl_encrypt($id, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

            // Combine the IV and encrypted payload together so it can be decoded later
            return bin2hex($iv . $encrypted);
        } catch (Exception $e) {
            error_log("Encryption failure: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Decodes the randomized hex string back into the original integer ID.
     */
    public function trait_decode($slug, $secret_key = "CCC_Secret_Salt_2026_!")
    {
        if (empty($slug) || !ctype_xdigit($slug)) return 0;

        try {
            $key = hash('sha256', $secret_key, true);
            $binary_data = @hex2bin($slug);
            if ($binary_data === false) return 0;

            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            if (strlen($binary_data) <= $iv_length) return 0;

            // Extract the IV and the actual encrypted text block
            $iv = substr($binary_data, 0, $iv_length);
            $encrypted = substr($binary_data, $iv_length);

            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) return 0;

            $clean_id = trim($decrypted);
            return (is_numeric($clean_id)) ? (int)$clean_id : 0;
        } catch (Exception $e) {
            error_log("Decryption failure: " . $e->getMessage());
            return 0;
        }
    }
}
