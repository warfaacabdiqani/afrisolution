<?php

namespace Tests\Feature;

use App\Services\PlatformMailConfigurator;
use App\Services\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformMailConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public static function encryptionModes(): array
    {
        return [
            'STARTTLS' => ['tls', 587, false],
            'implicit TLS' => ['ssl', 465, true],
        ];
    }

    #[DataProvider('encryptionModes')]
    public function test_saved_smtp_settings_build_a_transport(string $encryption, int $port, bool $implicitTls): void
    {
        config(['mail.mailers.smtp.url' => null]);
        app(SystemSettingsService::class)->setSection('email', [
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => $port,
            'smtp_username' => 'sender@example.test',
            'smtp_password' => 'test-secret',
            'encryption' => $encryption,
            'from_email' => 'sender@example.test',
            'from_name' => 'Clinic',
        ]);

        app(PlatformMailConfigurator::class)->apply();

        // Construct the real transport without connecting or sending mail.
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertSame('smtp.example.test', $transport->getStream()->getHost());
        $this->assertSame($port, $transport->getStream()->getPort());
        $this->assertSame($implicitTls, $transport->getStream()->isTLS());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame('sender@example.test', $transport->getUsername());
        $this->assertSame('test-secret', $transport->getPassword());
    }
}
