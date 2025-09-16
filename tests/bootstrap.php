<?php
declare(strict_types=1);

// Code comments in english

// Ensure storage is empty before tests
$storage = __DIR__ . '/../backend/storage/tasks.json';
if (file_exists($storage)) {
    file_put_contents($storage, "[]\n");
}

// Helper to start/stop PHP built-in server for tests
final class TestServer
{
    public static ?int $pid = null;

    public static function start(): void
    {
        if (self::$pid !== null) return;
        $host = getenv('API_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('API_PORT') ?: '8020');
        $docroot = realpath(__DIR__ . '/../backend');
        $index = $docroot . '/index.php';
        $cmd = sprintf('php -S %s:%d -t %s %s > /tmp/phpunit_server.log 2>&1 & echo $!', $host, $port, escapeshellarg($docroot), escapeshellarg($index));
        $pid = (int)shell_exec($cmd);
        self::$pid = $pid;
        usleep(300000);
    }

    public static function stop(): void
    {
        if (self::$pid) {
            exec('kill ' . self::$pid);
            self::$pid = null;
        }
    }
}

register_shutdown_function(static function () {
    TestServer::stop();
});


