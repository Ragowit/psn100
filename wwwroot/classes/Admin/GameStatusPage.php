<?php

declare(strict_types=1);

require_once __DIR__ . '/GameStatusRequestResult.php';

final class GameStatusPage
{
    private const STATUS_OPTIONS = [
        0 => 'Normal',
        1 => 'Delisted',
        3 => 'Obsolete',
        4 => 'Delisted & Obsolete',
    ];

    private GameStatusRequestResult $result;

    private function __construct(GameStatusRequestResult $result)
    {
        $this->result = $result;
    }

    public static function fromResult(GameStatusRequestResult $result): self
    {
        return new self($result);
    }

    public function render(): string
    {
        $optionsHtml = $this->renderStatusOptions();
        $messagesHtml = $this->renderMessages();

        return <<<HTML
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Game Status</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                Status:<br>
                <select name="status">
                    {$optionsHtml}
                </select><br><br>
                <input type="submit" value="Submit">
            </form>
            {$messagesHtml}
        </div>
    </body>
</html>
HTML;
    }

    private function renderStatusOptions(): string
    {
        $options = [];

        foreach (self::STATUS_OPTIONS as $value => $label) {
            $escapedValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $options[] = sprintf('<option value="%s">%s</option>', $escapedValue, $escapedLabel);
        }

        return implode('', $options);
    }

    private function renderMessages(): string
    {
        $messages = [];

        $error = $this->result->getErrorMessage();
        if ($error !== null) {
            $messages[] = sprintf('<div class="text-danger">%s</div>', htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
        }

        $success = $this->result->getSuccessMessage();
        if ($success !== null) {
            $messages[] = sprintf('<div class="text-success">%s</div>', htmlspecialchars($success, ENT_QUOTES, 'UTF-8'));
        }

        if ($messages === []) {
            return '';
        }

        return '<div class="mt-3">' . implode('', $messages) . '</div>';
    }
}
