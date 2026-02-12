<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\LogParser;
use PHPUnit\Framework\TestCase;

class LogParserUnitTest extends TestCase
{
    public function test_get_level_color_for_error(): void
    {
        $parser = new LogParser;

        $this->assertEquals('danger', $parser->getLevelColor('ERROR'));
    }

    public function test_get_level_color_for_warning(): void
    {
        $parser = new LogParser;

        $this->assertEquals('warning', $parser->getLevelColor('WARNING'));
    }

    public function test_get_level_color_for_info(): void
    {
        $parser = new LogParser;

        $this->assertEquals('info', $parser->getLevelColor('INFO'));
    }

    public function test_get_level_color_for_debug(): void
    {
        $parser = new LogParser;

        $this->assertEquals('gray', $parser->getLevelColor('DEBUG'));
    }

    public function test_get_level_color_for_unknown_returns_gray(): void
    {
        $parser = new LogParser;

        $this->assertEquals('gray', $parser->getLevelColor('UNKNOWN'));
    }

    public function test_log_parser_can_be_instantiated(): void
    {
        $parser = new LogParser;

        $this->assertInstanceOf(LogParser::class, $parser);
    }

    public function test_log_parser_has_get_log_files_method(): void
    {
        $this->assertTrue(method_exists(LogParser::class, 'getLogFiles'));
    }

    public function test_log_parser_has_parse_log_file_method(): void
    {
        $this->assertTrue(method_exists(LogParser::class, 'parseLogFile'));
    }
}
