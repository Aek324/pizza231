<?php
namespace Services;

class Logger
{
    private string $logFile;
    
    public function __construct()
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
    }
    
    public function info(string $message, array $context = []): void {
        $this->write('INFO', $message, $context);
    }
    public function error(string $message, array $context = []): void {
        $this->write('ERROR', $message, $context);
    }
    public function warning(string $message, array $context = []): void {
        $this->write('WARNING', $message, $context);
    }
    
    private function write(string $level, string $message, array $context): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}