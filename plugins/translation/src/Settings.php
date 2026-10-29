<?php
namespace Wawoo\Plugin\Translation;

/**
 * Settings page for translation plugin
 */
class Settings
{
    /**
     * Handle settings page
     */
    public function handle(?string $root): void
    {
        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            $this->showLogin();
            return;
        }
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
        
        // Display settings page
        $this->displaySettings();
    }
    
    /**
     * Check if user is authenticated
     */
    private function isAuthenticated(): bool
    {
        return isset($_SESSION['admin']) && $_SESSION['admin'];
    }
    
    /**
     * Show login form
     */
    private function showLogin(): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>ログイン - 翻訳プラグイン設定</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    margin: 0;
                    padding: 40px;
                    background-color: #f5f5f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                }
                .login-form {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    width: 100%;
                    max-width: 400px;
                }
                h1 {
                    color: #333;
                    margin-bottom: 30px;
                    text-align: center;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 500;
                    color: #333;
                }
                input {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                button {
                    background-color: #007cba;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    width: 100%;
                }
                button:hover {
                    background-color: #005a9e;
                }
                .error {
                    background-color: #f8d7da;
                    color: #721c24;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="login-form">
                <h1>ログイン</h1>
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="error"><?php echo htmlspecialchars($_SESSION['login_error']); ?></div>
                    <?php unset($_SESSION['login_error']); ?>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">ユーザー名</label>
                        <input type="text" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">パスワード</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                    <button type="submit">ログイン</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Handle POST request
     */
    private function handlePostRequest(): void
    {
        if (isset($_POST['username']) && isset($_POST['password'])) {
            // Simple authentication - in production, use proper authentication
            if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin') {
                $_SESSION['admin'] = true;
                $_SESSION['username'] = $_POST['username'];
                header('Location: /admin/translation/settings');
                exit;
            } else {
                $_SESSION['login_error'] = 'ユーザー名またはパスワードが正しくありません。';
                header('Location: /admin/translation/settings');
                exit;
            }
        }
    }
    
    /**
     * Display settings page
     */
    private function displaySettings(): void
    {
        $config = ConfigManager::getAll();
        
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>翻訳プラグイン設定</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #333;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #007cba;
                    padding-bottom: 10px;
                }
                .section {
                    margin-bottom: 30px;
                    padding: 20px;
                    border: 1px solid #e0e0e0;
                    border-radius: 5px;
                }
                .section h2 {
                    margin-top: 0;
                    color: #555;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 500;
                    color: #333;
                }
                input, select, textarea {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                textarea {
                    height: 100px;
                    resize: vertical;
                }
                button {
                    background-color: #007cba;
                    color: white;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    margin-right: 10px;
                }
                button:hover {
                    background-color: #005a9e;
                }
                button.secondary {
                    background-color: #6c757d;
                }
                button.secondary:hover {
                    background-color: #5a6268;
                }
                .success {
                    background-color: #d4edda;
                    color: #155724;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
                .error {
                    background-color: #f8d7da;
                    color: #721c24;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                }
                .tabs {
                    display: flex;
                    margin-bottom: 20px;
                    border-bottom: 1px solid #e0e0e0;
                }
                .tab {
                    padding: 10px 20px;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    margin-right: 10px;
                }
                .tab.active {
                    border-bottom-color: #007cba;
                    color: #007cba;
                }
                .tab-content {
                    display: none;
                }
                .tab-content.active {
                    display: block;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>翻訳プラグイン設定</h1>
                
                <div class="tabs">
                    <div class="tab active" onclick="showTab('basic')">基本設定</div>
                    <div class="tab" onclick="showTab('advanced')">高度な設定</div>
                    <div class="tab" onclick="showTab('custom')">カスタムプロンプト</div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
                        <?php echo htmlspecialchars($_SESSION['message']); ?>
                    </div>
                    <?php unset($_SESSION['message']); ?>
                <?php endif; ?>
                
                <form method="POST">
                    <div id="basic" class="tab-content active">
                        <div class="section">
                            <h2>基本設定</h2>
                            
                            <div class="form-group">
                                <label for="enabled">プラグインを有効化</label>
                                <select name="enabled" id="enabled">
                                    <option value="1" <?php echo $config['enabled'] ? 'selected' : ''; ?>>有効</option>
                                    <option value="0" <?php echo !$config['enabled'] ? 'selected' : ''; ?>>無効</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="source_language">ソース言語</label>
                                <select name="source_language" id="source_language">
                                    <option value="ja" <?php echo $config['source_language'] === 'ja' ? 'selected' : ''; ?>>日本語</option>
                                    <option value="en" <?php echo $config['source_language'] === 'en' ? 'selected' : ''; ?>>英語</option>
                                    <option value="zh" <?php echo $config['source_language'] === 'zh' ? 'selected' : ''; ?>>中国語</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="target_language">対象言語</label>
                                <select name="target_language" id="target_language">
                                    <option value="en" <?php echo $config['target_language'] === 'en' ? 'selected' : ''; ?>>英語</option>
                                    <option value="ja" <?php echo $config['target_language'] === 'ja' ? 'selected' : ''; ?>>日本語</option>
                                    <option value="zh" <?php echo $config['target_language'] === 'zh' ? 'selected' : ''; ?>>中国語</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="provider">APIプロバイダ</label>
                                <select name="provider" id="provider" onchange="updateModelOptions()">
                                    <option value="openai" <?php echo $config['provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                    <option value="anthropic" <?php echo $config['provider'] === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="model">モデル</label>
                                <select name="model" id="model">
                                    <?php if ($config['provider'] === 'openai'): ?>
                                        <option value="gpt-3.5-turbo" <?php echo $config['model'] === 'gpt-3.5-turbo' ? 'selected' : ''; ?>>GPT-3.5 Turbo</option>
                                        <option value="gpt-4" <?php echo $config['model'] === 'gpt-4' ? 'selected' : ''; ?>>GPT-4</option>
                                        <option value="gpt-4-turbo" <?php echo $config['model'] === 'gpt-4-turbo' ? 'selected' : ''; ?>>GPT-4 Turbo</option>
                                    <?php else: ?>
                                        <option value="claude-3-haiku-20240307" <?php echo $config['model'] === 'claude-3-haiku-20240307' ? 'selected' : ''; ?>>Claude 3 Haiku</option>
                                        <option value="claude-3-sonnet-20240229" <?php echo $config['model'] === 'claude-3-sonnet-20240229' ? 'selected' : ''; ?>>Claude 3 Sonnet</option>
                                        <option value="claude-3-opus-20240229" <?php echo $config['model'] === 'claude-3-opus-20240229' ? 'selected' : ''; ?>>Claude 3 Opus</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="api_key">APIキー</label>
                                <input type="password" name="api_key" id="api_key" value="<?php echo htmlspecialchars($config['api_key']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div id="advanced" class="tab-content">
                        <div class="section">
                            <h2>高度な設定</h2>
                            
                            <div class="form-group">
                                <label for="auto_translate">自動翻訳を有効化</label>
                                <select name="auto_translate" id="auto_translate">
                                    <option value="1" <?php echo $config['auto_translate'] ? 'selected' : ''; ?>>有効</option>
                                    <option value="0" <?php echo !$config['auto_translate'] ? 'selected' : ''; ?>>無効</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="validate_translation">翻訳検証を有効化</label>
                                <select name="validate_translation" id="validate_translation">
                                    <option value="1" <?php echo $config['validate_translation'] ? 'selected' : ''; ?>>有効</option>
                                    <option value="0" <?php echo !$config['validate_translation'] ? 'selected' : ''; ?>>無効</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="fallback_to_original">翻訳失敗時は原文を表示</label>
                                <select name="fallback_to_original" id="fallback_to_original">
                                    <option value="1" <?php echo $config['fallback_to_original'] ? 'selected' : ''; ?>>有効</option>
                                    <option value="0" <?php echo !$config['fallback_to_original'] ? 'selected' : ''; ?>>無効</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_retries">最大リトライ数</label>
                                <input type="number" name="max_retries" id="max_retries" value="<?php echo $config['max_retries']; ?>" min="1" max="10">
                            </div>
                            
                            <div class="form-group">
                                <label for="timeout">タイムアウト（秒）</label>
                                <input type="number" name="timeout" id="timeout" value="<?php echo $config['timeout']; ?>" min="10" max="300">
                            </div>
                            
                            <div class="form-group">
                                <label for="batch_size">バッチサイズ</label>
                                <input type="number" name="batch_size" id="batch_size" value="<?php echo $config['batch_size']; ?>" min="1" max="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="excluded_fields">除外フィールド（カンマ区切り）</label>
                                <input type="text" name="excluded_fields" id="excluded_fields" value="<?php echo implode(',', $config['excluded_fields']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div id="custom" class="tab-content">
                        <div class="section">
                            <h2>カスタムプロンプト</h2>
                            
                            <div class="form-group">
                                <label for="default_prompt">デフォルトプロンプト</label>
                                <textarea name="default_prompt" id="default_prompt"><?php echo htmlspecialchars($config['custom_prompts']['default']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="technical_prompt">技術系プロンプト</label>
                                <textarea name="technical_prompt" id="technical_prompt"><?php echo htmlspecialchars($config['custom_prompts']['technical']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="casual_prompt">カジュアルプロンプト</label>
                                <textarea name="casual_prompt" id="casual_prompt"><?php echo htmlspecialchars($config['custom_prompts']['casual']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit">設定を保存</button>
                    <button type="button" class="secondary" onclick="testConnection()">接続テスト</button>
                    <button type="button" class="secondary" onclick="exportConfig()">設定をエクスポート</button>
                    <button type="button" class="secondary" onclick="importConfig()">設定をインポート</button>
                </form>
            </div>
            
            <script>
                function showTab(tabName) {
                    var tabs = document.querySelectorAll('.tab');
                    var contents = document.querySelectorAll('.tab-content');
                    
                    tabs.forEach(tab => tab.classList.remove('active'));
                    contents.forEach(content => content.classList.remove('active'));
                    
                    document.querySelector('.tab[onclick="showTab(\'' + tabName + '\')"]').classList.add('active');
                    document.getElementById(tabName).classList.add('active');
                }
                
                function updateModelOptions() {
                    var provider = document.getElementById('provider').value;
                    var modelSelect = document.getElementById('model');
                    
                    modelSelect.innerHTML = '';
                    
                    if (provider === 'openai') {
                        modelSelect.innerHTML = `
                            <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                            <option value="gpt-4">GPT-4</option>
                            <option value="gpt-4-turbo">GPT-4 Turbo</option>
                        `;
                    } else {
                        modelSelect.innerHTML = `
                            <option value="claude-3-haiku-20240307">Claude 3 Haiku</option>
                            <option value="claude-3-sonnet-20240229">Claude 3 Sonnet</option>
                            <option value="claude-3-opus-20240229">Claude 3 Opus</option>
                        `;
                    }
                }
                
                function testConnection() {
                    const formData = new FormData(document.querySelector('form'));
                    const params = new URLSearchParams();
                    
                    for (let [key, value] of formData.entries()) {
                        params.append(key, value);
                    }
                    
                    fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=test_connection', {
                        method: 'POST',
                        body: params
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('接続テスト成功: ' + data.message);
                        } else {
                            alert('接続テスト失敗: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('エラー: ' + error.message);
                    });
                }
                
                function exportConfig() {
                    const formData = new FormData(document.querySelector('form'));
                    const params = new URLSearchParams();
                    
                    for (let [key, value] of formData.entries()) {
                        params.append(key, value);
                    }
                    
                    fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=export_config', {
                        method: 'POST',
                        body: params
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const blob = new Blob([data.config], { type: 'application/json' });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'translation_config.json';
                            a.click();
                            URL.revokeObjectURL(url);
                        } else {
                            alert('エクスポートに失敗しました: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('エラー: ' + error.message);
                    });
                }
                
                function importConfig() {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = '.json';
                    
                    input.onchange = function(e) {
                        const file = e.target.files[0];
                        const reader = new FileReader();
                        
                        reader.onload = function(event) {
                            const formData = new FormData();
                            formData.append('config', event.target.result);
                            
                            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=import_config', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('インポートに成功しました。ページを更新してください。');
                                } else {
                                    alert('インポートに失敗しました: ' + data.message);
                                }
                            })
                            .catch(error => {
                                alert('エラー: ' + error.message);
                            });
                        };
                        
                        reader.readAsText(file);
                    };
                    
                    input.click();
                }
            </script>
        </body>
        </html>
        <?php
    }
}