<?php

namespace Wawoo\Plugin\Translation;

class TOTPAuthenticator
{
    // TOTP設定
    private const DEFAULT_ISSUER = 'wawoo-cms';
    private const DEFAULT_ALGORITHM = 'sha1';
    private const DEFAULT_DIGITS = 6;
    private const DEFAULT_PERIOD = 30;
    private const DEFAULT_WINDOW = 1;

    // バックアップコードの設定
    private const BACKUP_CODE_LENGTH = 8;
    private const BACKUP_CODE_COUNT = 10;

    private $config;
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     *
     * @param array $config 設定
     * @return self
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     *
     * @param array $config 設定
     */
    private function __construct(array $config = [])
    {
        $this->config = array_merge([
            'issuer' => self::DEFAULT_ISSUER,
            'algorithm' => self::DEFAULT_ALGORITHM,
            'digits' => self::DEFAULT_DIGITS,
            'period' => self::DEFAULT_PERIOD,
            'window' => self::DEFAULT_WINDOW
        ], $config);
    }

    /**
     * シークレットキーを生成する
     *
     * @return string 生成されたシークレットキー
     */
    public function generateSecret(): string
    {
        return Base32::encode(random_bytes(16));
    }

    /**
     * TOTPのURLを生成する
     *
     * @param string $secret シークレットキー
     * @param string $username ユーザー名
     * @return string TOTP URL
     */
    public function generateTOTPUrl(string $secret, string $username): string
    {
        $label = sprintf('%s:%s', $this->config['issuer'], urlencode($username));
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s',
            $label,
            $secret,
            $this->config['issuer']
        );
    }

    /**
     * QRコードを生成する
     *
     * @param string $totpUrl TOTP URL
     * @param int $size サイズ
     * @return string QRコード画像のデータURL
     */
    public function generateQRCode(string $totpUrl, int $size = 200): string
    {
        // QRコードライブラリを使用
        if (class_exists('Endroid\\QrCode\\QrCode')) {
            $qrCode = new \Endroid\QrCode\QrCode($totpUrl);
            $qrCode->setSize($size);
            $qrCode->setMargin(10);
            return $qrCode->getDataUri();
        }

        // 外部サービスを使用
        $apiUrl = sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%dx%d&data=%s',
            $size,
            $size,
            urlencode($totpUrl)
        );
        
        $response = file_get_contents($apiUrl);
        if ($response) {
            return 'data:image/png;base64,' . base64_encode($response);
        }
        
        return '';
    }

    /**
     * TOTPコードを検証する
     *
     * @param string $secret シークレットキー
     * @param string $code 検証するコード
     * @param int|null $timestamp タイムスタンプ（nullの場合は現在時刻）
     * @return bool 検証結果
     */
    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $secretBytes = Base32::decode($secret);
        
        // ウィンドウ内のすべてのタイムスロットをチェック
        for ($i = -$this->config['window']; $i <= $this->config['window']; $i++) {
            $timeSlot = floor($timestamp / $this->config['period']) + $i;
            $expectedCode = $this->generateOTP($secretBytes, $timeSlot);
            
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * OTPを生成する
     *
     * @param string $secret シークレットキーのバイト
     * @param int $timeSlot タイムスロット
     * @return string 生成されたOTP
     */
    private function generateOTP(string $secret, int $timeSlot): string
    {
        // タイムスロットをバイト配列に変換
        $timeBytes = pack('N*', $timeSlot);
        
        // HMACを計算
        $hash = hash_hmac($this->config['algorithm'], $timeBytes, $secret, true);
        
        // ダイナミックトリミング
        $offset = ord($hash[strlen($hash) - 1]) & 0xF;
        $truncatedHash = substr($hash, $offset, 4);
        
        // 符号なし32ビット整数に変換
        $otp = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;
        
        // 指定桁数にフォーマット
        return str_pad($otp % pow(10, $this->config['digits']), $this->config['digits'], '0', STR_PAD_LEFT);
    }

    /**
     * バックアップコードを生成する
     *
     * @return array 生成されたバックアップコードの配列
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = '';
            for ($j = 0; $j < self::BACKUP_CODE_LENGTH; $j++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $codes[] = $code;
        }
        
        return $codes;
    }

    /**
     * バックアップコードを使用済みにする
     *
     * @param array $codes バックアップコードの配列
     * @param string $usedCode 使用されたコード
     * @return array 更新されたコードの配列
     */
    public function markBackupCodeUsed(array $codes, string $usedCode): array
    {
        $key = array_search($usedCode, $codes);
        if ($key !== false) {
            $codes[$key] = 'USED-' . $codes[$key];
        }
        return $codes;
    }

    /**
     * バックアップコードが有効かチェック
     *
     * @param array $codes バックアップコードの配列
     * @param string $code 検証するコード
     * @return bool 有効な場合はtrue
     */
    public function isValidBackupCode(array $codes, string $code): bool
    {
        return in_array($code, $codes, true);
    }

    /**
     * ユーザーのTOTP設定を保存
     *
     * @param string $username ユーザー名
     * @param array $totpData TOTPデータ
     */
    public function saveTOTPData(string $username, array $totpData): void
    {
        $storage = new TOTPStorage();
        $storage->save($username, $totpData);
    }

    /**
     * ユーザーのTOTP設定を取得
     *
     * @param string $username ユーザー名
     * @return array|null TOTPデータ、存在しない場合はnull
     */
    public function getTOTPData(string $username): ?array
    {
        $storage = new TOTPStorage();
        return $storage->load($username);
    }

    /**
     * ユーザーのTOTP設定を削除
     *
     * @param string $username ユーザー名
     */
    public function deleteTOTPData(string $username): void
    {
        $storage = new TOTPStorage();
        $storage->delete($username);
    }

    /**
     * TOTPが設定されているかチェック
     *
     * @param string $username ユーザー名
     * @return bool 設定されている場合はtrue
     */
    public function isTOTPEnabled(string $username): bool
    {
        $totpData = $this->getTOTPData($username);
        return $totpData !== null && !empty($totpData['secret']);
    }

    /**
     * TOTP設定を有効化
     *
     * @param string $username ユーザー名
     * @param string $secret シークレットキー
     * @param array $backupCodes バックアップコード
     */
    public function enableTOTP(string $username, string $secret, array $backupCodes): void
    {
        $totpData = [
            'secret' => $secret,
            'backup_codes' => $backupCodes,
            'enabled_at' => time(),
            'last_used' => null
        ];
        
        $this->saveTOTPData($username, $totpData);
    }

    /**
     * TOTP設定を無効化
     *
     * @param string $username ユーザー名
     */
    public function disableTOTP(string $username): void
    {
        $this->deleteTOTPData($username);
    }

    /**
     * TOTP設定の状態を取得
     *
     * @param string $username ユーザー名
     * @return array 設定状態
     */
    public function getTOTPStatus(string $username): array
    {
        $totpData = $this->getTOTPData($username);
        
        if ($totpData === null) {
            return [
                'enabled' => false,
                'created_at' => null,
                'last_used' => null
            ];
        }
        
        return [
            'enabled' => true,
            'created_at' => $totpData['enabled_at'],
            'last_used' => $totpData['last_used'],
            'backup_codes_count' => count(array_filter($totpData['backup_codes'], function($code) {
                return strpos($code, 'USED-') !== 0;
            }))
        ];
    }
}

