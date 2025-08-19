<?php
/**
 * Plugin Dumper - Genera dump completo del plugin
 * Versione: 1.0
 * Autore: Alby Dev
 */
// Sicurezza: previeni accesso diretto
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}
class PluginDumper {
    
    private $plugin_path;
    private $plugin_name;
    private $dump_file;
    private $excluded_extensions = ['zip', 'tar', 'gz', 'rar', '7z', 'exe', 'bin'];
    private $max_file_size = 10 * 1024 * 1024; // 10MB max per file
    
    public function __construct() {
        $this->plugin_path = dirname(__FILE__);
        $this->plugin_name = basename($this->plugin_path);
        $this->dump_file = $this->plugin_path . '/dump_' . $this->plugin_name . '_' . date('Y-m-d_H-i-s') . '.txt';
        
        $this->handleRequest();
    }
    
    private function handleRequest() {
        if (isset($_POST['generate_dump'])) {
            $this->generateDump();
        } elseif (isset($_GET['download']) && file_exists($_GET['download'])) {
            $this->downloadFile($_GET['download']);
        } else {
            $this->showInterface();
        }
    }
    
    private function generateDump() {
        try {
            $content = $this->buildDumpContent();
            
            // Comprimi il contenuto usando gzip
            $compressed_content = gzencode($content, 9);
            
            if (file_put_contents($this->dump_file . '.gz', $compressed_content)) {
                $this->showInterface("✅ Dump creato con successo!", $this->dump_file . '.gz');
            } else {
                throw new Exception("Errore nella scrittura del file dump");
            }
            
        } catch (Exception $e) {
            $this->showInterface("❌ Errore: " . $e->getMessage());
        }
    }
    
    private function buildDumpContent() {
        $content = $this->getHeader();
        $content .= $this->getDirectoryTree();
        $content .= $this->getFileContents();
        $content .= $this->getFooter();
        
        return $content;
    }
    
    private function getHeader() {
        $stats = $this->getPluginStats();
        
        return "
╔══════════════════════════════════════════════════════════════════════════════╗
║                              PLUGIN DUMP REPORT                             ║
╠══════════════════════════════════════════════════════════════════════════════╣
║ Plugin: {$this->plugin_name}
║ Generato: " . date('Y-m-d H:i:s') . "
║ Path: {$this->plugin_path}
║ Totale file: {$stats['files']}
║ Totale directory: {$stats['dirs']}
║ Dimensione totale: " . $this->formatBytes($stats['size']) . "
╚══════════════════════════════════════════════════════════════════════════════╝
";
    }
    
    private function getDirectoryTree() {
        $content = "\n" . str_repeat("=", 80) . "\n";
        $content .= "STRUTTURA DIRECTORY\n";
        $content .= str_repeat("=", 80) . "\n\n";
        
        $content .= $this->buildTree($this->plugin_path, '', true);
        
        return $content . "\n";
    }
    
    private function buildTree($dir, $prefix = '', $isLast = true) {
        $tree = '';
        $items = $this->scanDirectory($dir);
        
        if (empty($items)) return $tree;
        
        foreach ($items as $index => $item) {
            $isLastItem = ($index === count($items) - 1);
            $currentPrefix = $prefix . ($isLastItem ? '└── ' : '├── ');
            
            $tree .= $currentPrefix . basename($item) . "\n";
            
            if (is_dir($item)) {
                $nextPrefix = $prefix . ($isLastItem ? '    ' : '│   ');
                $tree .= $this->buildTree($item, $nextPrefix, $isLastItem);
            }
        }
        
        return $tree;
    }
    
    private function getFileContents() {
        $content = "\n" . str_repeat("=", 80) . "\n";
        $content .= "CONTENUTO FILE\n";
        $content .= str_repeat("=", 80) . "\n\n";
        
        $files = $this->getAllFiles($this->plugin_path);
        
        foreach ($files as $file) {
            $relativePath = str_replace($this->plugin_path . '/', '', $file);
            $content .= $this->getFileSection($file, $relativePath);
        }
        
        return $content;
    }
    
    private function getFileSection($filePath, $relativePath) {
        $section = "\n" . str_repeat("-", 80) . "\n";
        $section .= "FILE: {$relativePath}\n";
        $section .= "SIZE: " . $this->formatBytes(filesize($filePath)) . "\n";
        $section .= "MODIFIED: " . date('Y-m-d H:i:s', filemtime($filePath)) . "\n";
        $section .= str_repeat("-", 80) . "\n";
        
        if (!$this->isTextFile($filePath) || filesize($filePath) > $this->max_file_size) {
            $section .= "[BINARY FILE OR TOO LARGE - CONTENT SKIPPED]\n";
        } else {
            $fileContent = file_get_contents($filePath);
            $section .= $fileContent . "\n";
        }
        
        $section .= "\n" . str_repeat("-", 80) . "\n";
        
        return $section;
    }
    
    private function getFooter() {
        return "\n\n" . str_repeat("=", 80) . "\n" .
               "FINE DUMP - Generato da Plugin Dumper v1.0\n" .
               str_repeat("=", 80) . "\n";
    }
    
    private function scanDirectory($dir) {
        $items = [];
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $this->shouldSkip($file)) {
                continue;
            }
            
