<?php

declare(strict_types=1);

namespace Tapbuy\RedirectTracking\Test\Unit\Model\Resolver;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tapbuy\RedirectTracking\Api\Authorization\TokenAuthorizationInterface;
use Tapbuy\RedirectTracking\Api\ConfigInterface;
use Tapbuy\RedirectTracking\Api\LogHandlerInterface;
use Tapbuy\RedirectTracking\Model\Resolver\FetchLogs;

class FetchLogsTest extends TestCase
{
    private FetchLogs $resolver;
    private TokenAuthorizationInterface&MockObject $tokenAuth;
    private ConfigInterface&MockObject $config;
    private LogHandlerInterface&MockObject $logHandler;
    private FileDriver&MockObject $fileDriver;
    private Field&MockObject $field;
    private ContextInterface&MockObject $context;
    private ResolveInfo&MockObject $info;

    protected function setUp(): void
    {
        $this->tokenAuth = $this->createMock(TokenAuthorizationInterface::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->logHandler = $this->createMock(LogHandlerInterface::class);
        $this->fileDriver = $this->createMock(FileDriver::class);

        $this->resolver = new FetchLogs(
            $this->tokenAuth,
            $this->config,
            $this->logHandler,
            $this->fileDriver
        );

        $this->field = $this->createMock(Field::class);
        $this->context = $this->createMock(ContextInterface::class);
        $this->info = $this->createMock(ResolveInfo::class);
    }

    public function testRequiresAuthorization(): void
    {
        $this->tokenAuth->expects($this->once())
            ->method('authorize')
            ->with(TokenAuthorizationInterface::TAPBUY_LOGS);

        $this->config->method('isEnabled')->willReturn(false);

        $this->resolver->resolve($this->field, $this->context, $this->info);
    }

    public function testReturnsDisabledMessageWhenNotEnabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertCount(1, $result['logs']);
        $this->assertSame('Tapbuy is disabled.', $result['logs'][0]['message']);
        $this->assertSame('INFO', $result['logs'][0]['level_name']);
    }

    public function testReturnsEmptyLogsWhenNoFiles(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->logHandler->method('getAllLogFiles')->willReturn([]);

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertSame([], $result['logs']);
    }

    public function testParsesJsonLogEntries(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $logEntry = json_encode([
            'message' => 'Test log message',
            'level' => 200,
            'level_name' => 'INFO',
            'datetime' => '2024-01-01T00:00:00+00:00',
            'context' => [],
            'channel' => 'tapbuy',
        ]);

        $this->logHandler->method('getAllLogFiles')->willReturn(['/var/log/tapbuy.log']);
        $this->fileDriver->method('isExists')->willReturn(true);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $logEntry);
        rewind($handle);

        $this->fileDriver->method('fileOpen')->willReturn($handle);
        $this->fileDriver->method('endOfFile')->willReturnCallback(function () use ($handle) {
            return feof($handle);
        });
        $this->fileDriver->method('fileRead')->willReturnCallback(function ($h, $size) use ($handle) {
            return fread($handle, $size);
        });
        $this->fileDriver->method('fileClose')->willReturnCallback(function () use ($handle) {
            fclose($handle);
        });

        $result = $this->resolver->resolve($this->field, $this->context, $this->info);

        $this->assertCount(1, $result['logs']);
        $this->assertSame('Test log message', $result['logs'][0]['message']);
        $this->assertSame(200, $result['logs'][0]['level']);
    }

    public function testFiltersLogsByTraceId(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $log1 = json_encode([
            'message' => 'Matched',
            'level' => 200,
            'level_name' => 'INFO',
            'datetime' => '2024-01-01T00:00:00+00:00',
            'context' => ['tapbuy_trace_id' => 'trace-abc'],
            'channel' => 'tapbuy',
        ]);
        $log2 = json_encode([
            'message' => 'Not matched',
            'level' => 200,
            'level_name' => 'INFO',
            'datetime' => '2024-01-01T00:00:01+00:00',
            'context' => ['tapbuy_trace_id' => 'trace-xyz'],
            'channel' => 'tapbuy',
        ]);

        $this->logHandler->method('getAllLogFiles')->willReturn(['/var/log/tapbuy.log']);
        $this->fileDriver->method('isExists')->willReturn(true);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $log1 . "\n" . $log2);
        rewind($handle);

        $this->fileDriver->method('fileOpen')->willReturn($handle);
        $this->fileDriver->method('endOfFile')->willReturnCallback(function () use ($handle) {
            return feof($handle);
        });
        $this->fileDriver->method('fileRead')->willReturnCallback(function ($h, $size) use ($handle) {
            return fread($handle, $size);
        });
        $this->fileDriver->method('fileClose')->willReturnCallback(function () use ($handle) {
            fclose($handle);
        });

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'traceId' => 'trace-abc',
        ]);

        $this->assertCount(1, $result['logs']);
        $this->assertSame('Matched', $result['logs'][0]['message']);
    }

    public function testRespectsLimitArgument(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $logs = '';
        for ($i = 0; $i < 5; $i++) {
            $logs .= json_encode([
                'message' => "Log $i",
                'level' => 200,
                'level_name' => 'INFO',
                'datetime' => sprintf('2024-01-01T00:00:%02d+00:00', $i),
                'context' => [],
                'channel' => 'tapbuy',
            ]) . "\n";
        }

        $this->logHandler->method('getAllLogFiles')->willReturn(['/var/log/tapbuy.log']);
        $this->fileDriver->method('isExists')->willReturn(true);

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $logs);
        rewind($handle);

        $this->fileDriver->method('fileOpen')->willReturn($handle);
        $this->fileDriver->method('endOfFile')->willReturnCallback(function () use ($handle) {
            return feof($handle);
        });
        $this->fileDriver->method('fileRead')->willReturnCallback(function ($h, $size) use ($handle) {
            return fread($handle, $size);
        });
        $this->fileDriver->method('fileClose')->willReturnCallback(function () use ($handle) {
            fclose($handle);
        });

        $result = $this->resolver->resolve($this->field, $this->context, $this->info, null, [
            'limit' => 2,
        ]);

        $this->assertCount(2, $result['logs']);
    }
}
