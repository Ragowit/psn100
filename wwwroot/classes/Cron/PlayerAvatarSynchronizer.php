<?php

declare(strict_types=1);

require_once __DIR__ . '/../ImageHashCalculator.php';

/**
 * Downloads PSN profile avatars, deduplicates them via perceptual hash, and caches
 * filenames in psn100_avatars.
 *
 * Encapsulates avatar filesystem and catalog logic that was previously embedded in
 * PlayerScanProfileSynchronizer.
 */
final class PlayerAvatarSynchronizer
{
    public function __construct(
        private readonly PDO $database,
        private readonly ImageHashCalculator $imageHashCalculator,
        private readonly string $avatarStorageDirectory = '/home/psn100/public_html/img/avatar/',
    ) {
    }

    public function synchronizeFromPsnUser(object $user): string
    {
        $avatarUrls = $user->avatarUrls();
        $avatarFilename = '';

        foreach (['xl', 'l', 'm', 's'] as $size) {
            $avatarUrl = $avatarUrls[$size];

            $query = $this->database->prepare(
                'SELECT md5_hash, extension FROM psn100_avatars WHERE avatar_url = :avatar_url'
            );
            $query->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
            $query->execute();
            $result = $query->fetch();

            if (!$result) {
                $avatarContents = @file_get_contents($avatarUrl);
                if ($avatarContents === false) {
                    continue;
                }

                $newPHash = $this->imageHashCalculator->calculatePHash($avatarContents);
                if ($newPHash === null) {
                    continue;
                }

                $query = $this->database->prepare('SELECT DISTINCT md5_hash FROM psn100_avatars');
                $query->execute();
                $existingPHashes = $query->fetchAll(PDO::FETCH_COLUMN);

                $matchedPHash = array_find(
                    $existingPHashes,
                    fn (mixed $existingPHash): bool => is_string($existingPHash)
                        && $this->imageHashCalculator->getHammingDistance($newPHash, $existingPHash) <= 10
                );

                if (is_string($matchedPHash)) {
                    $newPHash = $matchedPHash;
                }

                $path = Uri\Rfc3986\Uri::parse($avatarUrl)?->getPath() ?? '';
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $avatarFilename = $newPHash . '.' . $extension;
                $avatarPath = $this->avatarStorageDirectory . $avatarFilename;

                if (!file_exists($avatarPath)) {
                    file_put_contents($avatarPath, $avatarContents);
                }

                $query = $this->database->prepare(
                    'INSERT INTO psn100_avatars(size, avatar_url, md5_hash, extension)
                    VALUES(:size, :avatar_url, :md5_hash, :extension)'
                );
                $query->bindValue(':size', $size, PDO::PARAM_STR);
                $query->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
                $query->bindValue(':md5_hash', $newPHash, PDO::PARAM_STR);
                $query->bindValue(':extension', $extension, PDO::PARAM_STR);
                $query->execute();
            } else {
                $avatarFilename = $result['md5_hash'] . '.' . $result['extension'];
            }

            break;
        }

        return $avatarFilename;
    }
}
