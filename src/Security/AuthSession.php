<?php

namespace SimpleFinance\Security;

use SimpleFinance\Models\User;

class AuthSession
{
    private const REMEMBER_COOKIE_NAME = 'sf_remember';
    private const SESSION_LIFETIME = 2592000; // 30 ngày (30 * 86400)
    private const REMEMBER_LIFETIME = 31536000; // 1 năm (365 * 86400)

    /**
     * Khởi động session với thời gian sống dài hạn và cấu hình cookie an toàn
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        date_default_timezone_set('Asia/Ho_Chi_Minh');

        // Kéo dài thời gian garbage collection của PHP
        @ini_set('session.gc_maxlifetime', (string)self::SESSION_LIFETIME);

        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => self::SESSION_LIFETIME,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            @session_start();
        } else {
            @session_start();
        }
    }

    /**
     * Đăng nhập người dùng và tạo Persistent Remember Cookie
     */
    public static function loginUser(int $userId, array $user, bool $remember = true): void
    {
        self::start();

        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = $user;

        if ($remember) {
            self::setRememberCookie($userId, $user['username'] ?? '');
        }
    }

    /**
     * Tự động đăng nhập từ cookie nhớ phiên nếu session bị hết hạn
     */
    public static function tryAutoLogin(?object $userModel = null): ?array
    {
        self::start();

        // Nếu đã có session hợp lệ
        if (!empty($_SESSION['user_id'])) {
            return $_SESSION['user'] ?? null;
        }

        // Kiểm tra cookie persistent remember
        if (empty($_COOKIE[self::REMEMBER_COOKIE_NAME])) {
            return null;
        }

        try {
            $decrypted = Crypto::decrypt($_COOKIE[self::REMEMBER_COOKIE_NAME]);
            if (!$decrypted) {
                return null;
            }

            $payload = json_decode($decrypted, true);
            if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp'])) {
                return null;
            }

            // Kiểm tra hạn sử dụng của token
            if ($payload['exp'] < time()) {
                self::clearRememberCookie();
                return null;
            }

            if ($userModel === null) {
                $userModel = new User();
            }

            $user = $userModel->findById((int)$payload['uid']);
            if (!$user) {
                self::clearRememberCookie();
                return null;
            }

            // Xác thực khớp username
            if (!empty($payload['username']) && $user['username'] !== $payload['username']) {
                self::clearRememberCookie();
                return null;
            }

            // Phục hồi session đăng nhập thành công
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user'] = $user;

            // Gia hạn cookie thêm 1 năm
            self::setRememberCookie((int)$user['id'], $user['username']);

            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tạo cookie persistent remember bảo mật bằng AES-256-CBC
     */
    public static function setRememberCookie(int $userId, string $username): void
    {
        $payload = [
            'uid'      => $userId,
            'username' => $username,
            'exp'      => time() + self::REMEMBER_LIFETIME,
        ];

        try {
            $encrypted = Crypto::encrypt(json_encode($payload));
            $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            if (!headers_sent()) {
                setcookie(self::REMEMBER_COOKIE_NAME, $encrypted, [
                    'expires'  => time() + self::REMEMBER_LIFETIME,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        } catch (\Throwable $e) {
            // bỏ qua nếu lỗi mã hóa
        }
    }

    /**
     * Xóa cookie persistent remember
     */
    public static function clearRememberCookie(): void
    {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            setcookie(self::REMEMBER_COOKIE_NAME, '', [
                'expires'  => time() - 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[self::REMEMBER_COOKIE_NAME]);
    }

    /**
     * Đăng xuất hoàn toàn, xóa sạch session và cookie nhớ phiên
     */
    public static function logout(): void
    {
        self::start();
        self::clearRememberCookie();

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
