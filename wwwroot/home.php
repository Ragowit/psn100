<?php

declare(strict_types=1);

require_once 'classes/HomepageController.php';

$homepageController = HomepageController::fromDatabase($database);
$homepageViewModel = $homepageController->getViewModel();

$title = $homepageViewModel->getTitle();
$newGames = $homepageViewModel->getNewGames();
$newDlcs = $homepageViewModel->getNewDlcs();
$popularGames = $homepageViewModel->getPopularGames();
require_once("header.php");
?>

<main class="container">
    <div class="bg-body-tertiary p-3 rounded mb-3">
        <div class="row row-cols">
            <div class="col">
                <div class="input-group mb-1">
                    <input type="text" class="form-control" placeholder="PSN name..." id="player" maxlength="16" aria-label="PSN name..." aria-describedby="player-button">
                    <button class="btn btn-primary" type="button" id="player-button">Update</button>
                </div>
            </div>
        </div>
        
        <div class="row justify-content-center" id="queue-result" style="display: none;">
            <div class="col text-center">
                <span id="add-to-queue-result"></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8">
            <!-- New Games -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New Games</h1>
                        <div class="row">
                            <?php
                            foreach ($newGames as $game) {
                                $gameUrl = $game->getRelativeUrl($utility);
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <div class="vstack gap-1">
                                        <!-- image, platforms and status -->
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="<?= $gameUrl; ?>">
                                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="<?= $game->getIconPath(); ?>" alt="<?= htmlentities($game->getName()); ?>">
                                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                                            <?php
                                                            foreach ($game->getPlatforms() as $platform) {
                                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . htmlentities($platform) . "</span> ";
                                                            }
                                                            ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-platinum.svg" alt="Platinum" height="18"> <span class="trophy-platinum"><?= $game->getPlatinum(); ?></span> &bull; <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $game->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $game->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $game->getBronze(); ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $gameUrl; ?>">
                                                <?= htmlentities($game->getName()); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New DLCs -->
            <div class="row mb-3">
                <div class="col">
                    <div class="bg-body-tertiary p-3 rounded">
                        <h1>New DLCs</h1>
                        <div class="row">
                            <?php
                            foreach ($newDlcs as $dlc) {
                                $dlcUrl = $dlc->getRelativeUrl($utility);
                                ?>
                                <div class="col-12 col-md-6 col-lg-4 col-xl-3 text-center mb-2">
                                    <!-- image, platforms and status -->
                                    <div class="vstack gap-1">
                                        <div>
                                            <div class="card">
                                                <div class="d-flex justify-content-center align-items-center" style="min-height: 11.5rem;">
                                                    <a href="<?= $dlcUrl; ?>">
                                                        <img class="card-img object-fit-scale" style="height: 11.5rem;" src="<?= $dlc->getIconPath(); ?>" alt="<?= htmlentities($dlc->getGroupName()); ?>">
                                                        <div class="card-img-overlay d-flex align-items-end p-2">
                                                            <?php
                                                            foreach ($dlc->getPlatforms() as $platform) {
                                                                echo "<span class=\"badge rounded-pill text-bg-primary p-2 me-1\">" . htmlentities($platform) . "</span> ";
                                                            }
                                                            ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- trophies -->
                                        <div>
                                            <img src="/img/trophy-gold.svg" alt="Gold" height="18"> <span class="trophy-gold"><?= $dlc->getGold(); ?></span> &bull; <img src="/img/trophy-silver.svg" alt="Silver" height="18"> <span class="trophy-silver"><?= $dlc->getSilver(); ?></span> &bull; <img src="/img/trophy-bronze.svg" alt="Bronze" height="18"> <span class="trophy-bronze"><?= $dlc->getBronze(); ?></span>
                                        </div>

                                        <!-- name -->
                                        <div class="text-center">
                                            <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $dlcUrl; ?>">
                                                <small><?= htmlentities($dlc->getName()); ?></small><br><?= htmlentities($dlc->getGroupName()); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Popular Games -->
        <div class="col-12 col-lg-4">
            <div class="bg-body-tertiary p-3 rounded">
                <h1>Popular Games</h1>
                <?php
                foreach ($popularGames as $game) {
                    $gameUrl = $game->getRelativeUrl($utility);
                    ?>
                    <div class="row mb-3">
                        <!-- image -->
                        <div class="col-4">
                            <div class="card">
                                <div class="d-flex justify-content-center align-items-center" style="height: 7rem;">
                                    <a href="<?= $gameUrl; ?>">
                                        <img class="card-img object-fit-cover" style="height: 7rem;" src="<?= $game->getIconPath(); ?>" alt="<?= htmlentities($game->getName()); ?>">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- name, platforms and status -->
                        <div class="col-5 d-flex align-items-center">
                            <div>
                                <div class="row">
                                    <div class="col">
                                        <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="<?= $gameUrl; ?>">
                                            <?= htmlentities($game->getName()); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <?php
                                        foreach ($game->getPlatforms() as $platform) {
                                            echo "<span class=\"badge rounded-pill text-bg-primary p-2 mt-2\">" . htmlentities($platform) . "</span> ";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Players -->
                        <div class="col-3 text-end d-flex align-items-center">
                            <div class="ms-auto">
                                <span class="fw-bold"><?= number_format($game->getRecentPlayers()); ?></span><br>Players
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</main>



<script>
class PlayerQueueManager {
    constructor({
        playerInputId,
        buttonId,
        queueResultContainerId,
        messageElementId,
        pollInterval = 3000,
    }) {
        this.playerInput = document.getElementById(playerInputId);
        this.button = document.getElementById(buttonId);
        this.queueResultContainer = document.getElementById(queueResultContainerId);
        this.messageElement = document.getElementById(messageElementId);
        this.pollInterval = pollInterval;
        this.timerId = null;
    }

    initialize() {
        if (!this.playerInput || !this.button || !this.queueResultContainer || !this.messageElement) {
            return;
        }

        this.button.addEventListener('click', () => this.addToQueue());
        this.playerInput.addEventListener('keyup', (event) => this.handleKeyUp(event));
    }

    handleKeyUp(event) {
        const key = event.key || event.keyCode;
        if (key === 'Enter' || key === 13) {
            event.preventDefault();
            this.addToQueue();
        }
    }

    addToQueue() {
        const player = this.playerInput.value;
        const url = `add_to_queue.php?q=${encodeURIComponent(player)}`;

        this.sendRequest(url, (response) => {
            this.updateQueueResult(response.message);

            if (response.shouldPoll) {
                this.startPolling(player);
            } else {
                this.stopPolling();
            }
        });
    }

    startPolling(player) {
        this.stopPolling();
        this.timerId = window.setInterval(() => this.checkQueuePosition(player), this.pollInterval);
    }

    checkQueuePosition(player) {
        const url = `check_queue_position.php?q=${encodeURIComponent(player)}`;

        this.sendRequest(url, (response) => {
            this.updateQueueResult(response.message);

            if (!response.shouldPoll) {
                this.stopPolling();
            }
        });
    }

    stopPolling() {
        if (this.timerId !== null) {
            window.clearInterval(this.timerId);
            this.timerId = null;
        }
    }

    sendRequest(url, onSuccess) {
        const request = new XMLHttpRequest();

        request.onreadystatechange = () => {
            if (request.readyState === XMLHttpRequest.DONE) {
                if (request.status >= 200 && request.status < 300) {
                    const response = this.parseResponse(request.responseText);

                    if (response !== null) {
                        onSuccess(response);
                    } else {
                        this.handleError();
                    }
                } else {
                    this.handleError();
                }
            }
        };

        request.onerror = () => this.handleError();

        request.open('GET', url, true);
        request.setRequestHeader('Accept', 'application/json');
        request.send();
    }

    parseResponse(responseText) {
        if (!responseText) {
            return null;
        }

        try {
            const data = JSON.parse(responseText);

            if (typeof data !== 'object' || data === null) {
                return null;
            }

            const message = typeof data.message === 'string' ? data.message : '';
            const shouldPoll = typeof data.shouldPoll === 'boolean' ? data.shouldPoll : false;
            const status = typeof data.status === 'string' ? data.status : 'error';

            return { message, shouldPoll, status };
        } catch (error) {
            return null;
        }
    }

    updateQueueResult(message) {
        if (this.messageElement) {
            this.messageElement.innerHTML = message;
        }

        this.showQueueResult();
    }

    showQueueResult() {
        if (this.queueResultContainer) {
            this.queueResultContainer.style.display = '';
        }
    }

    handleError() {
        this.updateQueueResult('An error occurred while contacting the server. Please try again later.');
        this.stopPolling();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const queueManager = new PlayerQueueManager({
        playerInputId: 'player',
        buttonId: 'player-button',
        queueResultContainerId: 'queue-result',
        messageElementId: 'add-to-queue-result',
        pollInterval: 3000,
    });

    queueManager.initialize();
    window.playerQueueManager = queueManager;
});
</script>



<?php
require_once("footer.php");
?>
