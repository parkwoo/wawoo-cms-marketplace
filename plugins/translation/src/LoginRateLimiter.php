<?php

namespace Wawoo\Plugin\Translation;

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 1800; // 30分
    private const ATTEMPT_CLEANUP_INTERVAL = 3600; // 1時間

    private static $attempts = [];
    private static $lockouts = [];

    /**
     * ログイン試行を記録する
     *
     * @param string $identifier 識別子（IPアドレスまたはユーザー名）
     */
    public static function recordAttempt(string $identifier): void
    {
        self::cleanupOldData();
        
        if (!isset(self::$attempts[$identifier])) {
            self::$attempts[$identifier] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }
        
        self::$attempts[$identifier]['count']++;
        self::$attempts[$identifier]['last_attempt'] = time();
    }

    /**
     * ログイン試行がロックアウトされているかチェック
     *
     * @param string $identifier 識別子
     * @return bool ロックアウトされている場合はtrue
     */
    public static function isLockedOut(string $identifier): bool
    {
        self::cleanupOldData();
        
        // ロックアウトチェック
        if (isset(self::$lockouts[$identifier])) {
            if (self::$lockouts[$identifier]['expires'] > time()) {
                return true;
            }
            // ロックアウト期限切れなら削除
            unset(self::$lockouts[$identifier]);
        }
        
        // 試行回数チェック
        if (isset(self::$attempts[$identifier])) {
            $attempts = self::$attempts[$identifier];
            if ($attempts['count'] >= self::MAX_ATTEMPTS) {
                // ロックアウトを記録
                self::$lockouts[$identifier] = [
                    'expires' => time() + self::LOCKOUT_DURATION,
                    'reason' => 'max_attempts'
                ];
                return true;
            }
        }
        
        return false;
    }

    /**
     * ログイン成功時に試行回数をリセットする
     *
     * @param string $identifier 識別子
     */
    public static function resetAttempts(string $identifier): void
    {
        unset(self::$attempts[$identifier]);
        unset(self::$lockouts[$identifier]);
    }

    /**
     * 残りの試行回数を取得する
     *
     * @param string $identifier 識別子
     * @return int 残り試行回数
     */
    public static function getRemainingAttempts(string $identifier): int
    {
        if (isset(self::$attempts[$identifier])) {
            return max(0, self::MAX_ATTEMPTS - self::$attempts[$identifier]['count']);
        }
        return self::MAX_ATTEMPTS;
    }

    /**
     * ロックアウト残時間を取得する
     *
     * @param string $identifier 識別子
     * @return int 残時間（秒）、ロックアウトされていない場合は0
     */
    public static function getLockoutTimeRemaining(string $identifier): int
    {
        if (isset(self::$lockouts[$identifier])) {
            return max(0, self::$lockouts[$identifier]['expires'] - time());
        }
        return 0;
    }

    /**
     * 古いデータをクリーンアップする
     */
    private static function cleanupOldData(): void
    {
        $now = time();
        
        // 古い試行データをクリーンアップ
        foreach (self::$attempts as $identifier => $data) {
            if ($now - $data['last_attempt'] > self::ATTEMPT_CLEANUP_INTERVAL) {
                unset(self::$attempts[$identifier]);
            }
        }
        
        // 古いロックアウトデータをクリーンアップ
        foreach (self::$lockouts as $identifier => $data) {
            if ($data['expires'] < $now) {
                unset(self::$lockouts[$identifier]);
            }
        }
    }

    /**
     * 現在の状態を取得（デバッグ用）
     *
     * @return array 状態情報
     */
    public static function getStatus(): array
    {
        return [
            'attempts' => self::$attempts,
            'lockouts' => self::$lockouts
        ];
    }

    /**
     * 特定の識別子の情報を取得
     *
     * @param string $identifier 識別子
     * @return array|null 情報、存在しない場合はnull
     */
    public static function getIdentifierInfo(string $identifier): ?array
    {
        $attempts = self::$attempts[$identifier] ?? null;
        $lockout = self::$lockouts[$identifier] ?? null;
        
        if ($attempts || $lockout) {
            return [
                'attempts' => $attempts,
                'lockout' => $lockout
            ];
        }
        
        return null;
    }
}