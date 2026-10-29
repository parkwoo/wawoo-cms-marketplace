<?php

namespace Wawoo\Plugin\Translation;

class PasswordValidator
{
    // パスワードの強度レベル
    public const STRENGTH_WEAK = 0;
    public const STRENGTH_FAIR = 1;
    public const STRENGTH_GOOD = 2;
    public const STRENGTH_STRONG = 3;
    public const STRENGTH_VERY_STRONG = 4;

    // デフォルトの設定
    private const DEFAULT_MIN_LENGTH = 8;
    private const DEFAULT_REQUIRE_UPPER = true;
    private const DEFAULT_REQUIRE_LOWER = true;
    private const DEFAULT_REQUIRE_NUMBER = true;
    private const DEFAULT_REQUIRE_SPECIAL = false;
    private const DEFAULT_MAX_LENGTH = 128;

    private $config;

    /**
     * コンストラクタ
     *
     * @param array $config カスタム設定
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'min_length' => self::DEFAULT_MIN_LENGTH,
            'require_uppercase' => self::DEFAULT_REQUIRE_UPPER,
            'require_lowercase' => self::DEFAULT_REQUIRE_LOWER,
            'require_number' => self::DEFAULT_REQUIRE_NUMBER,
            'require_special' => self::DEFAULT_REQUIRE_SPECIAL,
            'max_length' => self::DEFAULT_MAX_LENGTH,
            'blacklist' => $this->getDefaultPasswordBlacklist()
        ], $config);
    }

    /**
     * パスワードの有効性を検証する
     *
     * @param string $password 検証するパスワード
     * @return array 検証結果
     */
    public function validate(string $password): array
    {
        $result = [
            'valid' => true,
            'strength' => self::STRENGTH_WEAK,
            'errors' => [],
            'warnings' => []
        ];

        // 長さのチェック
        if (strlen($password) < $this->config['min_length']) {
            $result['valid'] = false;
            $result['errors'][] = sprintf('パスワードは%d文字以上必要です', $this->config['min_length']);
        }

        if (strlen($password) > $this->config['max_length']) {
            $result['valid'] = false;
            $result['errors'][] = sprintf('パスワードは%d文字以下にしてください', $this->config['max_length']);
        }

        // 文字種のチェック
        if ($this->config['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'パスワードには大文字を含める必要があります';
        }

        if ($this->config['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'パスワードには小文字を含める必要があります';
        }

        if ($this->config['require_number'] && !preg_match('/[0-9]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'パスワードには数字を含める必要があります';
        }

        if ($this->config['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'パスワードには特殊文字を含める必要があります';
        }

        // ブラックリストチェック
        if ($this->isPasswordBlacklisted($password)) {
            $result['valid'] = false;
            $result['errors'][] = '使用できないパスワードです';
        }

        // 強度の評価
        if ($result['valid']) {
            $result['strength'] = $this->calculateStrength($password);
            
            // 強度に基づく警告
            if ($result['strength'] < self::STRENGTH_GOOD) {
                $result['warnings'][] = 'パスワードの強度が低いです。より複雑なパスワードをお勧めします。';
            }
        }

        return $result;
    }

    /**
     * パスワードの強度を計算する
     *
     * @param string $password 評価するパスワード
     * @return int 強度スコア
     */
    private function calculateStrength(string $password): int
    {
        $score = 0;
        $length = strlen($password);

        // 長さによるスコア
        if ($length >= 12) $score += 2;
        elseif ($length >= 8) $score += 1;

        // 文字種の多様性によるスコア
        $characterTypes = 0;
        if (preg_match('/[a-z]/', $password)) $characterTypes++;
        if (preg_match('/[A-Z]/', $password)) $characterTypes++;
        if (preg_match('/[0-9]/', $password)) $characterTypes++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $characterTypes++;

        $score += $characterTypes;

        // 特定のパターンによるペナルティ
        if (preg_match('/^(password|123456|qwerty|abc123)/i', $password)) {
            $score = max(0, $score - 2);
        }

        if (preg_match('/^(.)\1{2,}/', $password)) {
            $score = max(0, $score - 1);
        }

        // スコアに基づいた強度の決定
        if ($score >= 6) return self::STRENGTH_VERY_STRONG;
        if ($score >= 5) return self::STRENGTH_STRONG;
        if ($score >= 4) return self::STRENGTH_GOOD;
        if ($score >= 3) return self::STRENGTH_FAIR;
        return self::STRENGTH_WEAK;
    }

    /**
     * パスワードがブラックリストに含まれているかチェック
     *
     * @param string $password チェックするパスワード
     * @return bool ブラックリストに含まれている場合はtrue
     */
    private function isPasswordBlacklisted(string $password): bool
    {
        $lowerPassword = strtolower($password);
        
        foreach ($this->config['blacklist'] as $blacklisted) {
            if (strtolower($blacklisted) === $lowerPassword) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * デフォルトのパスワードブラックリストを取得
     *
     * @return array ブラックリスト
     */
    private function getDefaultPasswordBlacklist(): array
    {
        return [
            'password', '123456', '12345678', '123456789', '1234567890',
            'qwerty', 'abc123', 'admin', 'letmein', 'welcome', 'monkey',
            'password1', 'sunshine', 'master', 'hello', 'login', 'admin123',
            'password123', 'qwerty123', 'admin123', 'welcome123'
        ];
    }

    /**
     * パスワードの強度を文字列で取得
     *
     * @param int $strength スコア
     * @return string 強度の文字列表現
     */
    public static function getStrengthText(int $strength): string
    {
        switch ($strength) {
            case self::STRENGTH_WEAK:
                return '弱';
            case self::STRENGTH_FAIR:
                return '普通';
            case self::STRENGTH_GOOD:
                return '良好';
            case self::STRENGTH_STRONG:
                return '強';
            case self::STRENGTH_VERY_STRONG:
                return '非常に強';
            default:
                return '不明';
        }
    }

    /**
     * パスワードの強度の色を取得
     *
     * @param int $strength スコア
     * @return string CSSクラス名
     */
    public static function getStrengthClass(int $strength): string
    {
        switch ($strength) {
            case self::STRENGTH_WEAK:
                return 'text-red-600';
            case self::STRENGTH_FAIR:
                return 'text-orange-600';
            case self::STRENGTH_GOOD:
                return 'text-yellow-600';
            case self::STRENGTH_STRONG:
                return 'text-green-600';
            case self::STRENGTH_VERY_STRONG:
                return 'text-green-800';
            default:
                return 'text-gray-600';
        }
    }

    /**
     * 推奨パスワードを生成する
     *
     * @param int $length 長さ
     * @return string 生成されたパスワード
     */
    public static function generateStrongPassword(int $length = 16): string
    {
        if ($length < 8) $length = 8;
        if ($length > 128) $length = 128;

        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = '';
        $charTypes = [
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'number' => '0123456789',
            'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?'
        ];

        // 各文字種を少なくとも1つ含める
        $password .= $charTypes['lowercase'][random_int(0, 25)];
        $password .= $charTypes['uppercase'][random_int(0, 25)];
        $password .= $charTypes['number'][random_int(0, 9)];
        $password .= $charTypes['special'][random_int(0, strlen($charTypes['special']) - 1)];

        // 残りの文字をランダムに追加
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // シャッフル
        $password = str_shuffle($password);

        return $password;
    }
}