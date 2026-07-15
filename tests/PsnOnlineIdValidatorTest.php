<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PsnOnlineIdValidator.php';

final class PsnOnlineIdValidatorTest extends TestCase
{
    public function testIsValidOnlineIdValidatesAllowedCharactersAndLength(): void
    {
        $this->assertTrue(PsnOnlineIdValidator::isValidOnlineId('Alpha-123'));
        $this->assertTrue(PsnOnlineIdValidator::isValidOnlineId('ABC'));
        $this->assertTrue(PsnOnlineIdValidator::isValidOnlineId('SixteenCharsHere'));
        $this->assertTrue(PsnOnlineIdValidator::isValidOnlineId('user_name'));
        $this->assertTrue(PsnOnlineIdValidator::isValidOnlineId('aBC'));

        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId(''));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('ab'));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('name with space'));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('invalid!'));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId(str_repeat('a', 17)));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('1Alpha'));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('_Alpha'));
        $this->assertFalse(PsnOnlineIdValidator::isValidOnlineId('-Alpha'));
    }
}
