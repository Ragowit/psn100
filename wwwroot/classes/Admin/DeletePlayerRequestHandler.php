<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminRequest.php';
require_once __DIR__ . '/DeletePlayerRequestResult.php';
require_once __DIR__ . '/DeletePlayerService.php';

final class DeletePlayerRequestHandler
{
    private DeletePlayerService $service;

    public function __construct(DeletePlayerService $service)
    {
        $this->service = $service;
    }

    public function handleRequest(AdminRequest $request): DeletePlayerRequestResult
    {
        if (!$request->isPost()) {
            return DeletePlayerRequestResult::empty();
        }

        $accountIdInput = $request->getPostString('account_id');
        $onlineId = $request->getPostString('online_id');

        if ($accountIdInput === '' && $onlineId === '') {
            return DeletePlayerRequestResult::error('<p>Please provide an account ID or an online ID.</p>');
        }

        if ($accountIdInput !== '') {
            if (!$this->isValidAccountId($accountIdInput)) {
                return DeletePlayerRequestResult::error('<p>Please provide a numeric account ID.</p>');
            }

            $accountId = $accountIdInput;
        } else {
            $accountId = $this->service->findAccountIdByOnlineId($onlineId);

            if ($accountId === null) {
                return DeletePlayerRequestResult::error('<p>No player was found with that online ID.</p>');
            }
        }

        try {
            $counts = $this->service->deletePlayerByAccountId($accountId);
        } catch (Throwable $exception) {
            return DeletePlayerRequestResult::error('<p>Failed to delete player data. Please try again.</p>');
        }

        $escapedAccountId = $this->escape($accountId);
        $items = '';

        foreach ($counts as $table => $count) {
            $items .= sprintf('<li>%s: %d rows deleted</li>', $this->escape((string) $table), $count);
        }

        $message = sprintf(
            '<p>Deleted data for account ID %s.</p><ul>%s</ul>',
            $escapedAccountId,
            $items
        );

        return DeletePlayerRequestResult::success($message);
    }

    private function isValidAccountId(string $accountId): bool
    {
        return $accountId !== '' && preg_match('/^\\d+$/', $accountId) === 1;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
