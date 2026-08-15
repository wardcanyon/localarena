<?php declare(strict_types=1);

namespace LocalArena\Test\Unit;

require_once __DIR__ . '/UnitTestCase.php';

require_once localarenaFrameworkPath('module/table/feException.php');
require_once localarenaFrameworkPath('module/table/APP_GameAction.php');

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for `APP_GameAction::getArg()`, which reads, validates, and
 * converts the arguments of an incoming action request.
 *
 * This is the framework's front door for everything a client sends:
 * every `act*()` method in every game reaches its parameters through
 * it.  It is also pure -- no table, no database -- which makes it the
 * natural first resident of the unit lane (see `UnitTestCase`).
 *
 * BGA's signature, from `_ide_helper.php`:
 *
 *     getArg(string $argName, int $argType, bool $bMandatory = false,
 *            mixed $default = null, array $argTypeDetails = [],
 *            bool $bCanFail = false): mixed
 *
 * LocalArena implements all but `$bCanFail`.
 */
class GetArgTest extends UnitTestCase
{
    // Builds an action object carrying the given request parameters.
    // Real requests arrive over HTTP, so every value is a string.
    private function action(array $params): \APP_GameAction
    {
        $act = new \APP_GameAction();
        $act->params = $params;
        return $act;
    }

    private function getArg(array $params, string $name, int $type, ...$rest)
    {
        return $this->action($params)->getArg($name, $type, ...$rest);
    }

    // Asserts that reading $name as $type rejects the value.
    private function assertRejects(array $params, string $name, int $type, ...$rest): void
    {
        $this->expectException(\feException::class);
        $this->getArg($params, $name, $type, ...$rest);
    }

    //////////////////////////////////////////////////////////////////
    // Presence, absence, and defaults.

    public function testReturnsTheConvertedValueWhenPresent(): void
    {
        $this->assertSame(5, $this->getArg(['n' => '5'], 'n', AT_int));
    }

    /**
     * An absent argument that was not required is not an error: the
     * caller's $default comes back.  Games rely on this for optional
     * parameters -- e.g. `emppty.action.php` reads
     * `getArg("row", AT_posint)` with no default at all.
     */
    public function testReturnsNullForAnAbsentOptionalArgument(): void
    {
        $this->assertNull($this->getArg([], 'missing', AT_int));
    }

    public function testReturnsTheGivenDefaultForAnAbsentOptionalArgument(): void
    {
        $this->assertSame(42, $this->getArg([], 'missing', AT_int, /*required=*/ false, /*default=*/ 42));
    }

    /**
     * The default is the caller's own value, not request input, so it
     * is handed back as-is rather than being validated or converted as
     * $type.
     */
    public function testDoesNotConvertOrValidateTheDefault(): void
    {
        $this->assertSame(
            'not an int at all',
            $this->getArg([], 'missing', AT_int, /*required=*/ false, /*default=*/ 'not an int at all')
        );
    }

    public function testThrowsForAnAbsentRequiredArgument(): void
    {
        $this->expectException(\feException::class);
        $this->expectExceptionMessage('Required parameter missing not found.');
        $this->getArg([], 'missing', AT_int, /*required=*/ true);
    }

    /**
     * Presence is tested with `isset()`, so a null-valued parameter
     * counts as absent -- including when it is required.
     */
    public function testTreatsANullValuedParameterAsAbsent(): void
    {
        $this->assertSame(7, $this->getArg(['n' => null], 'n', AT_int, /*required=*/ false, /*default=*/ 7));
    }

    public function testThrowsForAnUnrecognizedType(): void
    {
        $this->expectException(\feException::class);
        $this->expectExceptionMessage('Unsupported arg type: 999');
        $this->getArg(['n' => '5'], 'n', 999);
    }

    public function testIsArgReportsPresence(): void
    {
        $act = $this->action(['present' => '1', 'null_valued' => null]);
        $this->assertTrue($act->isArg('present'));
        $this->assertFalse($act->isArg('absent'));
        $this->assertFalse($act->isArg('null_valued'));
    }

    //////////////////////////////////////////////////////////////////
    // AT_int / AT_posint