/**
 * Base32エンコード/デコードクラス
 */
class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PAD_CHAR = '=';

    /**
     * Base32エンコード
     *
     * @param string $data エンコードするデータ
     * @return string エンコードされた文字列
     */
    public static function encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        
        $result = '';
        $groups = str_split($binary, 5);
        foreach ($groups as $group) {
            $group = str_pad($group, 5, '0');
            $index = bindec($group);
            $result .= self::ALPHABET[$index];
        }
        
        $padding = (8 - (strlen($binary) % 8)) % 8;
        if ($padding > 0) {
            $result .= str_repeat(self::PAD_CHAR, $padding / 5);
        }
        
        return $result;
    }

    /**
     * Base32デコード
     *
     * @param string $data デコードするデータ
     * @return string デコードされたデータ
     */
    public static function decode(string $data): string
    {
        $data = strtoupper(str_replace(self::PAD_CHAR, '', $data));
        $binary = '';
        foreach (str_split($data) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        
        $result = '';
        $groups = str_split($binary, 8);
        foreach ($groups as $group) {
            if (strlen($group) === 8) {
                $result .= chr(bindec($group));
            }
        }
        
        return $result;
    }
}

/**
 * TOTPストレージクラス
 */
class TOTPStorage
{
    private const STORAGE_FILE = 'cache/totp_storage.json';
    
    /**
     * TOTPデータを保存
     *
     * @param string $username ユーザー名
     * @param array $data データ
     */
    public function save(string $username, array $data): void
    {
        $storage = $this->loadAll();
        $storage[$username] = $data;
        
        $this->writeStorage($storage);
    }
    
    /**
     * TOTPデータをロード
     *
     * @param string $username ユーザー名
     * @return array|null データ、存在しない場合はnull
     */
    public function load(string $username): ?array
    {
        $storage = $this->loadAll();
        return $storage[$username] ?? null;
    }
    
    /**
     * TOTPデータを削除
     *
     * @param string $username ユーザー名
     */
    public function delete(string $username): void
    {
        $storage = $this->loadAll();
        unset($storage[$username]);
        $this->writeStorage($storage);
    }
    
    /**
     * すべてのTOTPデータをロード
     *
     * @return array すべてのデータ
     */
    private function loadAll(): array
    {
        if (!file_exists(dirname(self::STORAGE_FILE))) {
            mkdir(dirname(self::STORAGE_FILE), 0755, true);
        }
        
        if (!file_exists(self::STORAGE_FILE)) {
            return [];
        }
        
        $content = file_get_contents(self::STORAGE_FILE);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return $data ?: [];
    }
    
    /**
     * ストレージに書き込む
     *
     * @param array $data データ
     */
    private function writeStorage(array $data): void
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(self::STORAGE_FILE, $content);
    }
}