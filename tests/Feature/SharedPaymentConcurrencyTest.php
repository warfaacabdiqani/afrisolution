<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class SharedPaymentConcurrencyTest extends TestCase
{
    public function test_simultaneous_full_balance_payments_issue_only_one_receipt(): void
    {
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        $database = $directory.'/shared-payment-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($database);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => false, 'CACHE_STORE' => 'array'];
        $workers = [];
        try {
            $setup = new Process([PHP_BINARY, 'tests/Support/shared-payment-worker.php', 'setup'], base_path(), $env, null, 60);
            $setup->mustRun();
            $this->assertFileExists($database.'.fixture', 'Payment setup did not write its fixture: '.$setup->getOutput().$setup->getErrorOutput());
            foreach (['1', '2'] as $id) {
                $workers[] = $process = new Process([PHP_BINARY, 'tests/Support/shared-payment-worker.php', $id], base_path(), $env, null, 40);
                $process->start();
            }
            $deadline = microtime(true) + 20;
            while (!file_exists($database.'.ready1') || !file_exists($database.'.ready2')) {
                if (microtime(true) > $deadline) $this->fail('Workers failed to start: '.implode(' | ', array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $workers)));
                usleep(20000);
            }
            touch($database.'.go'); $results = [];
            foreach ($workers as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput()); $results[] = trim($process->getOutput()); }
            $this->assertEqualsCanonicalizing(['paid', 'rejected'], $results);
            $db = new \PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $db->query('SELECT COUNT(*) FROM billing_payments')->fetchColumn());
            $this->assertSame(1, (int) $db->query('SELECT COUNT(*) FROM billing_receipts')->fetchColumn());
            $this->assertSame(1, (int) $db->query('SELECT billing_receipt_sequence FROM tenants')->fetchColumn());
            $this->assertSame('45', (string) (float) $db->query('SELECT paid FROM billing_invoices')->fetchColumn());
            $db = null;
        } finally {
            foreach ($workers as $process) if ($process->isRunning()) $process->stop();
            foreach (['', '.fixture', '.ready1', '.ready2', '.go', '-journal', '-wal', '-shm'] as $suffix) if (file_exists($database.$suffix)) unlink($database.$suffix);
        }
    }
}
