<?php

declare(strict_types=1);

namespace Tests\Validation;

use PHPUnit\Framework\TestCase;
use Arc\Validation\Validator;
use Arc\Validation\ValidationException;

class ValidatorTest extends TestCase
{
    public function testPassesWithValidData(): void
    {
        $v = Validator::make(
            ['name' => 'Arc', 'email' => 'arc@example.com'],
            ['name' => 'required|string', 'email' => 'required|email'],
        );
        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
    }

    public function testFailsWithMissingRequired(): void
    {
        $v = Validator::make(
            ['name' => ''],
            ['name' => 'required'],
        );
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testFailsWithInvalidEmail(): void
    {
        $v = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email'],
        );
        $this->assertTrue($v->fails());
    }

    public function testMinRule(): void
    {
        $v = Validator::make(['password' => 'short'], ['password' => 'min:8']);
        $this->assertTrue($v->fails());

        $v = Validator::make(['password' => 'longenough'], ['password' => 'min:8']);
        $this->assertTrue($v->passes());
    }

    public function testMaxRule(): void
    {
        $v = Validator::make(['name' => 'A very long name'], ['name' => 'max:5']);
        $this->assertTrue($v->fails());

        $v = Validator::make(['name' => 'Arc'], ['name' => 'max:5']);
        $this->assertTrue($v->passes());
    }

    public function testIntegerRule(): void
    {
        $v = Validator::make(['age' => 'twenty'], ['age' => 'integer']);
        $this->assertTrue($v->fails());

        $v = Validator::make(['age' => '25'], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    public function testSameRule(): void
    {
        $v = Validator::make(
            ['password' => 'abc', 'confirm' => 'xyz'],
            ['confirm' => 'same:password'],
        );
        $this->assertTrue($v->fails());

        $v = Validator::make(
            ['password' => 'abc', 'confirm' => 'abc'],
            ['confirm' => 'same:password'],
        );
        $this->assertTrue($v->passes());
    }

    public function testInRule(): void
    {
        $v = Validator::make(['status' => 'active'], ['status' => 'in:active,inactive']);
        $this->assertTrue($v->passes());

        $v = Validator::make(['status' => 'unknown'], ['status' => 'in:active,inactive']);
        $this->assertTrue($v->fails());
    }

    public function testCustomMessages(): void
    {
        $v = Validator::make(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Give us a name!'],
        );
        $this->assertTrue($v->fails());
        $this->assertSame('Give us a name!', $v->errors()['name'][0]);
    }

    public function testValidatedReturnsOnlyRuleFields(): void
    {
        $v = Validator::make(
            ['name' => 'Arc', 'extra' => 'ignored'],
            ['name' => 'required|string'],
        );
        $validated = $v->validated();
        $this->assertSame(['name' => 'Arc'], $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testValidatedThrowsWhenFails(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->expectException(ValidationException::class);
        $v->validated();
    }

    public function testUrlRule(): void
    {
        $v = Validator::make(['site' => 'https://arc.dev'], ['site' => 'url']);
        $this->assertTrue($v->passes());

        $v = Validator::make(['site' => 'not a url'], ['site' => 'url']);
        $this->assertTrue($v->fails());
    }

    public function testAlphaNumRule(): void
    {
        $v = Validator::make(['slug' => 'hello-world'], ['slug' => 'alpha_num']);
        $this->assertTrue($v->fails());

        $v = Validator::make(['slug' => 'hello123'], ['slug' => 'alpha_num']);
        $this->assertTrue($v->passes());
    }

    public function testRegexRuleWithTildeDelimiter(): void
    {
        // Patterns using ~ as delimiter now work (changed from / delimiter)
        $v = Validator::make(['code' => 'abc123'], ['code' => 'regex:^[a-z]+\d+$']);
        $this->assertTrue($v->passes());

        $v = Validator::make(['code' => 'ABC'], ['code' => 'regex:^[a-z]+$']);
        $this->assertTrue($v->fails());
    }

    public function testRegexRuleWithSlashes(): void
    {
        // Patterns containing / now work (no delimiter conflict)
        $v = Validator::make(['path' => '/users/123'], ['path' => 'regex:^/users/\d+$']);
        $this->assertTrue($v->passes());

        $v = Validator::make(['path' => '/users/abc'], ['path' => 'regex:^/users/\d+$']);
        $this->assertTrue($v->fails());
    }

    public function testInvalidRegexPatternFailsValidation(): void
    {
        // Invalid regex should fail validation gracefully, not throw
        $v = Validator::make(['field' => 'test'], ['field' => 'regex:[invalid']);
        $this->assertTrue($v->fails());
    }

    public function testRegexRuleWithAnchoredPattern(): void
    {
        $v = Validator::make(['zip' => '12345'], ['zip' => 'regex:^\d{5}$']);
        $this->assertTrue($v->passes());

        $v = Validator::make(['zip' => 'abcde'], ['zip' => 'regex:^\d{5}$']);
        $this->assertTrue($v->fails());
    }
}