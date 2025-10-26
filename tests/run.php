<?php

declare(strict_types=1);

require __DIR__ . '/TestCase.php';

foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    require $testFile;
}

$testClasses = array_values(array_filter(
    get_declared_classes(),
    static fn (string $className): bool => is_subclass_of($className, TestCase::class)
));

$total = 0;
$failures = 0;
$errors = 0;

foreach ($testClasses as $testClass) {
    $testCase = new $testClass();
    $results = $testCase->runTests();

    foreach ($results as $result) {
        $total++;
        $status = $result['status'];
        $method = $result['method'];

        if ($status === 'passed') {
            echo sprintf("[PASS] %s::%s\n", $testClass, $method);
            continue;
        }

        $message = $result['message'] ?? '';

        if ($status === 'failed') {
            $failures++;
            echo sprintf("[FAIL] %s::%s - %s\n", $testClass, $method, $message);
            continue;
        }

        $errors++;
        echo sprintf("[ERROR] %s::%s - %s\n", $testClass, $method, $message);
    }
}

if ($total === 0) {
    echo "No tests were executed.\n";
    exit(1);
}

if ($failures === 0 && $errors === 0) {
    echo sprintf("\nAll %d tests passed.\n", $total);
    exit(0);
}

echo sprintf("\nTest run completed with %d failure(s) and %d error(s) out of %d tests.\n", $failures, $errors, $total);
exit(1);
