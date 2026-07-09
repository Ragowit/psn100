<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminRequest.php';
require_once __DIR__ . '/WorkerCredentialField.php';
require_once __DIR__ . '/WorkerCredentialRevealResult.php';
require_once __DIR__ . '/WorkerService.php';

final class WorkerCredentialRevealHandler
{
    public function __construct(private readonly WorkerService $workerService)
    {
    }

    public function handle(AdminRequest $request): WorkerCredentialRevealResult
    {
        if (!$request->isPost()) {
            return WorkerCredentialRevealResult::error('Invalid request method.');
        }

        $workerId = $request->getPostPositiveInt('worker_id');
        $credentialField = WorkerCredentialField::fromMixed($request->getPostString('credential'));

        if ($workerId === null || $credentialField === null) {
            return WorkerCredentialRevealResult::error('Invalid worker credential request.');
        }

        $credential = $this->workerService->fetchWorkerCredential($workerId, $credentialField);

        if ($credential === null) {
            return WorkerCredentialRevealResult::error('Worker not found.');
        }

        return WorkerCredentialRevealResult::success($credential);
    }
}
