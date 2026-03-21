<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\ChannelRateLimiter;
use Aicl\Notifications\DriverRegistry;
use Aicl\Services\EntityRegistry;
use Aicl\Services\LogParser;
use Aicl\Services\NotificationDispatcher;
use Aicl\Services\PdfGenerator;
use Aicl\Services\PresenceRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ServiceCoverageTest extends TestCase
{
    /** @var list<string> */
    private array $tempLogFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempLogFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->tempLogFiles = [];

        parent::tearDown();
    }

    /**
     * Create a temp log file inside storage/logs so LogParser::isValidLogPath() accepts it.
     */
    private function createTempLogFile(string $content = ''): string
    {
        $dir = storage_path('logs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/test_'.uniqid().'.log';
        file_put_contents($path, $content);
        $this->tempLogFiles[] = $path;

        return $path;
    }

    // ========================================================================
    // LogParser — parse method and log levels
    // ========================================================================

    public function test_log_parser_parse_log_file_returns_collection(): void
    {
        $parser = new LogParser;

        $result = $parser->parseLogFile('/nonexistent/path/file.log');

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_log_parser_parse_log_file_returns_empty_for_missing_file(): void
    {
        $parser = new LogParser;

        $result = $parser->parseLogFile('/tmp/does-not-exist.log');

        $this->assertTrue($result->isEmpty());
    }

    public function test_log_parser_parse_returns_structured_entries(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Test error message {\"key\":\"value\"}\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);

        $entry = $result->first();
        /** @phpstan-ignore-next-line */
        $this->assertSame('ERROR', $entry['level']);
        $this->assertStringContainsString('Test error message', $entry['message']);
    }

    public function test_log_parser_handles_info_level(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.INFO: Info message\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);
        /** @phpstan-ignore-next-line */
        $this->assertSame('INFO', $result->first()['level']);
    }

    public function test_log_parser_handles_warning_level(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.WARNING: Warning message\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);
        /** @phpstan-ignore-next-line */
        $this->assertSame('WARNING', $result->first()['level']);
    }

    public function test_log_parser_handles_debug_level(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.DEBUG: Debug message\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);
        /** @phpstan-ignore-next-line */
        $this->assertSame('DEBUG', $result->first()['level']);
    }

    public function test_log_parser_handles_critical_level(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.CRITICAL: Critical error\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);
        /** @phpstan-ignore-next-line */
        $this->assertSame('CRITICAL', $result->first()['level']);
    }

    public function test_log_parser_level_filter_filters_entries(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Error one\n"
            ."[2026-01-15 10:30:01] local.INFO: Info one\n"
            ."[2026-01-15 10:30:02] local.ERROR: Error two\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile, levelFilter: 'ERROR');

        $this->assertCount(2, $result);
        $this->assertTrue($result->every(fn ($entry) => $entry['level'] === 'ERROR'));
    }

    public function test_log_parser_search_filter_searches_messages(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Database connection failed\n"
            ."[2026-01-15 10:30:01] local.ERROR: Authentication error\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile, search: 'database');

        $this->assertCount(1, $result);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('Database', $result->first()['message']);
    }

    public function test_log_parser_limit_restricts_result_count(): void
    {
        $logContent = '';
        for ($i = 0; $i < 10; $i++) {
            $logContent .= "[2026-01-15 10:30:0{$i}] local.INFO: Message {$i}\n";
        }
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile, limit: 3);

        $this->assertCount(3, $result);
    }

    public function test_log_parser_get_available_levels_returns_all_eight(): void
    {
        $parser = new LogParser;

        $levels = $parser->getAvailableLevels();

        $this->assertCount(8, $levels);
        $this->assertArrayHasKey('DEBUG', $levels);
        $this->assertArrayHasKey('INFO', $levels);
        $this->assertArrayHasKey('NOTICE', $levels);
        $this->assertArrayHasKey('WARNING', $levels);
        $this->assertArrayHasKey('ERROR', $levels);
        $this->assertArrayHasKey('CRITICAL', $levels);
        $this->assertArrayHasKey('ALERT', $levels);
        $this->assertArrayHasKey('EMERGENCY', $levels);
    }

    public function test_log_parser_get_level_color_for_notice(): void
    {
        $parser = new LogParser;

        $this->assertSame('primary', $parser->getLevelColor('NOTICE'));
    }

    public function test_log_parser_get_level_color_for_alert(): void
    {
        $parser = new LogParser;

        $this->assertSame('danger', $parser->getLevelColor('ALERT'));
    }

    public function test_log_parser_get_level_color_for_emergency(): void
    {
        $parser = new LogParser;

        $this->assertSame('danger', $parser->getLevelColor('EMERGENCY'));
    }

    public function test_log_parser_get_level_color_for_critical(): void
    {
        $parser = new LogParser;

        $this->assertSame('danger', $parser->getLevelColor('CRITICAL'));
    }

    public function test_log_parser_get_level_color_is_case_insensitive(): void
    {
        $parser = new LogParser;

        $this->assertSame('danger', $parser->getLevelColor('error'));
        $this->assertSame('warning', $parser->getLevelColor('warning'));
        $this->assertSame('info', $parser->getLevelColor('info'));
    }

    public function test_log_parser_format_size_bytes(): void
    {
        $parser = new LogParser;

        $this->assertSame('500 B', $parser->formatSize(500));
    }

    public function test_log_parser_format_size_kilobytes(): void
    {
        $parser = new LogParser;

        $this->assertSame('1 KB', $parser->formatSize(1024));
    }

    public function test_log_parser_format_size_megabytes(): void
    {
        $parser = new LogParser;

        $this->assertSame('1 MB', $parser->formatSize(1024 * 1024));
    }

    public function test_log_parser_format_size_gigabytes(): void
    {
        $parser = new LogParser;

        $this->assertSame('1 GB', $parser->formatSize(1024 * 1024 * 1024));
    }

    public function test_log_parser_format_size_fractional(): void
    {
        $parser = new LogParser;

        $result = $parser->formatSize(1536);

        $this->assertSame('1.5 KB', $result);
    }

    public function test_log_parser_tail_returns_empty_for_missing_file(): void
    {
        $parser = new LogParser;

        $result = $parser->tail('/nonexistent/file.log');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_log_parser_tail_returns_entries_from_file(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Tail error\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->tail($tempFile, 10);

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    public function test_log_parser_delete_file_returns_false_for_missing_file(): void
    {
        $parser = new LogParser;

        $this->assertFalse($parser->deleteFile('/nonexistent/file.log'));
    }

    public function test_log_parser_delete_file_returns_false_for_non_log_extension(): void
    {
        $parser = new LogParser;

        $this->assertFalse($parser->deleteFile('/tmp/file.txt'));
    }

    public function test_log_parser_clear_file_returns_false_for_missing_file(): void
    {
        $parser = new LogParser;

        $this->assertFalse($parser->clearFile('/nonexistent/file.log'));
    }

    public function test_log_parser_clear_file_returns_false_for_non_log_extension(): void
    {
        $parser = new LogParser;

        $this->assertFalse($parser->clearFile('/tmp/file.txt'));
    }

    public function test_log_parser_parse_handles_empty_file(): void
    {
        $tempFile = $this->createTempLogFile('');

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertTrue($result->isEmpty());
    }

    public function test_log_parser_parse_handles_multiline_entry(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Exception occurred\nStack trace line 1\nStack trace line 2\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(1, $result);

        $entry = $result->first();
        /** @phpstan-ignore-next-line */
        $this->assertSame('ERROR', $entry['level']);
    }

    public function test_log_parser_entry_has_expected_keys(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Test message {\"key\":\"val\"}\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);
        $entry = $result->first();

        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('timestamp', $entry);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('environment', $entry);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('level', $entry);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('message', $entry);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('context', $entry);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('stack_trace', $entry);
    }

    // ========================================================================
    // PdfGenerator — method structure and chaining
    // ========================================================================

    public function test_pdf_generator_generate_method_exists(): void
    {
        $ref = new ReflectionMethod(PdfGenerator::class, 'generate');

        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('string', $ref->getReturnType()->getName());
    }

    public function test_pdf_generator_generate_accepts_view_and_data(): void
    {
        $ref = new ReflectionMethod(PdfGenerator::class, 'generate');
        $params = $ref->getParameters();

        $this->assertSame('view', $params[0]->getName());
        $this->assertSame('data', $params[1]->getName());
    }

    public function test_pdf_generator_download_method_exists(): void
    {
        $ref = new ReflectionMethod(PdfGenerator::class, 'download');

        $this->assertTrue($ref->isPublic());
    }

    public function test_pdf_generator_stream_method_exists(): void
    {
        $ref = new ReflectionMethod(PdfGenerator::class, 'stream');

        $this->assertTrue($ref->isPublic());
    }

    public function test_pdf_generator_save_method_exists(): void
    {
        $ref = new ReflectionMethod(PdfGenerator::class, 'save');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isPublic());
        $this->assertSame('view', $params[0]->getName());
        $this->assertSame('data', $params[1]->getName());
        $this->assertSame('path', $params[2]->getName());
        $this->assertSame('disk', $params[3]->getName());
    }

    public function test_pdf_generator_default_paper_is_a4(): void
    {
        $generator = new PdfGenerator;

        $ref = new ReflectionClass($generator);
        $paper = $ref->getProperty('paper');

        $this->assertSame('a4', $paper->getValue($generator));
    }

    public function test_pdf_generator_default_orientation_is_portrait(): void
    {
        $generator = new PdfGenerator;

        $ref = new ReflectionClass($generator);
        $orientation = $ref->getProperty('orientation');

        $this->assertSame('portrait', $orientation->getValue($generator));
    }

    public function test_pdf_generator_landscape_sets_orientation(): void
    {
        $generator = new PdfGenerator;
        $generator->landscape();

        $ref = new ReflectionClass($generator);
        $orientation = $ref->getProperty('orientation');

        $this->assertSame('landscape', $orientation->getValue($generator));
    }

    public function test_pdf_generator_portrait_sets_orientation(): void
    {
        $generator = new PdfGenerator;
        $generator->landscape();
        $generator->portrait();

        $ref = new ReflectionClass($generator);
        $orientation = $ref->getProperty('orientation');

        $this->assertSame('portrait', $orientation->getValue($generator));
    }

    public function test_pdf_generator_paper_sets_paper_size(): void
    {
        $generator = new PdfGenerator;
        $generator->paper('letter');

        $ref = new ReflectionClass($generator);
        $paper = $ref->getProperty('paper');

        $this->assertSame('letter', $paper->getValue($generator));
    }

    public function test_pdf_generator_orientation_sets_custom_orientation(): void
    {
        $generator = new PdfGenerator;
        $generator->orientation('landscape');

        $ref = new ReflectionClass($generator);
        $orientation = $ref->getProperty('orientation');

        $this->assertSame('landscape', $orientation->getValue($generator));
    }

    // ========================================================================
    // PresenceRegistry — register/unregister/getOnline
    // ========================================================================

    public function test_presence_registry_touch_stores_session(): void
    {
        $registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');

        $registry->touch('test-session-1', 1, [
            'user_name' => 'Test User',
            'user_email' => 'test@example.com',
            'current_url' => '/dashboard',
            'ip_address' => '127.0.0.1',
        ]);

        $sessions = $registry->allSessions();

        $this->assertCount(1, $sessions);
        /** @phpstan-ignore-next-line */
        $this->assertSame(1, $sessions->first()['user_id']);
    }

    public function test_presence_registry_forget_removes_session(): void
    {
        $registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');

        $registry->touch('test-session-2', 1, [
            'user_name' => 'Test',
            'user_email' => 'test@example.com',
            'current_url' => '/',
            'ip_address' => '127.0.0.1',
        ]);

        $registry->forget('test-session-2');

        $sessions = $registry->allSessions();
        $this->assertTrue($sessions->isEmpty());
    }

    public function test_presence_registry_all_sessions_returns_collection(): void
    {
        $registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');

        $sessions = $registry->allSessions();

        $this->assertInstanceOf(Collection::class, $sessions);
    }

    public function test_presence_registry_sessions_for_user_returns_collection(): void
    {
        $registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');

        $sessions = $registry->sessionsForUser(999);

        $this->assertInstanceOf(Collection::class, $sessions);
        $this->assertTrue($sessions->isEmpty());
    }

    public function test_presence_registry_mask_session_id_static_method(): void
    {
        $result = PresenceRegistry::maskSessionId('abcdefghijklmnopqrstuvwxyz');

        $this->assertStringStartsWith('abcd', $result);
        $this->assertStringEndsWith('wxyz', $result);
    }

    public function test_presence_registry_terminate_session_returns_false_for_unknown(): void
    {
        Event::fake();
        $registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');

        $result = $registry->terminateSession('nonexistent-session');

        $this->assertFalse($result);
    }

    // ========================================================================
    // EntityRegistry — register/get/list methods and metadata
    // ========================================================================

    public function test_entity_registry_all_types_returns_collection(): void
    {
        $registry = new EntityRegistry;

        $result = $registry->allTypes();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_entity_registry_is_entity_returns_bool(): void
    {
        $registry = new EntityRegistry;

        $result = $registry->isEntity('NonExistentModel');

        $this->assertFalse($result);
    }

    public function test_entity_registry_resolve_type_returns_null_for_unknown(): void
    {
        $registry = new EntityRegistry;

        $result = $registry->resolveType('App\\Models\\CompletelyFake');

        $this->assertNull($result);
    }

    public function test_entity_registry_flush_is_static(): void
    {
        $ref = new ReflectionMethod(EntityRegistry::class, 'flush');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_entity_registry_search_method_exists(): void
    {
        $ref = new ReflectionMethod(EntityRegistry::class, 'search');

        $this->assertTrue($ref->isPublic());
    }

    public function test_entity_registry_at_location_method_exists(): void
    {
        $ref = new ReflectionMethod(EntityRegistry::class, 'atLocation');

        $this->assertTrue($ref->isPublic());
    }

    public function test_entity_registry_counts_by_status_method_exists(): void
    {
        $ref = new ReflectionMethod(EntityRegistry::class, 'countsByStatus');

        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('array', $ref->getReturnType()->getName());
    }

    public function test_entity_registry_has_cache_key_constant(): void
    {
        $ref = new ReflectionClass(EntityRegistry::class);
        $constants = $ref->getConstants();

        $this->assertArrayHasKey('CACHE_KEY', $constants);
        $this->assertSame('entity_registry_types', $constants['CACHE_KEY']);
    }

    public function test_entity_registry_has_cache_tags_constant(): void
    {
        $ref = new ReflectionClass(EntityRegistry::class);
        $constants = $ref->getConstants();

        $this->assertArrayHasKey('CACHE_TAGS', $constants);
        $this->assertIsArray($constants['CACHE_TAGS']);
        $this->assertContains('aicl', $constants['CACHE_TAGS']);
        $this->assertContains('entity-registry', $constants['CACHE_TAGS']);
    }

    public function test_entity_registry_flush_does_not_throw(): void
    {
        EntityRegistry::flush();

    }

    // ========================================================================
    // NotificationDispatcher — dispatch method and channel resolution
    // ========================================================================

    public function test_notification_dispatcher_has_send_method(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'send');

        $this->assertTrue($ref->isPublic());
    }

    public function test_notification_dispatcher_has_send_to_many_method(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'sendToMany');

        $this->assertTrue($ref->isPublic());
    }

    public function test_notification_dispatcher_send_accepts_correct_params(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'send');
        $params = $ref->getParameters();

        $this->assertSame('notifiable', $params[0]->getName());
        $this->assertSame('notification', $params[1]->getName());
        $this->assertSame('sender', $params[2]->getName());
        $this->assertTrue($params[2]->allowsNull());
    }

    public function test_notification_dispatcher_send_returns_notification_log(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'send');

        /** @phpstan-ignore-next-line */
        $this->assertSame(NotificationLog::class, $ref->getReturnType()->getName());
    }

    public function test_notification_dispatcher_send_to_many_returns_collection(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'sendToMany');

        /** @phpstan-ignore-next-line */
        $this->assertSame(Collection::class, $ref->getReturnType()->getName());
    }

    public function test_notification_dispatcher_constructor_accepts_dependencies(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, '__construct');
        $params = $ref->getParameters();

        $this->assertSame('driverRegistry', $params[0]->getName());
        $this->assertSame('rateLimiter', $params[1]->getName());
        $this->assertSame('channelResolver', $params[2]->getName());
        $this->assertSame('recipientResolver', $params[3]->getName());
        $this->assertTrue($params[2]->allowsNull());
        $this->assertTrue($params[3]->allowsNull());
    }

    public function test_notification_dispatcher_can_be_instantiated(): void
    {
        $driverRegistry = $this->createMock(DriverRegistry::class);
        $rateLimiter = $this->createMock(ChannelRateLimiter::class);

        $dispatcher = new NotificationDispatcher($driverRegistry, $rateLimiter);

        $this->assertInstanceOf(NotificationDispatcher::class, $dispatcher);
    }

    public function test_notification_dispatcher_has_protected_resolve_external_channels(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'resolveExternalChannels');

        $this->assertTrue($ref->isProtected());
    }

    public function test_notification_dispatcher_has_protected_init_channel_status(): void
    {
        $ref = new ReflectionMethod(NotificationDispatcher::class, 'initChannelStatus');

        $this->assertTrue($ref->isProtected());
    }

    public function test_notification_dispatcher_init_channel_status_returns_pending_for_all(): void
    {
        $driverRegistry = $this->createMock(DriverRegistry::class);
        $rateLimiter = $this->createMock(ChannelRateLimiter::class);
        $dispatcher = new NotificationDispatcher($driverRegistry, $rateLimiter);

        $ref = new ReflectionMethod($dispatcher, 'initChannelStatus');
        $ref->setAccessible(true);

        $result = $ref->invoke($dispatcher, ['database', 'mail', 'broadcast']);

        $this->assertSame([
            'database' => 'pending',
            'mail' => 'pending',
            'broadcast' => 'pending',
        ], $result);
    }

    public function test_notification_dispatcher_init_channel_status_empty_array(): void
    {
        $driverRegistry = $this->createMock(DriverRegistry::class);
        $rateLimiter = $this->createMock(ChannelRateLimiter::class);
        $dispatcher = new NotificationDispatcher($driverRegistry, $rateLimiter);

        $ref = new ReflectionMethod($dispatcher, 'initChannelStatus');
        $ref->setAccessible(true);

        $result = $ref->invoke($dispatcher, []);

        $this->assertSame([], $result);
    }

    public function test_notification_dispatcher_resolve_external_channels_returns_empty_without_resolver(): void
    {
        $driverRegistry = $this->createMock(DriverRegistry::class);
        $rateLimiter = $this->createMock(ChannelRateLimiter::class);
        $dispatcher = new NotificationDispatcher($driverRegistry, $rateLimiter);

        $ref = new ReflectionMethod($dispatcher, 'resolveExternalChannels');
        $ref->setAccessible(true);

        $notification = $this->createMock(BaseNotification::class);
        $notifiable = new \stdClass;

        $result = $ref->invoke($dispatcher, $notification, $notifiable);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ========================================================================
    // LogParser — getLogFiles method
    // ========================================================================

    public function test_log_parser_get_log_files_returns_array(): void
    {
        $parser = new LogParser;

        $result = $parser->getLogFiles();

    }

    public function test_log_parser_get_log_files_entries_have_expected_keys(): void
    {
        $parser = new LogParser;
        $files = $parser->getLogFiles();

        if (count($files) > 0) {
            $first = $files[0];
            $this->assertArrayHasKey('path', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('size', $first);
            $this->assertArrayHasKey('modified', $first);
        } else {
            $this->assertSame([], $files);
        }
    }

    // ========================================================================
    // LogParser — context parsing from JSON
    // ========================================================================

    public function test_log_parser_extracts_json_context(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: Something failed {\"user_id\":42}\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $entry = $result->first();
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($entry['context']);
        $this->assertStringContainsString('user_id', $entry['context']);
    }

    public function test_log_parser_handles_entry_without_context(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.INFO: Simple message\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $entry = $result->first();
        /** @phpstan-ignore-next-line */
        $this->assertNull($entry['context']);
    }

    // ========================================================================
    // LogParser — multiple entries
    // ========================================================================

    public function test_log_parser_parses_multiple_entries(): void
    {
        $logContent = "[2026-01-15 10:30:00] local.ERROR: First error\n"
            ."[2026-01-15 10:30:01] local.WARNING: First warning\n"
            ."[2026-01-15 10:30:02] local.INFO: First info\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $this->assertCount(3, $result);
    }

    // ========================================================================
    // LogParser — environment field
    // ========================================================================

    public function test_log_parser_captures_environment_field(): void
    {
        $logContent = "[2026-01-15 10:30:00] production.ERROR: Prod error\n";
        $tempFile = $this->createTempLogFile($logContent);

        $parser = new LogParser;
        $result = $parser->parseLogFile($tempFile);

        $entry = $result->first();
        /** @phpstan-ignore-next-line */
        $this->assertSame('production', $entry['environment']);
    }

    // ========================================================================
    // LogParser — format size edge cases
    // ========================================================================

    public function test_log_parser_format_size_zero_bytes(): void
    {
        $parser = new LogParser;

        $this->assertSame('0 B', $parser->formatSize(0));
    }
}
