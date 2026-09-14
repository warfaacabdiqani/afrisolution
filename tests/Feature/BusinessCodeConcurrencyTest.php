<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class BusinessCodeConcurrencyTest extends TestCase
{
    public function test_separate_processes_create_unique_codes_while_contending_for_the_sequence(): void
    {
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        $database = $directory.'/business-code-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($database);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => false, 'CACHE_STORE' => 'array'];
        $workers = [];
        try {
            (new Process([PHP_BINARY, 'tests/Support/business-code-worker.php', 'setup'], base_path(), $env, null, 60))->mustRun();
            foreach (['1', '2'] as $id) {
                $workers[] = $worker = new Process([PHP_BINARY, 'tests/Support/business-code-worker.php', $id], base_path(), $env, null, 30);
                $worker->start();
            }
            $deadline = microtime(true) + 15;
            while (!file_exists($database.'.ready1') || !file_exists($database.'.ready2')) {
                if (microtime(true) > $deadline) $this->fail('Workers did not become ready: '.implode('', array_map(fn ($p) => $p->getErrorOutput().$p->getOutput(), $workers)));
                usleep(20000);
            }
            touch($database.'.go');
            $codes = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $codes[] = trim($worker->getOutput());
            }
            sort($codes);
            $this->assertSame(['AFRI-CLN-200001', 'AFRI-CLN-200002'], $codes);
            $connection = new \PDO('sqlite:'.$database);
            $this->assertSame(2, (int) $connection->query('SELECT COUNT(DISTINCT slug) FROM tenants')->fetchColumn());
            $this->assertSame(200002, (int) $connection->query("SELECT current_value FROM platform_sequences WHERE key = 'business_code'")->fetchColumn());
            $connection = null;
        } finally {
            foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
            foreach (['', '.ready1', '.ready2', '.go', '-journal', '-wal', '-shm'] as $suffix) if (file_exists($database.$suffix)) unlink($database.$suffix);
        }
    }
}
