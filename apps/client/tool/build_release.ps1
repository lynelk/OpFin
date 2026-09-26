# Run on the authorised Windows signing workstation. Never upload key.properties.
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f]{40}$')]
    [string]$ExpectedSourceCommit,
    [string]$ApiBaseUrl = 'https://opfin-production.up.railway.app/api',
    [string]$FlutterExecutable = 'flutter',
    [string]$JavaHome = 'C:\Program Files\Android\Android Studio\jbr',
    [ValidatePattern('^[0-9A-Fa-f]{64}$')]
    [string]$ExpectedUploadCertificate = '7A77EBA5B7179FEA28516EF10EF88BFB1E3804718AD74882C346587B7EBEA358'
)

$ErrorActionPreference = 'Stop'
$opfinPreviousCi = $env:CI
$opfinClient = Split-Path -Parent $PSScriptRoot
$opfinAndroidNamespace = 'http://schemas.android.com/apk/res/android'
$opfinForbiddenPermissions = @(
    'ACCESS_FINE_LOCATION', 'ACCESS_BACKGROUND_LOCATION', 'READ_CONTACTS',
    'READ_EXTERNAL_STORAGE', 'WRITE_EXTERNAL_STORAGE', 'MANAGE_EXTERNAL_STORAGE', 'READ_MEDIA_IMAGES',
    'READ_MEDIA_VIDEO', 'READ_PHONE_NUMBERS', 'QUERY_ALL_PACKAGES',
    'READ_SMS', 'RECEIVE_SMS', 'SEND_SMS', 'READ_CALL_LOG', 'WRITE_CALL_LOG'
)

function Invoke-OpfinCommand {
    param([string]$Program, [string[]]$Arguments)
    Get-Command -Name $Program -CommandType Application -ErrorAction Stop | Out-Null
    # Windows PowerShell can turn harmless native stderr warnings into errors.
    # Preserve the output, but use the process exit code to decide success.
    $opfinCommandPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & $Program @Arguments
        $opfinExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $opfinCommandPreference
    }
    if ($opfinExitCode -ne 0) {
        throw "$Program failed with exit code $opfinExitCode. Stop before uploading."
    }
}

function Assert-OpfinManifest {
    param([xml]$Manifest)
    foreach ($opfinPermission in $Manifest.SelectNodes('/manifest/uses-permission | /manifest/uses-permission-sdk-23')) {
        $opfinName = $opfinPermission.GetAttribute('name', $opfinAndroidNamespace)
        if ($opfinName -in ($opfinForbiddenPermissions | ForEach-Object { "android.permission.$_" })) {
            throw "Forbidden release permission: $opfinName"
        }
    }
    foreach ($opfinFeatureName in @('android.hardware.camera', 'android.hardware.camera.autofocus', 'android.hardware.location', 'android.hardware.location.network')) {
        $opfinFeature = @($Manifest.SelectNodes('/manifest/uses-feature') | Where-Object {
            $_.GetAttribute('name', $opfinAndroidNamespace) -eq $opfinFeatureName
        })
        if ($opfinFeature.Count -ne 1 -or $opfinFeature[0].GetAttribute('required', $opfinAndroidNamespace) -ne 'false') {
            throw "Installation hardware must be optional: $opfinFeatureName"
        }
    }
}