    public static function validIntProvider(): array
    {
        return [
            'positive' => ['5', 5],
            'zero' => ['0', 0],
            'negative' => ['-5', -5],
            'leading zeroes' => ['007', 7],
        ];
    }

    #[DataProvider('validIntProvider')]
    public function testAtInt(string $raw, int $expected): void
    {
        $this->assertSame($expected, $this->getArg(['n' => $raw], 'n', AT_int));
    }

    public static function invalidIntProvider(): array
    {
        return [
            'empty' => [''],
            'decimal' => ['5.5'],
            'alphabetic' => ['abc'],
            'trailing garbage' => ['5x'],
            'leading space' => [' 5'],
            'plus sign' => ['+5'],
        ];
    }

    #[DataProvider('invalidIntProvider')]
    public function testAtIntRejects(string $raw): void
    {
        $this->assertRejects(['n' => $raw], 'n', AT_int);
    }

    public function testAtPosint(): void
    {
        $this->assertSame(5, $this->getArg(['n' => '5'], 'n', AT_posint));
        $this->assertSame(0, $this->getArg(['n' => '0'], 'n', AT_posint));
    }

    /**
     * The difference between AT_int and AT_posint is exactly the sign.
     */
    public function testAtPosintRejectsNegativeNumbers(): void
    {
        $this->assertRejects(['n' => '-5'], 'n', AT_posint);
    }

    //////////////////////////////////////////////////////////////////
    // AT_float

    public function testAtFloat(): void
    {
        $this->assertSame(5.5, $this->getArg(['n' => '5.5'], 'n', AT_float));
        $this->assertSame(-5.5, $this->getArg(['n' => '-5.5'], 'n', AT_float));
        // An integral value still comes back as a float.
        $this->assertSame(5.0, $this->getArg(['n' => '5'], 'n', AT_float));
    }

    public static function invalidFloatProvider(): array
    {
        return [
            'no leading digit' => ['.5'],
            'no trailing digit' => ['5.'],
            'alphabetic' => ['abc'],
            'exponent notation' => ['1e3'],
            'two points' => ['1.2.3'],
        ];
    }

    #[DataProvider('invalidFloatProvider')]
    public function testAtFloatRejects(string $raw): void
    {
        $this->assertRejects(['n' => $raw], 'n', AT_float);
    }

    //////////////////////////////////////////////////////////////////
    // AT_bool

    public function testAtBool(): void
    {
        $this->assertTrue($this->getArg(['b' => '1'], 'b', AT_bool));
        $this->assertTrue($this->getArg(['b' => 'true'], 'b', AT_bool));
        $this->assertFalse($this->getArg(['b' => '0'], 'b', AT_bool));
        $this->assertFalse($this->getArg(['b' => 'false'], 'b', AT_bool));
    }

    public static function invalidBoolProvider(): array
    {
        return [
            'yes' => ['yes'],
            'capitalized' => ['True'],
            'empty' => [''],
            'number' => ['2'],
        ];
    }

    #[DataProvider('invalidBoolProvider')]
    public function testAtBoolRejects(string $raw): void
    {
        $this->assertRejects(['b' => $raw], 'b', AT_bool);
    }

    //////////////////////////////////////////////////////////////////
    // AT_enum

    public function testAtEnumReturnsTheRawValue(): void
    {
        $this->assertSame(
            'red',
            $this->getArg(['c' => 'red'], 'c', AT_enum, /*required=*/ false, /*default=*/ null, ['red', 'blue'])
        );
    }

    public function testAtEnumRejectsAValueNotInTheList(): void
    {
        $this->expectException(\feException::class);
        $this->expectExceptionMessage('Invalid value for argument of type AT_enum: green (possible values: red, blue)');
        $this->getArg(['c' => 'green'], 'c', AT_enum, /*required=*/ false, /*default=*/ null, ['red', 'blue']);
    }

    /**
     * With no list supplied, nothing can be valid.
     */
    public function testAtEnumRejectsEverythingWhenTheListIsEmpty(): void
    {
        $this->assertRejects(['c' => 'red'], 'c', AT_enum);
    }

    //////////////////////////////////////////////////////////////////
    // AT_alphanum / AT_alphanum_dash

