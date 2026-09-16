<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class SharedBillingConcurrencyTest extends TestCase
{
    protected function worker(): string { return 'tests/Support/shared-billing-worker.php'; }
    protected function expectedItems(): int { return 2; }
    public function test_simultaneous_invoice_requests_create_one_invoice_and_consume_one_number(): void
    {
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        $database = $directory.'/shared-billing-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($database);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => false, 'CACHE_STORE' => 'array'];
        $workers = [];
        try {
            (new Process([PHP_BINARY, $this->worker(), 'setup'], base_path(), $env, null, 60))->mustRun();
            foreach (['1', '2'] as $id) {
                $workers[] = $process = new Process([PHP_BINARY, $this->worker(), $id], base_path(), $env, null, 40);
                $process->start();
            }
            $deadline = microtime(true) + 20;
            while (!file_exists($database.'.ready1') || !file_exists($database.'.ready2')) {
                if (microtime(true) > $deadline) $this->fail('Workers failed to start.');
                usleep(20000);
            }
            touch($database.'.go'); $results = [];
            foreach ($workers as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput()); $results[] = trim($process->getOutput()); }
            $this->assertSame($results[0], $results[1]);
            $db = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $db->query('SELECT COUNT(*) FROM billing_invoices')->fetchColumn());
            $this->assertSame($this->expectedItems(), (int) $db->query('SELECT COUNT(*) FROM billing_invoice_items')->fetchColumn());
            $this->assertSame(1, (int) $db->query('SELECT billing_invoice_sequence FROM tenants')->fetchColumn());
            $db = null;
        } finally {
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
            foreach (['', '.fixture', '.ready1', '.ready2', '.go', '-journal', '-wal', '-shm'] as $suffix) if (file_exists($database.$suffix)) unlink($database.$suffix);
        }
    }
}
