<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerPlatformFilterOptions.php';

final class PlayerPlatformFilterOptionsTest extends TestCase
{
    public function testFromSelectionCallbackCreatesOptionsForEachPlatform(): void
    {
        $capturedKeys = [];
        $options = PlayerPlatformFilterOptions::fromSelectionCallback(
            function (string $key) use (&$capturedKeys): bool {
                $capturedKeys[] = $key;

                return in_array($key, ['ps5', 'psvr2'], true);
            }
        )->getOptions();

        $expectedOrder = ['pc', 'ps3', 'ps4', 'ps5', 'psvita', 'psvr', 'psvr2'];
        $expectedLabels = [
            'pc' => 'PC',
            'ps3' => 'PS3',
            'ps4' => 'PS4',
            'ps5' => 'PS5',
            'psvita' => 'PSVITA',
            'psvr' => 'PSVR',
            'psvr2' => 'PSVR2',
        ];

        $this->assertSame($expectedOrder, $capturedKeys);
        $this->assertCount(count($expectedOrder), $options);

        foreach ($options as $index => $option) {
            if (!$option instanceof PlayerPlatformFilterOption) {
                $this->fail('Options must be instances of PlayerPlatformFilterOption.');
            }

            $expectedKey = $expectedOrder[$index];

            $this->assertSame($expectedKey, $option->getInputName());
            $this->assertSame('filter' . strtoupper($expectedKey), $option->getInputId());
            $this->assertSame($expectedLabels[$expectedKey], $option->getLabel());
            $this->assertSame(in_array($expectedKey, ['ps5', 'psvr2'], true), $option->isSelected());
        }
    }

    public function testSelectionCallbackResultIsCastedToBoolean(): void
    {
        $options = PlayerPlatformFilterOptions::fromSelectionCallback(
            static function (string $key) {
                return $key === 'ps3' ? '1' : '';
            }
        )->getOptions();

        $selectedByKey = [];
        foreach ($options as $option) {
            if (!$option instanceof PlayerPlatformFilterOption) {
                $this->fail('Options must be instances of PlayerPlatformFilterOption.');
            }

            $selectedByKey[$option->getInputName()] = $option->isSelected();
        }

        $this->assertTrue($selectedByKey['ps3']);
        $this->assertFalse($selectedByKey['pc']);
        $this->assertFalse($selectedByKey['ps4']);
    }
}
