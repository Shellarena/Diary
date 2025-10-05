<?php
// Test script for version manager
require_once 'public/php/version_manager.php';

echo "Testing Version Manager...\n\n";

$versionManager = new VersionManager();

echo "Current Version:\n";
$current = $versionManager->getCurrentVersion();
print_r($current);

echo "\nChecking for updates...\n";
$updateCheck = $versionManager->checkForUpdates();
print_r($updateCheck);

echo "\nVersion Info for Display:\n";
$versionInfo = $versionManager->getVersionInfo();
print_r($versionInfo);