            $fullPath = $dir . '/' . $file;
            $items[] = $fullPath;
        }
        
        // Ordina: directory prima, poi file
        usort($items, function($a, $b) {
            $aIsDir = is_dir($a);
            $bIsDir = is_dir($b);
            
            if ($aIsDir && !$bIsDir) return -1;
            if (!$aIsDir && $bIsDir) return 1;
            
            return strcasecmp(basename($a), basename($b));
        });
        
        return $items;
    }
    
    private function getAllFiles($dir) {
        $files = [];
        $items = $this->scanDirectory($dir);
        
        foreach ($items as $item) {
            if (is_file($item)) {
                $files[] = $item;
            } elseif (is_dir($item)) {
                $files = array_merge($files, $this->getAllFiles($item));
            }
        }
        
        return $files;
    }
    
    private function shouldSkip($filename) {
        // Skip file temporanei e dump precedenti
        if (strpos($filename, 'dump_') === 0) return true;
        if (in_array($filename, ['.DS_Store', 'Thumbs.db', '.git', '.svn', 'node_modules'])) return true;
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $this->excluded_extensions);
    }
    
    private function isTextFile($filePath) {
        $textExtensions = ['php', 'js', 'css', 'html', 'htm', 'txt', 'json', 'xml', 'sql', 'md', 'yml', 'yaml', 'ini', 'conf'];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($ext, $textExtensions)) return true;
        
        // Check mime type per file senza estensione
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return strpos($mimeType, 'text/') === 0;
    }
    
    private function getPluginStats() {
        $stats = ['files' => 0, 'dirs' => 0, 'size' => 0];
        $this->calculateStats($this->plugin_path, $stats);
        return $stats;
    }
    
    private function calculateStats($dir, &$stats) {
        $items = $this->scanDirectory($dir);
        
        foreach ($items as $item) {
            if (is_file($item)) {
                $stats['files']++;
                $stats['size'] += filesize($item);
            } elseif (is_dir($item)) {
                $stats['dirs']++;
                $this->calculateStats($item, $stats);
            }
        }
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    private function downloadFile($filePath) {
        if (!file_exists($filePath)) {
            die("File non trovato");
        }
        
        $filename = basename($filePath);
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        
        readfile($filePath);
        exit;
    }
    
    private function showInterface($message = '', $dumpFile = '') {
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Plugin Dumper - <?php echo $this->plugin_name; ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    padding: 40px;
                    max-width: 600px;
                    width: 100%;
                    text-align: center;
                }
                h1 {
                    color: #333;
                    margin-bottom: 10px;
                    font-size: 2.5em;
                }
                .subtitle {
                    color: #666;
                    margin-bottom: 30px;
                    font-size: 1.1em;
                }
                .plugin-info {
                    background: #f8f9fa;
                    border-radius: 10px;
                    padding: 20px;
                    margin: 20px 0;
                    text-align: left;
                }
                .info-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 10px 0;
                    padding: 5px 0;
                    border-bottom: 1px solid #eee;
                }
                .info-row:last-child { border-bottom: none; }
                .btn {
                    background: linear-gradient(45deg, #667eea, #764ba2);
                    color: white;
                    border: none;
                    padding: 15px 30px;
                    border-radius: 50px;
                    font-size: 1.1em;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    text-decoration: none;
                    display: inline-block;
                    margin: 10px;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
                }
                .btn.success {
                    background: linear-gradient(45deg, #56ab2f, #a8e6cf);
                }
                .message {
                    padding: 15px;
                    border-radius: 10px;
                    margin: 20px 0;
                    font-weight: 600;
                }
                .message.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                .message.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                .loading {
                    display: none;
                    margin: 20px 0;
                }
                .spinner {
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #667eea;
                    border-radius: 50%;
                    width: 30px;
                    height: 30px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🔧 Plugin Dumper</h1>
                <div class="subtitle">Genera dump completo del plugin</div>
                
                <div class="plugin-info">
                    <div class="info-row">
                        <strong>Plugin:</strong>
                        <span><?php echo $this->plugin_name; ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Path:</strong>
                        <span><?php echo $this->plugin_path; ?></span>
                    </div>
                    <?php 
                    $stats = $this->getPluginStats();
                    ?>
                    <div class="info-row">
                        <strong>File totali:</strong>
                        <span><?php echo $stats['files']; ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Directory:</strong>
                        <span><?php echo $stats['dirs']; ?></span>
                    </div>
                    <div class="info-row">
                        <strong>Dimensione:</strong>
                        <span><?php echo $this->formatBytes($stats['size']); ?></span>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($dumpFile && file_exists($dumpFile)): ?>
                    <div>
                        <a href="?download=<?php echo urlencode($dumpFile); ?>" class="btn success">
                            📥 Scarica Dump (<?php echo $this->formatBytes(filesize($dumpFile)); ?>)
                        </a>
                    </div>
                <?php endif; ?>
                
                <form method="post" id="dumpForm">
                    <button type="submit" name="generate_dump" class="btn" onclick="showLoading()">
                        🚀 Genera Dump
                    </button>
                </form>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Generazione dump in corso...</p>
                </div>
            </div>
            
            <script>
                function showLoading() {
                    document.getElementById('loading').style.display = 'block';
                    document.querySelector('.btn').style.display = 'none';
                }
            </script>
        </body>
        </html>
        <?php
    }
}
// Avvia il dumper
new PluginDumper();
?>
--------------------------------------------------------------------------------