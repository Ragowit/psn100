<?php
require_once __DIR__ . '/classes/ApplicationContainer.php';

$applicationContainer = ApplicationContainer::create();

$database = $applicationContainer->getDatabase();
$utility = $applicationContainer->getUtility();
$paginationRenderer = $applicationContainer->getPaginationRenderer();
