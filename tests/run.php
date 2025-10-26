<?php

declare(strict_types=1);

require __DIR__ . '/TestSuite.php';

$suite = TestSuite::fromDirectory(__DIR__);
$result = $suite->run();
$statusMap = [
    'passed' => 'PASS',
    'failed' => 'FAIL',
    'error' => 'ERROR',
];

foreach ($result->getResults() as $testResult) {
    $status = $statusMap[$testResult->getStatus()] ?? strtoupper($testResult->getStatus());
    $className = $testResult->getClassName();
    $methodName = $testResult->getMethodName();
    $message = $testResult->getMessage();

    if ($testResult->isPassed()) {
        echo sprintf('[%s] %s::%s', $status, $className, $methodName) . "\n";
        continue;
    }

    echo sprintf('[%s] %s::%s - %s', $status, $className, $methodName, $message ?? '') . "\n";
}

if ($result->getTotalTests() === 0) {
    echo "No tests were executed.\n";
    exit(1);
}

if ($result->isSuccessful()) {
    echo sprintf("\nAll %d tests passed.\n", $result->getTotalTests());
    exit(0);
}

echo sprintf(
    "\nTest run completed with %d failure(s) and %d error(s) out of %d tests.\n",
    $result->getFailureCount(),
    $result->getErrorCount(),
    $result->getTotalTests()
);

exit(1);
