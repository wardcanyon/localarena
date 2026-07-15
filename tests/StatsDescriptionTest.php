<?php declare(strict_types=1);

namespace LocalArena\Test;

require_once __DIR__ . '/../module/test/IntegrationTestCase.php';

/**
 * Unit tests for the statistics-description loader
 * (`localarenaLoadStatsDescription()`) and its jsonc comment
 * stripping (`localarenaStripJsonComments()`).  These don't need a
 * table (or a database); they run against temporary fixture
 * directories.
 */
class StatsDescriptionTest extends \PHPUnit\Framework\TestCase
{
    private array $fixture_dirs_ = [];

    protected function tearDown(): void
    {
        foreach ($this->fixture_dirs_ as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        $this->fixture_dirs_ = [];
    }

    private function makeFixtureDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/localarena-stats-test-' . uniqid();
        mkdir($dir);
        $this->fixture_dirs_[] = $dir;
        foreach ($files as $filename => $contents) {
            file_put_contents($dir . '/' . $filename, $contents);
        }
        return $dir;
    }

    //////////////////////////////////////////////////////////////////
    // Comment stripping.

    public function testStripLineComments(): void
    {
        $this->assertJsonStringEqualsJsonString(
            '{"a": 1}',
            \localarenaStripJsonComments("// leading comment\n{\"a\": 1 // trailing comment\n}")
        );
    }

    public function testStripBlockComments(): void
    {
        $this->assertJsonStringEqualsJsonString(
            '{"a": 1, "b": 2}',
            \localarenaStripJsonComments("{/* one */\"a\": 1, /* two\n spans lines */ \"b\": 2}")
        );
    }

    public function testCommentMarkersInsideStringsArePreserved(): void
    {
        $json = '{"url": "https://example.com/foo", "glob": "a/*"}';
        $this->assertSame($json, \localarenaStripJsonComments($json));
    }

    public function testEscapedQuotesInsideStringsArePreserved(): void
    {
        $json = '{"a": "she said \\"hi\\" // not a comment"}';
        $this->assertSame($json, \localarenaStripJsonComments($json));
    }

    //////////////////////////////////////////////////////////////////
    // Loading and precedence.

    private const SIMPLE_STATS_JSON = <<<'EOT'
{
  "table": {
    "turnsNumber": { "id": 10, "name": "Number of turns", "type": "int" }
  },
  "player": {
    "somethingCount": { "id": 10, "name": "Things done", "type": "int" }
  }
}
EOT;

    public function testLoadsStatsJson(): void
    {
        $dir = $this->makeFixtureDir(['stats.json' => self::SIMPLE_STATS_JSON]);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertSame(10, $stats_type['table']['turnsNumber']['id']);
        $this->assertSame('int', $stats_type['player']['somethingCount']['type']);
    }

    public function testLoadsStatsJsoncWithComments(): void
    {
        $dir = $this->makeFixtureDir([
            'stats.jsonc' => "// a comment\n" . self::SIMPLE_STATS_JSON,
        ]);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertSame(10, $stats_type['table']['turnsNumber']['id']);
    }

    public function testLoadsLegacyStatsIncPhp(): void
    {
        $dir = $this->makeFixtureDir([
            'stats.inc.php' =>
                '<?php $stats_type = ["table" => ["turnsNumber" => ["id" => 10, "name" => "Number of turns", "type" => "int"]], "player" => []];',
        ]);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertSame(10, $stats_type['table']['turnsNumber']['id']);
        $this->assertSame([], $stats_type['player']);
    }

    public function testStatsJsonTakesPrecedenceOverLegacyFile(): void
    {
        $dir = $this->makeFixtureDir([
            'stats.json' => self::SIMPLE_STATS_JSON,
            'stats.inc.php' => '<?php $stats_type = ["table" => [], "player" => []];',
        ]);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertArrayHasKey('turnsNumber', $stats_type['table']);
    }

    public function testMissingSectionsDefaultToEmpty(): void
    {
        $dir = $this->makeFixtureDir(['stats.json' => '{}']);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertSame([], $stats_type['table']);
        $this->assertSame([], $stats_type['player']);
    }

    public function testExtraDisplayOnlyKeysArePreserved(): void
    {
        $dir = $this->makeFixtureDir([
            'stats.json' => '{"table": {}, "player": {}, "value_labels": {"10": ["None", "Auren"]}}',
        ]);
        $stats_type = \localarenaLoadStatsDescription($dir);
        $this->assertSame(['None', 'Auren'], $stats_type['value_labels']['10']);
    }

    public function testMalformedJsonThrows(): void
    {
        $dir = $this->makeFixtureDir(['stats.json' => '{"table": {']);
        $this->expectException(\feException::class);
        \localarenaLoadStatsDescription($dir);
    }

    public function testMissingStatsFileThrows(): void
    {
        $dir = $this->makeFixtureDir([]);
        $this->expectException(\feException::class);
        \localarenaLoadStatsDescription($dir);
    }
}
