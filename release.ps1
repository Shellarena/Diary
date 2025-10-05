# PowerShell Script für automatisches Tagging
# Verwendung: .\release.ps1

# Lese Version aus version.json
$versionFile = "version.json"
if (-not (Test-Path $versionFile)) {
    Write-Error "version.json nicht gefunden!"
    exit 1
}

$versionData = Get-Content $versionFile | ConvertFrom-Json
$version = "v" + $versionData.version
$description = $versionData.description

Write-Host "Creating release for version: $version" -ForegroundColor Green
Write-Host "Description: $description" -ForegroundColor Yellow

# Prüfe ob Tag bereits existiert
$tagExists = git tag -l $version
if ($tagExists) {
    Write-Warning "Tag $version exists already!"
    $response = Read-Host "Do you want to delete and recreate it? (y/N)"
    if ($response -eq 'y' -or $response -eq 'Y') {
        git tag -d $version
        git push origin --delete $version
    } else {
        Write-Host "Aborted."
        exit 0
    }
}

# Erstelle Git Tag
Write-Host "Creating git tag..." -ForegroundColor Blue
git add .
git commit -m "Release $version: $description"
git tag -a $version -m "$description"

# Push zu GitHub
Write-Host "Pushing to GitHub..." -ForegroundColor Blue
git push origin
git push origin $version

Write-Host "✅ Release $version created successfully!" -ForegroundColor Green
Write-Host "🔗 Check: https://github.com/Shellarena/Diary/releases" -ForegroundColor Cyan