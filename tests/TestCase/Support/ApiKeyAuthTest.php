<?php

namespace App\Test\TestCase\Support;

use App\Support\ApiKeyAuth;
use PHPUnit\Framework\TestCase;

class ApiKeyAuthTest extends TestCase
{
    public function testValidateWithCorrectKey(): void
    {
        $auth = new ApiKeyAuth('test-api-key-123');
        $this->assertTrue($auth->validate('test-api-key-123'));
    }

    public function testValidateWithWrongKey(): void
    {
        $auth = new ApiKeyAuth('test-api-key-123');
        $this->assertFalse($auth->validate('wrong-key'));
    }

    public function testValidateWithEmptyKey(): void
    {
        $auth = new ApiKeyAuth('test-api-key-123');
        $this->assertFalse($auth->validate(''));
    }

    public function testValidateIsCaseSensitive(): void
    {
        $auth = new ApiKeyAuth('Test-Key');
        $this->assertFalse($auth->validate('test-key'));
    }
}
