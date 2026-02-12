<?php

namespace Aicl\Tests\Feature;

use Aicl\Services\LogParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogParserTest extends TestCase
{
    use RefreshDatabase;

    protected LogParser $parser;

    protected string $logsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new LogParser;
        $this->logsPath = storage_path('logs');
    }

    protected function tearDown(): void
    {
        // Clean up test log files
        foreach (glob($this->logsPath.'/test-*.log') as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    protected function createLogFile(string $name, string $content): string
    {
        $path = $this->logsPath.'/'.$name;
        File::put($path, $content);

        return $path;
    }

    public function test_get_log_files_returns_only_log_files(): void
    {
        $this->createLogFile('test-only-logs.log', 'test');
        File::put($this->logsPath.'/test-not-a-log.txt', 'not a log');

        $files = $this->parser->getLogFiles();
        $names = array_column($files, 'name');

        $this->assertContains('test-only-logs.log', $names);
        $this->assertNotContains('test-not-a-log.txt', $names);

        File::delete($this->logsPath.'/test-not-a-log.txt');
    }

    public function test_get_log_files_returns_correct_structure(): void
    {
        $path = $this->createLogFile('test-structure.log', 'content');

        $files = $this->parser->getLogFiles();
        $file = collect($files)->firstWhere('name', 'test-structure.log');

        $this->assertNotNull($file);
        $this->assertArrayHasKey('path', $file);
        $this->assertArrayHasKey('name', $file);
        $this->assertArrayHasKey('size', $file);
        $this->assertArrayHasKey('modified', $file);
        $this->assertEquals($path, $file['path']);
    }

    public function test_get_log_files_sorted_by_modified_descending(): void
    {
        $this->createLogFile('test-older.log', 'old');
        sleep(1);
        $this->createLogFile('test-newer.log', 'new');

        $files = $this->parser->getLogFiles();
        $testFiles = array_values(array_filter($files, fn ($f) => str_starts_with($f['name'], 'test-')));

        if (count($testFiles) >= 2) {
            $this->assertGreaterThanOrEqual($testFiles[1]['modified'], $testFiles[0]['modified']);
        }
    }

    public function test_get_log_files_returns_empty_for_missing_directory(): void
    {
        // Temporarily rename logs dir
        $backup = $this->logsPath.'_backup_test';
        if (! File::isDirectory($backup)) {
            File::moveDirectory($this->logsPath, $backup);
        }

        $files = $this->parser->getLogFiles();

        // Restore
        File::moveDirectory($backup, $this->logsPath);

        $this->assertEmpty($files);
    }

    public function test_parse_log_file_returns_structured_entries(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: Test message\n";
        $path = $this->createLogFile('test-parse.log', $content);

        $entries = $this->parser->parseLogFile($path);

        $this->assertCount(1, $entries);
        $entry = $entries->first();

        $this->assertEquals('2025-02-05 12:00:00', $entry['timestamp']);
        $this->assertEquals('local', $entry['environment']);
        $this->assertEquals('INFO', $entry['level']);
        $this->assertEquals('Test message', $entry['message']);
    }

    public function test_parse_log_file_handles_context_json(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: User login {\"user_id\":1,\"ip\":\"127.0.0.1\"}\n";
        $path = $this->createLogFile('test-context.log', $content);

        $entries = $this->parser->parseLogFile($path);
        $entry = $entries->first();

        $this->assertEquals('User login', $entry['message']);
        $this->assertNotNull($entry['context']);
        $this->assertStringContainsString('user_id', $entry['context']);
    }

    public function test_parse_log_file_handles_stack_traces(): void
    {
        $content = "[2025-02-05 12:00:00] local.ERROR: Exception thrown\n#0 /app/test.php(10): throw()\n#1 /vendor/framework(20): handle()\n";
        $path = $this->createLogFile('test-stack.log', $content);

        $entries = $this->parser->parseLogFile($path);
        $entry = $entries->first();

        $this->assertEquals('Exception thrown', $entry['message']);
        $this->assertNotNull($entry['stack_trace']);
    }

    public function test_parse_log_file_returns_empty_for_nonexistent_file(): void
    {
        $entries = $this->parser->parseLogFile('/nonexistent/path.log');

        $this->assertCount(0, $entries);
    }

    public function test_parse_log_file_filters_by_level(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: Info msg\n[2025-02-05 12:00:01] local.ERROR: Error msg\n[2025-02-05 12:00:02] local.WARNING: Warn msg\n";
        $path = $this->createLogFile('test-filter-level.log', $content);

        $entries = $this->parser->parseLogFile($path, 100, 'ERROR');

        $this->assertCount(1, $entries);
        $this->assertEquals('ERROR', $entries->first()['level']);
    }

    public function test_parse_log_file_level_filter_is_case_insensitive(): void
    {
        $content = "[2025-02-05 12:00:00] local.ERROR: Error msg\n";
        $path = $this->createLogFile('test-case.log', $content);

        $entries = $this->parser->parseLogFile($path, 100, 'error');

        $this->assertCount(1, $entries);
    }

    public function test_parse_log_file_searches_messages(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: User logged in\n[2025-02-05 12:00:01] local.INFO: Order created\n";
        $path = $this->createLogFile('test-search.log', $content);

        $entries = $this->parser->parseLogFile($path, 100, null, 'User');

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('User', $entries->first()['message']);
    }

    public function test_parse_log_file_search_is_case_insensitive(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: User logged in\n";
        $path = $this->createLogFile('test-search-case.log', $content);

        $entries = $this->parser->parseLogFile($path, 100, null, 'user');

        $this->assertCount(1, $entries);
    }

    public function test_parse_log_file_respects_limit(): void
    {
        $content = '';
        for ($i = 0; $i < 10; $i++) {
            $content .= "[2025-02-05 12:00:0{$i}] local.INFO: Message {$i}\n";
        }
        $path = $this->createLogFile('test-limit.log', $content);

        $entries = $this->parser->parseLogFile($path, 3);

        $this->assertCount(3, $entries);
    }

    public function test_parse_log_file_combined_filter_and_search(): void
    {
        $content = "[2025-02-05 12:00:00] local.INFO: User action\n[2025-02-05 12:00:01] local.ERROR: User failed\n[2025-02-05 12:00:02] local.ERROR: System error\n";
        $path = $this->createLogFile('test-combined.log', $content);

        $entries = $this->parser->parseLogFile($path, 100, 'ERROR', 'User');

        $this->assertCount(1, $entries);
        $this->assertEquals('ERROR', $entries->first()['level']);
        $this->assertStringContainsString('User', $entries->first()['message']);
    }

    public function test_tail_returns_recent_entries(): void
    {
        $content = '';
        for ($i = 0; $i < 20; $i++) {
            $ts = sprintf('2025-02-05 12:%02d:00', $i);
            $content .= "[{$ts}] local.INFO: Message {$i}\n";
        }
        $path = $this->createLogFile('test-tail.log', $content);

        $entries = $this->parser->tail($path, 5);

        $this->assertLessThanOrEqual(5, $entries->count());
    }

    public function test_tail_returns_empty_for_nonexistent_file(): void
    {
        $entries = $this->parser->tail('/nonexistent/path.log');

        $this->assertCount(0, $entries);
    }

    public function test_get_available_levels_returns_all_standard_levels(): void
    {
        $levels = $this->parser->getAvailableLevels();

        $this->assertArrayHasKey('DEBUG', $levels);
        $this->assertArrayHasKey('INFO', $levels);
        $this->assertArrayHasKey('NOTICE', $levels);
        $this->assertArrayHasKey('WARNING', $levels);
        $this->assertArrayHasKey('ERROR', $levels);
        $this->assertArrayHasKey('CRITICAL', $levels);
        $this->assertArrayHasKey('ALERT', $levels);
        $this->assertArrayHasKey('EMERGENCY', $levels);
        $this->assertCount(8, $levels);
    }

    public function test_get_level_color_for_all_levels(): void
    {
        $this->assertEquals('gray', $this->parser->getLevelColor('DEBUG'));
        $this->assertEquals('info', $this->parser->getLevelColor('INFO'));
        $this->assertEquals('primary', $this->parser->getLevelColor('NOTICE'));
        $this->assertEquals('warning', $this->parser->getLevelColor('WARNING'));
        $this->assertEquals('danger', $this->parser->getLevelColor('ERROR'));
        $this->assertEquals('danger', $this->parser->getLevelColor('CRITICAL'));
        $this->assertEquals('danger', $this->parser->getLevelColor('ALERT'));
        $this->assertEquals('danger', $this->parser->getLevelColor('EMERGENCY'));
    }

    public function test_get_level_color_default(): void
    {
        $this->assertEquals('gray', $this->parser->getLevelColor('UNKNOWN'));
    }

    public function test_get_level_color_is_case_insensitive(): void
    {
        $this->assertEquals('danger', $this->parser->getLevelColor('error'));
        $this->assertEquals('warning', $this->parser->getLevelColor('warning'));
    }

    public function test_delete_file_removes_log_file(): void
    {
        $path = $this->createLogFile('test-delete.log', 'content');

        $this->assertTrue(File::exists($path));
        $result = $this->parser->deleteFile($path);

        $this->assertTrue($result);
        $this->assertFalse(File::exists($path));
    }

    public function test_delete_file_returns_false_for_nonexistent_file(): void
    {
        $result = $this->parser->deleteFile('/nonexistent/path.log');

        $this->assertFalse($result);
    }

    public function test_delete_file_rejects_non_log_extension(): void
    {
        $path = $this->logsPath.'/test-delete.txt';
        File::put($path, 'content');

        $result = $this->parser->deleteFile($path);

        $this->assertFalse($result);

        File::delete($path);
    }

    public function test_delete_file_rejects_path_outside_logs_directory(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test').'.log';
        file_put_contents($tempFile, 'content');

        $result = $this->parser->deleteFile($tempFile);

        $this->assertFalse($result);

        unlink($tempFile);
    }

    public function test_clear_file_empties_contents(): void
    {
        $path = $this->createLogFile('test-clear.log', 'lots of content here');

        $this->assertNotEmpty(File::get($path));

        $result = $this->parser->clearFile($path);

        $this->assertTrue($result);
        $this->assertEmpty(File::get($path));
    }

    public function test_clear_file_returns_false_for_nonexistent_file(): void
    {
        $result = $this->parser->clearFile('/nonexistent/path.log');

        $this->assertFalse($result);
    }

    public function test_clear_file_rejects_non_log_extension(): void
    {
        $path = $this->logsPath.'/test-clear.txt';
        File::put($path, 'content');

        $result = $this->parser->clearFile($path);

        $this->assertFalse($result);

        File::delete($path);
    }

    public function test_clear_file_rejects_path_outside_logs_directory(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test').'.log';
        file_put_contents($tempFile, 'content');

        $result = $this->parser->clearFile($tempFile);

        $this->assertFalse($result);

        unlink($tempFile);
    }

    public function test_format_size_bytes(): void
    {
        $this->assertEquals('0 B', $this->parser->formatSize(0));
        $this->assertEquals('500 B', $this->parser->formatSize(500));
    }

    public function test_format_size_kilobytes(): void
    {
        $this->assertEquals('1 KB', $this->parser->formatSize(1024));
        $this->assertEquals('1.5 KB', $this->parser->formatSize(1536));
    }

    public function test_format_size_megabytes(): void
    {
        $this->assertEquals('1 MB', $this->parser->formatSize(1024 * 1024));
        $this->assertEquals('2.5 MB', $this->parser->formatSize(2621440));
    }

    public function test_format_size_gigabytes(): void
    {
        $this->assertEquals('1 GB', $this->parser->formatSize(1024 * 1024 * 1024));
    }

    public function test_parse_multiple_environments(): void
    {
        $content = "[2025-02-05 12:00:00] production.ERROR: Prod error\n[2025-02-05 12:00:01] staging.WARNING: Stage warning\n";
        $path = $this->createLogFile('test-env.log', $content);

        $entries = $this->parser->parseLogFile($path);

        $environments = $entries->pluck('environment')->toArray();
        $this->assertContains('production', $environments);
        $this->assertContains('staging', $environments);
    }

    public function test_parse_empty_log_file(): void
    {
        $path = $this->createLogFile('test-empty.log', '');

        $entries = $this->parser->parseLogFile($path);

        $this->assertCount(0, $entries);
    }

    public function test_parse_malformed_log_entries(): void
    {
        $content = "This is not a valid log entry\nAnother invalid line\n";
        $path = $this->createLogFile('test-malformed.log', $content);

        $entries = $this->parser->parseLogFile($path);

        $this->assertCount(0, $entries);
    }
}
