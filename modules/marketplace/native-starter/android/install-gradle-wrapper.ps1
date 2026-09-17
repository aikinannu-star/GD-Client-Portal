param(
    [string]$GradleVersion = "7.5.1",
    [int]$TimeoutSec = 300,
    [int]$Retries = 3
)

function Try-Download {
    param($url, $out)
    for ($i=1; $i -le $Retries; $i++) {
        Write-Host "Attempt $($i): Downloading $url (timeout=$($TimeoutSec)s)..."
        try {
            Invoke-WebRequest -Uri $url -OutFile $out -TimeoutSec $TimeoutSec -UseBasicParsing
            return $true
        } catch {
            Write-Warning "Download attempt $i failed: $_"
            Start-Sleep -Seconds (5 * $i)
        }
    }
    return $false
}

$distUrl = "https://services.gradle.org/distributions/gradle-$GradleVersion-bin.zip"
$tmp = Join-Path $env:TEMP "gradle-$GradleVersion.zip"
$unpack = Join-Path $env:TEMP "gradle_unpack_$GradleVersion"

if (Test-Path "gradle/wrapper/gradle-wrapper.jar") {
    Write-Host "gradle-wrapper.jar already present. Nothing to do."
    exit 0
}

if (-Not (Try-Download -url $distUrl -out $tmp)) {
    Write-Error "Failed to download Gradle distribution from services.gradle.org."
    Write-Host "You can either:"
    Write-Host "  1) Run 'gradle wrapper --gradle-version $GradleVersion' on a machine with Gradle and copy the generated gradle/wrapper/gradle-wrapper.jar here." -ForegroundColor Yellow
    Write-Host "  2) Manually download the distribution, extract 'gradle-$GradleVersion/lib/gradle-wrapper.jar' and place it into 'gradle/wrapper/'."
    exit 1
}

Write-Host "Extracting gradle-wrapper.jar..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
try {
    if (Test-Path $unpack) { Remove-Item -Recurse -Force $unpack }
    [System.IO.Compression.ZipFile]::ExtractToDirectory($tmp, $unpack)
} catch {
    Write-Error "Failed to extract zip: $_"
    exit 1
}

$candidate = Join-Path $unpack "gradle-$GradleVersion/lib/gradle-wrapper.jar"
if (-Not (Test-Path $candidate)) {
    Write-Error "Could not find gradle-wrapper.jar inside distribution."
    exit 1
}

New-Item -ItemType Directory -Force -Path "gradle/wrapper" | Out-Null
Copy-Item -Path $candidate -Destination "gradle/wrapper/gradle-wrapper.jar" -Force

Write-Host "gradle-wrapper.jar installed to gradle/wrapper/gradle-wrapper.jar"
Write-Host "You can now run .\gradlew.bat assembleDebug"
