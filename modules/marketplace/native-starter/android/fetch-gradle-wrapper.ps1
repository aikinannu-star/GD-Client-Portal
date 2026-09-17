param(
    [string]$GradleVersion = '7.5.1'
)

$wrapperDir = Join-Path -Path $PSScriptRoot -ChildPath 'gradle\wrapper'
if (-not (Test-Path $wrapperDir)) { New-Item -ItemType Directory -Path $wrapperDir -Force | Out-Null }

$distUrl = "https://services.gradle.org/distributions/gradle-$GradleVersion-bin.zip"
$tmp = [IO.Path]::GetTempFileName()
Write-Host "Downloading $distUrl... (timeout=300s)"
try {
    Invoke-WebRequest -Uri $distUrl -OutFile $tmp -TimeoutSec 300 -UseBasicParsing
} catch {
    Write-Error "Download failed: $_"
    exit 1
}

Write-Host "Extracting gradle-wrapper.jar..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
try {
    [System.IO.Compression.ZipFile]::ExtractToDirectory($tmp, "$env:TEMP\\gradle_unpack")
} catch {
    Write-Error "Failed to extract distribution: $_"
    exit 1
}
$source = Join-Path -Path "$env:TEMP\gradle_unpack" -ChildPath "gradle-$GradleVersion\lib\gradle-wrapper.jar"
if (Test-Path $source) {
    Copy-Item -Path $source -Destination (Join-Path $wrapperDir 'gradle-wrapper.jar') -Force
    Write-Host "Saved gradle-wrapper.jar to $wrapperDir"
} else {
    Write-Error "Could not find gradle-wrapper.jar in the distribution"
    exit 1
}
Remove-Item -Path "$env:TEMP\gradle_unpack" -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -Path $tmp -Force
Write-Host "Done. You can now run .\gradlew assembleDebug from the android folder."
