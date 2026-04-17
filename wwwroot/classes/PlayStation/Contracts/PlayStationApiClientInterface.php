<?php

declare(strict_types=1);

require_once __DIR__ . '/AuthClientInterface.php';
require_once __DIR__ . '/ProfileClientInterface.php';
require_once __DIR__ . '/TrophyClientInterface.php';
require_once __DIR__ . '/UserSearchClientInterface.php';

interface PlayStationApiClientInterface extends AuthClientInterface, ProfileClientInterface, TrophyClientInterface, UserSearchClientInterface
{
}
