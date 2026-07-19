<?php

declare(strict_types=1);

enum IpRateLimitBucket: string
{
    case QueuePoll = 'queue_poll';
    case QueueSubmit = 'queue_submit';
    case PlayerReport = 'player_report';
    case ScanLogPoll = 'scan_log_poll';

    private const int QUEUE_POLL_MAX_REQUESTS = 60;
    private const int QUEUE_POLL_WINDOW_SECONDS = 60;
    private const int QUEUE_SUBMIT_MAX_REQUESTS = 10;
    private const int QUEUE_SUBMIT_WINDOW_SECONDS = 60;
    private const int PLAYER_REPORT_MAX_REQUESTS = 5;
    private const int PLAYER_REPORT_WINDOW_SECONDS = 60;
    private const int SCAN_LOG_POLL_MAX_REQUESTS = 30;
    private const int SCAN_LOG_POLL_WINDOW_SECONDS = 60;

    /**
     * @return array{0: int, 1: int}
     */
    public function limits(): array
    {
        return match ($this) {
            self::ScanLogPoll => [
                self::SCAN_LOG_POLL_MAX_REQUESTS,
                self::SCAN_LOG_POLL_WINDOW_SECONDS,
            ],
            self::QueuePoll => [
                self::QUEUE_POLL_MAX_REQUESTS,
                self::QUEUE_POLL_WINDOW_SECONDS,
            ],
            self::QueueSubmit => [
                self::QUEUE_SUBMIT_MAX_REQUESTS,
                self::QUEUE_SUBMIT_WINDOW_SECONDS,
            ],
            self::PlayerReport => [
                self::PLAYER_REPORT_MAX_REQUESTS,
                self::PLAYER_REPORT_WINDOW_SECONDS,
            ],
        };
    }
}