    public function testAtAlphanum(): void
    {
        $this->assertSame('abc123', $this->getArg(['s' => 'abc123'], 's', AT_alphanum));
        $this->assertSame('with space', $this->getArg(['s' => 'with space'], 's', AT_alphanum));
    }

    public static function invalidAlphanumProvider(): array
    {
        return [
            'empty' => [''],
            'dash' => ['a-b'],
            'punctuation' => ['a.b'],
            'quote' => ["a'b"],
            'angle bracket' => ['<b>'],
        ];
    }

    #[DataProvider('invalidAlphanumProvider')]
    public function testAtAlphanumRejects(string $raw): void
    {
        $this->assertRejects(['s' => $raw], 's', AT_alphanum);
    }

    public function testAtAlphanumDashAllowsDashes(): void
    {
        $this->assertSame('a-b 1', $this->getArg(['s' => 'a-b 1'], 's', AT_alphanum_dash));
    }

    public function testAtAlphanumDashRejectsOtherPunctuation(): void
    {
        $this->assertRejects(['s' => 'a.b'], 's', AT_alphanum_dash);
    }

    /**
     * Both AT_alphanum and AT_alphanum_dash currently reject the
     * underscore, even though the comments on their `define()`s
     * describe it as allowed ("a string with 0-9a-zA-Z_ and space").
     *
     * These two assertions pin down what the code does TODAY rather
     * than endorsing it: if the regexes are corrected to match the
     * documented character set, these are the tests that should fail
     * and be updated, deliberately.
     */
    public function testAtAlphanumTypesRejectUnderscoresDespiteTheirDocumentation(): void
    {
        $this->assertRejects(['s' => 'a_b'], 's', AT_alphanum);
    }

    public function testAtAlphanumDashRejectsUnderscores(): void
    {
        $this->assertRejects(['s' => 'a_b'], 's', AT_alphanum_dash);
    }

    //////////////////////////////////////////////////////////////////
    // AT_numberlist

    /**
     * N.B.: AT_numberlist validates but does not parse; the raw string
     * comes back, and callers split it themselves (as
     * `burglebrostwo.action.php` does with `explode()`).
     */
    public static function validNumberlistProvider(): array
    {
        return [
            'single' => ['1'],
            'comma separated' => ['1,4,2'],
            'semicolon separated' => ['1;4;2'],
            'mixed separators' => ['1,4;2,3'],
            'negative values' => ['-1,2'],
        ];
    }

    #[DataProvider('validNumberlistProvider')]
    public function testAtNumberlistReturnsTheRawString(string $raw): void
    {
        $this->assertSame($raw, $this->getArg(['l' => $raw], 'l', AT_numberlist));
    }

    public static function invalidNumberlistProvider(): array
    {
        return [
            'empty' => [''],
            'trailing separator' => ['1,'],
            'leading separator' => [',1'],
            'doubled separator' => ['1,,2'],
            'spaces' => ['1, 2'],
            'non-numeric' => ['1,a'],
        ];
    }

    #[DataProvider('invalidNumberlistProvider')]
    public function testAtNumberlistRejects(string $raw): void
    {
        $this->assertRejects(['l' => $raw], 'l', AT_numberlist);
    }

    //////////////////////////////////////////////////////////////////
    // AT_base64 / AT_json

    public function testAtBase64Decodes(): void
    {
        $this->assertSame('hello', $this->getArg(['s' => base64_encode('hello')], 's', AT_base64));
    }

    /**
     * Decoding is strict, but a failure is reported by returning false
     * rather than by throwing -- unlike every other type here.
     */
    public function testAtBase64ReturnsFalseForUndecodableInput(): void
    {
        $this->assertFalse($this->getArg(['s' => 'not!valid!base64'], 's', AT_base64));
    }

    public function testAtJsonDecodesToAnAssociativeArray(): void
    {
        $this->assertSame(
            ['a' => 1, 'b' => [2, 3]],
            $this->getArg(['j' => '{"a":1,"b":[2,3]}'], 'j', AT_json)
        );
    }

    /**
     * As with AT_base64, malformed input is not an exception; it
     * decodes to null.
     */
    public function testAtJsonReturnsNullForMalformedInput(): void
    {
        $this->assertNull($this->getArg(['j' => '{not json'], 'j', AT_json));
    }
}
