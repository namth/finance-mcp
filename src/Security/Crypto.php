<?php

namespace SimpleFinance\Security;

use RuntimeException;

class Crypto
{
    private static ?string $key = null;

    private static function getKey(): string
    {
        if (self::$key === null) {
            $configFile = __DIR__ . '/../../config.php';
            if (!file_exists($configFile)) {
                $configFile = __DIR__ . '/../../config.example.php';
            }

            if (!file_exists($configFile)) {
                throw new RuntimeException("Không tìm thấy file cấu hình config.php");
            }

            $config = require $configFile;
            $rawKey = $config['app_key'] ?? 'simplefinance_default_secret_key_32bytes!!';
            // Chuẩn hóa thành đúng 32 bytes bằng SHA-256
            self::$key = hash('sha256', $rawKey, true);
        }

        return self::$key;
    }

    /**
     * Mã hóa chuỗi văn bản bằng AES-256-CBC
     */
    public static function encrypt(string $plainText): string
    {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($plainText, 'AES-256-CBC', $key, 0, $iv);

        if ($encrypted === false) {
            throw new RuntimeException("Lỗi trong quá trình mã hóa dữ liệu: " . openssl_error_string());
        }

        // Đóng gói IV và ciphertext dạng base64
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Giải mã chuỗi AES-256-CBC
     */
    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false) {
            return null;
        }

        $parts = explode('::', $raw, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$iv, $encrypted] = $parts;
        $key = self::getKey();

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Sinh API Key ngẫu nhiên cho user
     */
    public static function generateApiKey(): string
    {
        return 'sf_usr_' . bin2hex(random_bytes(20));
    }
}
