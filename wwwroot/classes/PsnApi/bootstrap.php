<?php

declare(strict_types=1);

require_once __DIR__ . '/Exceptions/ApiException.php';
require_once __DIR__ . '/Exceptions/AuthenticationException.php';
require_once __DIR__ . '/Exceptions/NotFoundException.php';
require_once __DIR__ . '/Json/DecodingException.php';
require_once __DIR__ . '/Json/Decoder.php';
require_once __DIR__ . '/HttpClient.php';
require_once __DIR__ . '/AuthTokens.php';
require_once __DIR__ . '/Authenticator.php';
require_once __DIR__ . '/Client.php';
require_once __DIR__ . '/Trophies/TrophyType.php';
require_once __DIR__ . '/Trophies/TitleTrophy.php';
require_once __DIR__ . '/Trophies/TitleTrophyGroup.php';
require_once __DIR__ . '/Trophies/TitleTrophySet.php';
require_once __DIR__ . '/Users/UserSearchResult.php';
require_once __DIR__ . '/Users/TrophySummary.php';
require_once __DIR__ . '/Users/UserTrophy.php';
require_once __DIR__ . '/Users/UserTrophyGroup.php';
require_once __DIR__ . '/Users/UserTrophyTitle.php';
require_once __DIR__ . '/Users/UserTrophyTitleCollection.php';
require_once __DIR__ . '/Users/User.php';
require_once __DIR__ . '/Users/UsersService.php';
