$base = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app\Http\Controllers"

# All DGEN controllers referenced in routes that need stub files
$controllers = @(
    # Admin DGEN
    @{ns="Api\Client\Admin\DGEN"; name="PermissionRoleController"},
    
    # Client DGEN
    @{ns="Api\Client\DGEN"; name="WingsAddonController"},
    @{ns="Api\Client\DGEN"; name="LoginActivityController"},
    @{ns="Api\Client\DGEN"; name="DdosAlertController"},
    @{ns="Api\Client\DGEN"; name="FastDLNginxController"},
    
    # Client Admin DGEN
    @{ns="Api\Client\Admin\DGEN"; name="DiscordBotController"},
    
    # Client Theme
    @{ns="Api\Client\Theme"; name="HyperV2ThemeController"},
    @{ns="Api\Client\Theme"; name="HyperV2AddonController"},
    @{ns="Api\Client\Theme"; name="PwaController"},
    
    # Client Auth
    @{ns="Api\Client"; name="DiscordVerificationController"},
    @{ns="Api\Client"; name="AdminUserSearchController"},
    @{ns="Api\Client"; name="ReverseProxyController"},
    
    # Servers DGEN
    @{ns="Api\Client\Servers\DGEN"; name="NodeStatusController"},
    @{ns="Api\Client\Servers\DGEN"; name="CustomMonitorController"},
    @{ns="Api\Client\Servers\DGEN"; name="SubdomainManagerController"},
    @{ns="Api\Client\Servers\DGEN"; name="CustomModManagerController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerTypeChangerController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerTypeChangerWhitelistController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerImporterController"},
    @{ns="Api\Client\Servers\DGEN"; name="UploadFromUrlController"},
    @{ns="Api\Client\Servers\DGEN"; name="GithubSourceControlController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerStatsController"},
    @{ns="Api\Client\Servers\DGEN"; name="StaffRequestController"},
    @{ns="Api\Client\Servers\DGEN"; name="RecycleBinController"},
    @{ns="Api\Client\Servers\DGEN"; name="AutoSuspendController"},
    @{ns="Api\Client\Servers\DGEN"; name="QuickFileAccessController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerSplitterController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerSplitterWhitelistController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerSplitterMigrationController"},
    @{ns="Api\Client\Servers\DGEN"; name="ConfigEditorController"},
    @{ns="Api\Client\Servers\DGEN"; name="StartupPresetsController"},
    @{ns="Api\Client\Servers\DGEN"; name="SchedulePresetsController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerWiperController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerTimeChangerController"},
    @{ns="Api\Client\Servers\DGEN"; name="ReverseProxyWhitelistController"},
    @{ns="Api\Client\Servers\DGEN"; name="FastDLController"},
    @{ns="Api\Client\Servers\DGEN"; name="ServerAgentTicketController"},
    @{ns="Api\Client\Servers\DGEN"; name="NetworkStatisticsController"},
    @{ns="Api\Client\Servers\DGEN"; name="FirewallManagerController"},
    @{ns="Api\Client\Servers\DGEN"; name="CommandHistoryController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftVotifierTesterController"},
    
    # Minecraft
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftVersionController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftPluginController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftPluginCacheController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftModController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftModCacheController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftModpackController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftModpackCacheController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftWorldController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftWorldCacheController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftBedrockAddonController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftBedrockAddonCacheController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftBedrockVersionController"},
    @{ns="Api\Client\Servers\DGEN"; name="MinecraftPlayerManagerController"},
    
    # Auth
    @{ns="Auth"; name="SSOLoginController"},
    @{ns="Auth"; name="RegisterController"},
    @{ns="Auth"; name="PasskeyController"},
    @{ns="Auth"; name="ReferralController"},
    @{ns="Auth"; name="WemxSsoController"},
    
    # Base
    @{ns="Base"; name="HyperV2ThemePublicController"},
    @{ns="Base"; name="LanguageController"},
    @{ns="Base"; name="PublicStatusPageController"},
    @{ns="Base"; name="PublicStatsController"},
    @{ns="Base"; name="HealthController"},
    @{ns="Base"; name="DocumentationController"},
    @{ns="Base"; name="PublicNodeStatusController"},
    
    # API
    @{ns="Api"; name="PublicEggController"},
    
    # Admin extras
    @{ns="Admin"; name="AuditLogController"},
    @{ns="Admin"; name="PanelLogsController"},
    @{ns="Admin"; name="AdminStatisticsController"},
    @{ns="Admin"; name="GlobalStorageBackendController"},
    @{ns="Admin\Nodes"; name="WingsNodeStatsController"},
    @{ns="Admin\Nodes"; name="NodeBackupController"},
    @{ns="Admin\Nests"; name="EggRemoteController"},
    @{ns="Admin\Servers"; name="MassServerActionController"}
)

$count = 0
foreach ($c in $controllers) {
    $ns = $c.ns
    $name = $c.name
    $fullNs = "Pterodactyl\Http\Controllers\$ns"
    $filePath = Join-Path $base "$ns\$name.php"
    
    if (Test-Path $filePath) { continue }
    
    $dir = Split-Path $filePath
    if (!(Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    
    # Determine parent class
    $parent = "Controller"
    if ($ns -match "^Api\\Client") { $parent = "ClientApiController" }
    if ($ns -match "^Api\\Application") { $parent = "ApplicationApiController" }
    
    $content = "<?php`n`nnamespace $fullNs;`n`nuse Pterodactyl\Http\Controllers\$parent;`n`nclass $name extends $parent`n{`n    //`n}`n"
    
    Set-Content -Path $filePath -Value $content
    $count++
}

Write-Host "Created $count stub controllers"
