<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        TestServer::start();
        $host = getenv('API_HOST') ?: '127.0.0.1';
        $port = (int)(getenv('API_PORT') ?: '8020');
        $this->base = 'http://' . $host . ':' . $port;
        // reset storage
        file_put_contents(__DIR__ . '/../backend/storage/tasks.json', "[]\n");
    }

    /**
     * Perform raw HTTP request using cURL, allowing custom headers/body
     * @return array{0:int,1:array<string,mixed>|null,2:string,3:array<int,string>} [status, decoded,jsonBody, headers]
     */
    private function raw(string $method, string $path, string $body = '', array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string)$response, 0, $headerSize);
        $respHeaders = array_values(array_filter(array_map('trim', explode("\n", (string)$rawHeaders))));
        $respBody = substr((string)$response, $headerSize);
        curl_close($ch);
        $decoded = $respBody === '' ? null : json_decode($respBody, true);
        return [$status, $decoded, $respBody, $respHeaders];
    }

    public function testRejectsNonJsonContentType(): void
    {
        [$st, $data] = $this->raw('POST', '/tasks', '{"title":"x"}', [
            'Content-Type: text/plain',
        ]);
        $this->assertSame(400, $st);
        $this->assertSame('UNSUPPORTED_MEDIA_TYPE', $data['error']['code'] ?? null);
    }

    public function testRejectsInvalidJson(): void
    {
        [$st, $data] = $this->raw('POST', '/tasks', '{invalid json', [
            'Content-Type: application/json',
        ]);
        $this->assertSame(400, $st);
        $this->assertSame('BAD_JSON', $data['error']['code'] ?? null);
    }

    public function testRejectsNonUtf8Body(): void
    {
        // Build invalid UTF-8 string
        $invalid = "{\"title\":\"" . "\xC3\x28" . "\"}"; // invalid sequence
        [$st, $data] = $this->raw('POST', '/tasks', $invalid, [
            'Content-Type: application/json',
        ]);
        $this->assertSame(400, $st);
        $this->assertSame('BAD_ENCODING', $data['error']['code'] ?? null);
    }

    public function testPayloadTooLarge(): void
    {
        $over = str_repeat('a', 16385);
        $json = json_encode(['title' => $over], JSON_UNESCAPED_UNICODE);
        [$st, $data] = $this->raw('POST', '/tasks', (string)$json, [
            'Content-Type: application/json',
        ]);
        $this->assertSame(400, $st);
        $this->assertSame('PAYLOAD_TOO_LARGE', $data['error']['code'] ?? null);
    }

    public function testCorsHeadersPresent(): void
    {
        [$st, $_, $body, $headers] = $this->raw('GET', '/tasks');
        $this->assertSame(200, $st);
        $this->assertNotSame('', $body);
        $this->assertTrue($this->hasHeader($headers, 'Access-Control-Allow-Origin: *'));
        $this->assertTrue($this->hasHeader($headers, 'Content-Type: application/json; charset=utf-8'));
    }

    public function testOptionsPreflightReturns204AndCors(): void
    {
        [$st, $_, $body, $headers] = $this->raw('OPTIONS', '/tasks');
        $this->assertSame(204, $st);
        $this->assertSame('', $body);
        $this->assertTrue($this->hasHeader($headers, 'Access-Control-Allow-Origin: *'));
    }

    public function testPathNormalizationPreventsTrailingSlashMismatch(): void
    {
        [$st1] = $this->raw('GET', '/tasks');
        [$st2] = $this->raw('GET', '/tasks/');
        $this->assertSame($st1, $st2);
        $this->assertSame(200, $st2);
    }

    public function testNoHtmlInjectionInTitleStorage(): void
    {
        $payload = ['title' => '<script>alert(1)</script>'];
        [$st, $data] = $this->raw('POST', '/tasks', json_encode($payload, JSON_UNESCAPED_UNICODE), [
            'Content-Type: application/json',
        ]);
        $this->assertSame(201, $st);
        $this->assertSame('<script>alert(1)</script>', $data['title'] ?? null);
    }

    private function hasHeader(array $headers, string $needle): bool
    {
        foreach ($headers as $h) {
            if (stripos($h, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
