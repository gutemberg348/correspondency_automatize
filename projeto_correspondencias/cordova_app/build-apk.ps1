$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$ToolsRoot = Join-Path $ProjectRoot '.build-tools'
$JdkZip = Join-Path $ToolsRoot 'temurin-jdk17.zip'
$JdkDir = Join-Path $ToolsRoot 'jdk-17'
$JdkExtract = Join-Path $ToolsRoot 'jdk-extract'
$AndroidSdkRoot = Join-Path $ToolsRoot 'android-sdk'
$AdbExe = Join-Path $AndroidSdkRoot 'platform-tools\adb.exe'
$PlatformToolsDir = Join-Path $AndroidSdkRoot 'platform-tools'
$CmdZip = Join-Path $ToolsRoot 'android-commandlinetools.zip'
$CmdExtract = Join-Path $ToolsRoot 'android-commandlinetools-extract'
$CmdlineRoot = Join-Path $AndroidSdkRoot 'cmdline-tools'
$CmdlineLatest = Join-Path $CmdlineRoot 'latest'
$SdkManager = Join-Path $CmdlineLatest 'bin\sdkmanager.bat'
$GradleZip = Join-Path $ToolsRoot 'gradle-8.7-bin.zip'
$GradleDir = Join-Path $ToolsRoot 'gradle-8.7'
$GradleExtract = Join-Path $ToolsRoot 'gradle-extract'

$JdkUrl = 'https://api.adoptium.net/v3/binary/latest/17/ga/windows/x64/jdk/hotspot/normal/eclipse?project=jdk'
$AndroidToolsUrl = 'https://dl.google.com/android/repository/commandlinetools-win-14742923_latest.zip'
$GradleUrl = 'https://services.gradle.org/distributions/gradle-8.7-bin.zip'

function Get-BuildFile {
    param(
        [string] $Url,
        [string] $Output
    )

    if (Test-Path $Output) {
        return
    }

    Write-Host "Baixando $Url"
    Invoke-WebRequest -Uri $Url -OutFile $Output -UseBasicParsing
}

New-Item -ItemType Directory -Force -Path $ToolsRoot | Out-Null

Get-BuildFile -Url $JdkUrl -Output $JdkZip
if (-not (Test-Path (Join-Path $JdkDir 'bin\javac.exe'))) {
    Remove-Item -LiteralPath $JdkDir -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $JdkExtract -Recurse -Force -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path $JdkExtract | Out-Null
    Expand-Archive -LiteralPath $JdkZip -DestinationPath $JdkExtract -Force
    $ExtractedJdk = Get-ChildItem -LiteralPath $JdkExtract -Directory | Select-Object -First 1

    if (-not $ExtractedJdk) {
        throw 'Nao foi possivel localizar o JDK extraido.'
    }

    Move-Item -LiteralPath $ExtractedJdk.FullName -Destination $JdkDir
    Remove-Item -LiteralPath $JdkExtract -Recurse -Force
}

Get-BuildFile -Url $AndroidToolsUrl -Output $CmdZip
if (-not (Test-Path $SdkManager)) {
    Remove-Item -LiteralPath $CmdlineLatest -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $CmdExtract -Recurse -Force -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path $CmdExtract | Out-Null
    New-Item -ItemType Directory -Force -Path $CmdlineRoot | Out-Null
    tar -xf $CmdZip -C $CmdExtract

    $ExtractedCmdline = Join-Path $CmdExtract 'cmdline-tools'
    if (-not (Test-Path $ExtractedCmdline)) {
        throw 'Nao foi possivel localizar o Android command-line tools extraido.'
    }

    Move-Item -LiteralPath $ExtractedCmdline -Destination $CmdlineLatest
    Remove-Item -LiteralPath $CmdExtract -Recurse -Force
}

Get-BuildFile -Url $GradleUrl -Output $GradleZip
if (-not (Test-Path (Join-Path $GradleDir 'bin\gradle.bat'))) {
    Remove-Item -LiteralPath $GradleDir -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $GradleExtract -Recurse -Force -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path $GradleExtract | Out-Null
    tar -xf $GradleZip -C $GradleExtract
    Move-Item -LiteralPath (Join-Path $GradleExtract 'gradle-8.7') -Destination $GradleDir
    Remove-Item -LiteralPath $GradleExtract -Recurse -Force
}

$env:JAVA_HOME = $JdkDir
$env:ANDROID_HOME = $AndroidSdkRoot
$env:ANDROID_SDK_ROOT = $AndroidSdkRoot
$env:Path = "$JdkDir\bin;$CmdlineLatest\bin;$AndroidSdkRoot\platform-tools;$GradleDir\bin;$env:Path"

$Yes = "y`n" * 100
$Packages = @(
    'platform-tools',
    'platforms;android-34',
    'build-tools;34.0.0'
)

if (Test-Path $AdbExe) {
    $AdbFile = Get-Item -LiteralPath $AdbExe
    if ($AdbFile.Length -eq 0) {
        Write-Host 'Removendo platform-tools corrompido para reinstalar o ADB'
        Remove-Item -LiteralPath $PlatformToolsDir -Recurse -Force
    }
}

Write-Host 'Aceitando licencas Android SDK'
$Yes | & $SdkManager --sdk_root=$AndroidSdkRoot --licenses

Write-Host 'Instalando pacotes Android SDK'
$Yes | & $SdkManager --sdk_root=$AndroidSdkRoot @Packages

if (-not (Test-Path $AdbExe) -or (Get-Item -LiteralPath $AdbExe).Length -eq 0) {
    throw 'ADB nao foi instalado corretamente. Rode o build novamente ou reinstale o Android SDK platform-tools.'
}

Set-Location $ProjectRoot
cordova prepare android
cordova build android --debug
