<?php

namespace Wawoo\Plugin\Translation;

class CSRFTokenManager
{
    private const TOKEN_LENGTH = 32;
    private const TOKEN_SESSION_KEY = 'csrf_token';
    private const TOKEN_LIFETIME = 3600; // 1時間

    /**
     * CSRFトークンを生成する
     *
     * @return string 生成されたトークン
     */
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_SESSION_KEY] = [
            'token' => $token,
            'expires' => time() + self::TOKEN_LIFETIME
        ];
        return $token;
    }

    /**
     * 現在のトークンを取得する
     *
     * @return string|null トークン、存在しない場合はnull
     */
    public static function getCurrentToken(): ?string
    {
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return null;
        }

        $sessionData = $_SESSION[self::TOKEN_SESSION_KEY];
        
        // トークンの有効性をチェック
        if ($sessionData['expires'] < time()) {
            self::invalidateToken();
            return null;
        }

        return $sessionData['token'];
    }

    /**
     * トークンの有効性を検証する
     *
     * @param string $token 検証するトークン
     * @return bool 有効な場合はtrue
     */
    public static function validate(string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        $sessionData = $_SESSION[self::TOKEN_SESSION_KEY];
        
        // トークンの有効性をチェック
        if ($sessionData['expires'] < time()) {
            self::invalidateToken();
            return false;
        }

        // ハッシュ比較でタイミング攻撃を防ぐ
        return hash_equals($sessionData['token'], $token);
    }

    /**
     * トークンを無効化する
     */
    public static function invalidateToken(): void
    {
        unset($_SESSION[self::TOKEN_SESSION_KEY]);
    }

    /**
     * フォーム用の隠しフィールドを生成する
     *
     * @return string HTML
     */
    public static function generateFormField(): string
    {
        $token = self::generate();
        return sprintf('<input type="hidden" name="csrf_token" value="%s">', htmlspecialchars($token, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * AJAXリクエスト用のトークンを取得する
     *
     * @return array トークン情報
     */
    public static function getTokenForAjax(): array
    {
        $token = self::generate();
        return [
            'token' => $token,
            'expires' => time() + self::TOKEN_LIFETIME
        ];
    }

    /**
     * トークンの有効期限を延長する
     */
    public static function extendTokenLifetime(): void
    {
        if (isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            $_SESSION[self::TOKEN_SESSION_KEY]['expires'] = time() + self::TOKEN_LIFETIME;
        }
    }

    /**
     * 古いトークンをクリーンアップする
     */
    public static function cleanupExpiredTokens(): void
    {
        if (isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            if ($_SESSION[self::TOKEN_SESSION_KEY]['expires'] < time()) {
                self::invalidateToken();
            }
        }
    }
}