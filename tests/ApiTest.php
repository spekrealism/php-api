<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
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

    private function req(string $method, string $path, ?array $json = null): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $headers = ['Content-Type: application/json'];
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr((string)$response, (int)$headerSize);
        curl_close($ch);
        $data = $body === '' ? null : json_decode($body, true);
        return [$status, $data, $body];
    }

    public function testCrudFlow(): void
    {
        [$st, $data] = $this->req('GET', '/tasks');
        $this->assertSame(200, $st);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);

        [$st, $data] = $this->req('POST', '/tasks', ['title' => 'PHPUnit']);
        $this->assertSame(201, $st);
        $this->assertIsArray($data);
        $this->assertSame('PHPUnit', $data['title']);
        $this->assertFalse($data['completed']);
        $id = (int)$data['id'];

        [$st, $data] = $this->req('PATCH', '/tasks/' . $id, ['completed' => true]);
        $this->assertSame(200, $st);
        $this->assertTrue($data['completed']);

        [$st] = $this->req('DELETE', '/tasks/' . $id);
        $this->assertSame(204, $st);
    }

    public function testErrors(): void
    {
        [$st, $data] = $this->req('POST', '/tasks', ['title' => '']);
        $this->assertSame(400, $st);
        $this->assertIsArray($data['error'] ?? null);

        [$st, $data] = $this->req('PATCH', '/tasks/999999', ['completed' => true]);
        $this->assertSame(404, $st);
        $this->assertIsArray($data['error'] ?? null);
    }
}