Push-Location $opfinClient
try {
    $opfinSource = (Invoke-OpfinCommand 'git' @('rev-parse', 'HEAD') | Out-String).Trim()
    if ($opfinSource -ne $ExpectedSourceCommit) { throw 'The checkout does not match the reviewed source commit.' }
    $opfinChanges = Invoke-OpfinCommand 'git' @('status', '--porcelain', '--untracked-files=no')
    if ($opfinChanges) { throw 'Tracked files have local changes. Use a clean release worktree and preserve the original checkout.' }
    if (-not (Test-Path -LiteralPath 'android/key.properties' -PathType Leaf)) {
        throw 'Configure android/key.properties locally using the existing upload keystore.'
    }

    $opfinUri = [uri]$ApiBaseUrl
    if (-not $opfinUri.IsAbsoluteUri -or $opfinUri.Scheme -ne 'https' -or
        $opfinUri.HostNameType -ne [System.UriHostNameType]::Dns -or
        $opfinUri.Host -notmatch '\.[a-zA-Z]+$' -or
        $opfinUri.Host -match '(^|\.)(localhost|local|internal|test|invalid)\.?$' -or
        $opfinUri.UserInfo -or $opfinUri.Query -or $opfinUri.Fragment -or
        $opfinUri.AbsolutePath -notin @('/api', '/api/') -or $ApiBaseUrl -ne $ApiBaseUrl.Trim()) {
        throw 'Use the verified public HTTPS API URL ending in /api, without credentials, query or fragment.'
    }
    $opfinJava = Join-Path $JavaHome 'bin/java.exe'
    $opfinKeytool = Join-Path $JavaHome 'bin/keytool.exe'
    $opfinJarsigner = Join-Path $JavaHome 'bin/jarsigner.exe'
    foreach ($opfinExecutable in @($opfinJava, $opfinKeytool, $opfinJarsigner)) {
        if (-not (Test-Path -LiteralPath $opfinExecutable -PathType Leaf)) { throw "Missing JDK tool: $opfinExecutable" }
    }

    $opfinVersionLine = Select-String -Path 'pubspec.yaml' -Pattern '^version:\s*(\d+\.\d+\.\d+)\+(\d+)\s*$'
    if (-not $opfinVersionLine) { throw 'pubspec.yaml must declare a release name and version code.' }
    $opfinVersionName = $opfinVersionLine.Matches[0].Groups[1].Value
    $opfinVersionCode = $opfinVersionLine.Matches[0].Groups[2].Value
    Assert-OpfinManifest ([xml](Get-Content -Raw 'android/app/src/main/AndroidManifest.xml'))

    $opfinFlutterInfo = (Invoke-OpfinCommand $FlutterExecutable @('--version', '--machine') | Out-String) | ConvertFrom-Json
    if ($opfinFlutterInfo.frameworkVersion -ne '3.47.1') { throw 'Use the reviewed Flutter 3.47.1 toolchain for this candidate.' }
    Invoke-OpfinCommand $FlutterExecutable @('pub', 'get')
    Invoke-OpfinCommand $FlutterExecutable @('analyze', '--no-pub')
    Invoke-OpfinCommand $FlutterExecutable @('test', '--no-pub')

    # Require real local signing even if this workstation has a CI variable set.
    $env:CI = 'false'
    Invoke-OpfinCommand $FlutterExecutable @(
        'build', 'appbundle', '--release', '--no-pub',
        "--dart-define=OPFIN_API_BASE_URL=$($ApiBaseUrl.TrimEnd('/'))",
        '--dart-define=OPFIN_APP_STORE_P2P_BORROWING_ENABLED=false'
    )
    $opfinChanges = Invoke-OpfinCommand 'git' @('status', '--porcelain', '--untracked-files=no')
    if ($opfinChanges) { throw 'The toolchain changed tracked files. Review those changes before distributing this build.' }
    $opfinBundle = Join-Path $opfinClient 'build/app/outputs/bundle/release/app-release.aab'
    if (-not (Test-Path -LiteralPath $opfinBundle -PathType Leaf)) { throw 'The build did not produce an AAB.' }

    # jarsigner checks content integrity; the pinned public certificate below checks signer identity.
    Invoke-OpfinCommand $opfinJarsigner @('-verify', $opfinBundle)
    $opfinCertificate = (Invoke-OpfinCommand $opfinKeytool @(
        '-J-Duser.language=en', '-J-Duser.country=US', '-printcert', '-jarfile', $opfinBundle
    ) | Out-String)
    $opfinFingerprints = [regex]::Matches($opfinCertificate, 'SHA256:\s*([0-9A-Fa-f:]+)')
    if ($opfinFingerprints.Count -ne 1 -or
        $opfinFingerprints[0].Groups[1].Value.Replace(':', '') -ne $ExpectedUploadCertificate) {
        throw 'The AAB signer differs from the expected upload certificate. Verify Play App signing before continuing.'
    }

    $opfinTools = Join-Path $opfinClient 'build/release-tools'
    New-Item -ItemType Directory -Path $opfinTools -Force | Out-Null
    $opfinBundletool = Join-Path $opfinTools 'bundletool-1.18.3.jar'
    if (-not (Test-Path -LiteralPath $opfinBundletool -PathType Leaf)) {
        Invoke-WebRequest -UseBasicParsing -Uri 'https://github.com/google/bundletool/releases/download/1.18.3/bundletool-all-1.18.3.jar' -OutFile $opfinBundletool
    }
    if ((Get-FileHash -Algorithm SHA256 -LiteralPath $opfinBundletool).Hash -ne 'a099cfa1543f55593bc2ed16a70a7c67fe54b1747bb7301f37fdfd6d91028e29') {
        throw 'bundletool checksum mismatch. Do not run this downloaded file.'
    }
    Invoke-OpfinCommand $opfinJava @('-jar', $opfinBundletool, 'validate', "--bundle=$opfinBundle")
    $opfinManifestText = (Invoke-OpfinCommand $opfinJava @('-jar', $opfinBundletool, 'dump', 'manifest', "--bundle=$opfinBundle", '--module=base') | Out-String)
    [xml]$opfinMergedManifest = $opfinManifestText
    Assert-OpfinManifest $opfinMergedManifest
    $opfinManifestRoot = $opfinMergedManifest.DocumentElement
    $opfinApplication = $opfinMergedManifest.SelectSingleNode('/manifest/application')
    $opfinUsesSdk = $opfinMergedManifest.SelectSingleNode('/manifest/uses-sdk')
    if ($opfinManifestRoot.GetAttribute('package') -ne 'org.rotaryo.opfin' -or
        $opfinManifestRoot.GetAttribute('versionCode', $opfinAndroidNamespace) -ne $opfinVersionCode -or
        $opfinManifestRoot.GetAttribute('versionName', $opfinAndroidNamespace) -ne $opfinVersionName -or
        $opfinApplication.GetAttribute('debuggable', $opfinAndroidNamespace) -eq 'true' -or
        [int]$opfinUsesSdk.GetAttribute('targetSdkVersion', $opfinAndroidNamespace) -lt 36) {
        throw 'The AAB package, version, release mode or target SDK does not match the release contract.'
    }

    $opfinDelivery = Join-Path $opfinClient 'build/release-delivery'
    New-Item -ItemType Directory -Path $opfinDelivery -Force | Out-Null
    $opfinDeliveryBundle = Join-Path $opfinDelivery "OpFin-$opfinVersionName-$opfinVersionCode.aab"
    Copy-Item -LiteralPath $opfinBundle -Destination $opfinDeliveryBundle -Force
    $opfinHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $opfinDeliveryBundle).Hash.ToLowerInvariant()
    $opfinManifestText | Set-Content -Encoding UTF8 (Join-Path $opfinDelivery 'AndroidManifest.xml')
    "$opfinHash  $([IO.Path]::GetFileName($opfinDeliveryBundle))" | Set-Content -Encoding ASCII (Join-Path $opfinDelivery 'SHA256SUMS.txt')
    [ordered]@{
        source_commit = $opfinSource
        version_name = $opfinVersionName
        version_code = $opfinVersionCode
        package = 'org.rotaryo.opfin'
        api_base_url = $ApiBaseUrl.TrimEnd('/')
        p2p_borrowing_enabled = $false
        upload_certificate_sha256 = $ExpectedUploadCertificate.ToUpperInvariant()
        aab_sha256 = $opfinHash
        flutter_version = $opfinFlutterInfo.frameworkVersion
        min_sdk = $opfinUsesSdk.GetAttribute('minSdkVersion', $opfinAndroidNamespace)
        target_sdk = $opfinUsesSdk.GetAttribute('targetSdkVersion', $opfinAndroidNamespace)
        built_at_utc = [DateTime]::UtcNow.ToString('o')
        published = $false
    } | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $opfinDelivery 'release-record.json')
    Write-Host "Verified release files: $opfinDelivery"
    Write-Host 'Upload the AAB to internal testing. Complete device/UAT and Play checks before production promotion.'
    explorer.exe $opfinDelivery
} finally {
    $env:CI = $opfinPreviousCi
    Pop-Location
}
