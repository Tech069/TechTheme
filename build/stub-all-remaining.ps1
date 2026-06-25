$base = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app"
$count = 0

# All remaining ionCube files to stub
$files = @(
    # Console Commands
    "Console\Commands\CheckScheduledWipes.php|CheckScheduledWipes|Console\Command",
    "Console\Commands\GenerateEmailPreview.php|GenerateEmailPreview|Console\Command",
    "Console\Commands\HyperV2MigrateLegacySettingsCommand.php|HyperV2MigrateLegacySettingsCommand|Console\Command",
    "Console\Commands\ProcessScheduledWipes.php|ProcessScheduledWipes|Console\Command",
    "Console\Commands\SessionGarbageCollect.php|SessionGarbageCollect|Console\Command",
    # DGEN Commands
    "Console\Commands\DGEN\ArmaReforgerWebhookLogCommand.php|ArmaReforgerWebhookLogCommand|Console\Command",
    "Console\Commands\DGEN\CheckNodeStatusCommand.php|CheckNodeStatusCommand|Console\Command",
    "Console\Commands\DGEN\RunDiscordBot.php|RunDiscordBot|Console\Command",
    "Console\Commands\DGEN\SeedDdosDemoCommand.php|SeedDdosDemoCommand|Console\Command",
    "Console\Commands\DGEN\SyncDdosAlertsCommand.php|SyncDdosAlertsCommand|Console\Command",
    "Console\Commands\DGEN\SyncDiscordRoles.php|SyncDiscordRoles|Console\Command",
    "Console\Commands\DGEN\SyncServerSplitsCommand.php|SyncServerSplitsCommand|Console\Command",
    "Console\Commands\DGEN\SyncServerStatsCommand.php|SyncServerStatsCommand|Console\Command",
    "Console\Commands\DGEN\TestDdosWebhookCommand.php|TestDdosWebhookCommand|Console\Command",
    "Console\Commands\DGEN\Billing\AutoRenewalCommand.php|AutoRenewalCommand|Console\Command",
    "Console\Commands\DGEN\Billing\CleanupPendingPaymentsCommand.php|CleanupPendingPaymentsCommand|Console\Command",
    # Maintenance
    "Console\Commands\Maintenance\CleanRecycleBinCommand.php|CleanRecycleBinCommand|Console\Command",
    "Console\Commands\Maintenance\CleanTempDatabaseExportsCommand.php|CleanTempDatabaseExportsCommand|Console\Command",
    # Server
    "Console\Commands\Server\AutoRejectExpiredStaffRequestsCommand.php|AutoRejectExpiredStaffRequestsCommand|Console\Command",
    "Console\Commands\Server\AutoSuspendCommand.php|AutoSuspendCommand|Console\Command",
    "Console\Commands\Server\CleanupOrphanedSubdomainsCommand.php|CleanupOrphanedSubdomainsCommand|Console\Command",
    # Exceptions
    "Exceptions\Service\Subuser\UserNotFoundForSubuserException.php|UserNotFoundForSubuserException|Exception",
    # Socialite
    "Extensions\Socialite\DgenProvider.php|DgenProvider|Laravel\Socialite\Two\AbstractProvider",
    "Extensions\Socialite\DiscordProvider.php|DiscordProvider|Laravel\Socialite\Two\AbstractProvider",
    "Extensions\Socialite\PaymenterProvider.php|PaymenterProvider|Laravel\Socialite\Two\AbstractProvider",
    # Helpers
    "Helpers\ActivityHelpers.php|ActivityHelpers",
    "Helpers\ClientIpHelper.php|ClientIpHelper",
    "Helpers\DgenDiscordApi.php|DgenDiscordApi",
    "Helpers\IpDetailsHelper.php|IpDetailsHelper",
    "Helpers\UserAgentHelper.php|UserAgentHelper",
    # Admin DGEN
    "Http\Controllers\Api\Client\Admin\DGEN\AdminBillingController.php|AdminBillingController|App\Http\Controllers\Controller",
    # Billing
    "Http\Controllers\Api\Client\DGEN\Billing\BillingController.php|BillingController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\DGEN\Billing\OrderController.php|OrderController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\DGEN\Billing\PromoCodeController.php|PromoCodeController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\DGEN\Billing\StoreController.php|StoreController|App\Http\Controllers\Controller",
    # Removed games
    "Http\Controllers\Api\Client\Servers\DGEN\ArkModCacheController.php|ArkModCacheController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\ArkModController.php|ArkModController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\ArmaReforgerAdminToolsController.php|ArmaReforgerAdminToolsController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\ArmaReforgerConfigController.php|ArmaReforgerConfigController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\ArmaReforgerModManagerController.php|ArmaReforgerModManagerController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\FiveMUtilsController.php|FiveMUtilsController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\HytaleModController.php|HytaleModController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\HytaleWorldCacheController.php|HytaleWorldCacheController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Client\Servers\DGEN\HytaleWorldController.php|HytaleWorldController|App\Http\Controllers\Controller",
    # Public
    "Http\Controllers\Api\Public\NodeBackupApiController.php|NodeBackupApiController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Public\PublicAddonSettingsController.php|PublicAddonSettingsController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Public\WingsAddonSettingsController.php|WingsAddonSettingsController|App\Http\Controllers\Controller",
    "Http\Controllers\Api\Public\WingsHealthController.php|WingsHealthController|App\Http\Controllers\Controller",
    # Middleware
    "Http\Middleware\CaptureUserActivity.php|CaptureUserActivity|Closure",
    "Http\Middleware\CompressResponse.php|CompressResponse|Closure",
    "Http\Middleware\Admin\LogAdminAction.php|LogAdminAction|Closure",
    "Http\Middleware\Api\Client\Server\EnsureDiscordMembership.php|EnsureDiscordMembership|Closure",
    # Requests
    "Http\Requests\Api\Client\Account\UpdateAccountInfoRequest.php|UpdateAccountInfoRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Api\Client\Servers\DGEN\MinecraftVotifierTester\TestMinecraftVotifierRequest.php|TestMinecraftVotifierRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Api\Client\Servers\DGEN\ServerImporter\StoreServerImportRequest.php|StoreServerImportRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Api\Client\Servers\DGEN\ServerImporter\TestConnectionRequest.php|TestConnectionRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Api\Client\Servers\DGEN\ServerImporter\UpdateServerImportRequest.php|UpdateServerImportRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Api\Client\Servers\DGEN\ServerSplitter\StoreServerSplitRequest.php|StoreServerSplitRequest|App\Http\Requests\Api\Client\ClientApiRequest",
    "Http\Requests\Auth\RegisterRequest.php|RegisterRequest|App\Http\Requests\Request",
    # Jobs
    "Jobs\ApplyNativeBackupLocalRetention.php|ApplyNativeBackupLocalRetention|App\Jobs\Job",
    "Jobs\HyperV2UpdateJob.php|HyperV2UpdateJob|App\Jobs\Job",
    "Jobs\Job.php|Job|Illuminate\Bus\Queueable",
    "Jobs\RestoreNativeBackupJob.php|RestoreNativeBackupJob|App\Jobs\Job",
    "Jobs\SendHyperV2SecurityAlert.php|SendHyperV2SecurityAlert|App\Jobs\Job",
    "Jobs\TrackAutomatedNodeBackupRestore.php|TrackAutomatedNodeBackupRestore|App\Jobs\Job",
    "Jobs\UploadNativeBackupExternally.php|UploadNativeBackupExternally|App\Jobs\Job",
    "Jobs\DGEN\CalculateRecycleBinFolderSizes.php|CalculateRecycleBinFolderSizes|App\Jobs\Job",
    "Jobs\DGEN\DiagnoseArmaReforgerBadModJob.php|DiagnoseArmaReforgerBadModJob|App\Jobs\Job",
    "Jobs\DGEN\EggSwappingInstallationJob.php|EggSwappingInstallationJob|App\Jobs\Job",
    "Jobs\DGEN\StartCustomModManagerInstallJob.php|StartCustomModManagerInstallJob|App\Jobs\Job",
    # Listeners
    "Listeners\ArmaReforgerWebhookListener.php|ArmaReforgerWebhookListener",
    "Listeners\HyperV2SecurityLogListener.php|HyperV2SecurityLogListener",
    "Listeners\Auth\AuthenticationListener.php|AuthenticationListener",
    "Listeners\Auth\PasswordResetListener.php|PasswordResetListener",
    "Listeners\Auth\SessionCleanupListener.php|SessionCleanupListener",
    "Listeners\Auth\TwoFactorListener.php|TwoFactorListener",
    # Mail
    "Mail\FallbackSmtpTransport.php|FallbackSmtpTransport|Symfony\Component\Mailer\Transport\TransportInterface",
    # Models
    "Models\AdminAuditLog.php|AdminAuditLog|App\Models\Model",
    "Models\AgentServerTransfer.php|AgentServerTransfer|App\Models\Model",
    "Models\CustomMonitor.php|CustomMonitor|App\Models\Model",
    "Models\GithubSourceControlAccount.php|GithubSourceControlAccount|App\Models\Model",
    "Models\GlobalStorageBackend.php|GlobalStorageBackend|App\Models\Model",
    "Models\HyperCommandHistory.php|HyperCommandHistory|App\Models\Model",
    "Models\NodeBackup.php|NodeBackup|App\Models\Model",
    "Models\NodeBackupConfig.php|NodeBackupConfig|App\Models\Model",
    "Models\NodeBackupList.php|NodeBackupList|App\Models\Model",
    "Models\PromoCode.php|PromoCode|App\Models\Model",
    "Models\ReverseProxy.php|ReverseProxy|App\Models\Model",
    "Models\RustMapLibrary.php|RustMapLibrary|App\Models\Model",
    "Models\ServerImport.php|ServerImport|App\Models\Model",
    "Models\ServerQuickAccess.php|ServerQuickAccess|App\Models\Model",
    "Models\ServerRecycleBin.php|ServerRecycleBin|App\Models\Model",
    "Models\ServerSplit.php|ServerSplit|App\Models\Model",
    "Models\ServerSubdomain.php|ServerSubdomain|App\Models\Model",
    "Models\StaffRequest.php|StaffRequest|App\Models\Model",
    "Models\StatusIncident.php|StatusIncident|App\Models\Model",
    "Models\UserIntegration.php|UserIntegration|App\Models\Model",
    "Models\UserLoginHistory.php|UserLoginHistory|App\Models\Model",
    "Models\WipeExecution.php|WipeExecution|App\Models\Model",
    "Models\WipeSchedule.php|WipeSchedule|App\Models\Model",
    # DGEN Models
    "Models\DGEN\DdosAlertEvent.php|DdosAlertEvent|App\Models\Model",
    "Models\DGEN\Game.php|Game|App\Models\Model",
    "Models\DGEN\GameCategory.php|GameCategory|App\Models\Model",
    "Models\DGEN\Payment.php|Payment|App\Models\Model",
    "Models\DGEN\PermissionRole.php|PermissionRole|App\Models\Model",
    "Models\DGEN\ReverseProxyWhitelist.php|ReverseProxyWhitelist|App\Models\Model",
    "Models\DGEN\ServerSplitterWhitelist.php|ServerSplitterWhitelist|App\Models\Model",
    "Models\DGEN\Subcategory.php|Subcategory|App\Models\Model",
    "Models\DGEN\SubdomainManagerWhitelist.php|SubdomainManagerWhitelist|App\Models\Model",
    # DGEN Observers
    "Observers\DGEN\ReverseProxyLimitObserver.php|ReverseProxyLimitObserver",
    "Observers\DGEN\ServerSplitterLimitObserver.php|ServerSplitterLimitObserver",
    "Observers\DGEN\ServerSplitterObserver.php|ServerSplitterObserver",
    "Observers\DGEN\StockObserver.php|StockObserver",
    "Observers\DGEN\SubdomainManagerLimitObserver.php|SubdomainManagerLimitObserver",
    "Observers\DGEN\UserIntegrationObserver.php|UserIntegrationObserver",
    # DGEN Services
    "Services\AddonConfigService.php|AddonConfigService",
    "Services\CrossVpsCacheInvalidationService.php|CrossVpsCacheInvalidationService",
    "Services\CurseForgeService.php|CurseForgeService",
    "Services\HyperV2AddonDefaultsService.php|HyperV2AddonDefaultsService",
    "Services\HyperV2DataSanitizerService.php|HyperV2DataSanitizerService",
    "Services\HyperV2IntegrityService.php|HyperV2IntegrityService",
    "Services\HyperV2LegacySettingsMigrator.php|HyperV2LegacySettingsMigrator",
    "Services\HyperV2RequiredUpdateService.php|HyperV2RequiredUpdateService",
    "Services\HyperV2SecurityAlertService.php|HyperV2SecurityAlertService",
    "Services\HyperV2ValidationRules.php|HyperV2ValidationRules",
    "Services\MinecraftPlayerCountService.php|MinecraftPlayerCountService",
    "Services\MinecraftVotifierTesterService.php|MinecraftVotifierTesterService",
    "Services\PermissionRegistryService.php|PermissionRegistryService",
    "Services\ServerSplitterService.php|ServerSplitterService",
    "Services\ServerWiperService.php|ServerWiperService",
    "Services\ArmaReforger\ArmaReforgerService.php|ArmaReforgerService",
    "Services\ArmaReforger\ArmaReforgerWingsAgentService.php|ArmaReforgerWingsAgentService",
    "Services\DGEN\AutoSuspendService.php|AutoSuspendService",
    "Services\DGEN\CommandHistoryService.php|CommandHistoryService",
    "Services\DGEN\DdosAlertService.php|DdosAlertService",
    "Services\DGEN\DiscordBotService.php|DiscordBotService",
    "Services\DGEN\FastDLNginxService.php|FastDLNginxService",
    "Services\DGEN\FastDLService.php|FastDLService",
    "Services\DGEN\FiveMUtilsService.php|FiveMUtilsService",
    "Services\DGEN\MinecraftPlayerManagerService.php|MinecraftPlayerManagerService",
    "Services\DGEN\NodeStatusService.php|NodeStatusService",
    "Services\DGEN\PermissionRegistryService.php|PermissionRegistryService",
    "Services\DGEN\WingsAgentEndpointResolver.php|WingsAgentEndpointResolver",
    "Services\DGEN\WingsAgentService.php|WingsAgentService",
    "Services\DGEN\WingsNodeDaemonTokenService.php|WingsNodeDaemonTokenService",
    "Services\DGEN\Billing\BillingService.php|BillingService",
    "Services\DGEN\Billing\OrderService.php|OrderService",
    "Services\DGEN\Billing\PaymentGatewayService.php|PaymentGatewayService",
    "Services\DGEN\Billing\StoreService.php|StoreService",
    "Services\DGEN\Helpers\NbtParser.php|NbtParser",
    "Services\DGEN\Helpers\NbtWriter.php|NbtWriter",
    "Services\Eggs\Sharing\RemoteEggService.php|RemoteEggService",
    "Services\Helpers\VueUserEmbedCache.php|VueUserEmbedCache",
    "Services\ReverseProxy\NginxConfigService.php|NginxConfigService",
    "Services\ReverseProxy\ReverseProxyService.php|ReverseProxyService",
    "Services\ReverseProxy\SslService.php|SslService",
    "Services\ServerImporter\TestConnectionService.php|TestConnectionService",
    "Services\SubdomainManager\CloudflareService.php|CloudflareService",
    # Traits
    "Traits\HandlesEtagCache.php|HandlesEtagCache",
    "Traits\DGEN\ChecksAddonAccess.php|ChecksAddonAccess",
    "Traits\DGEN\ManagesFileCache.php|ManagesFileCache",
    "Traits\Helpers\ThemeLanguages.php|ThemeLanguages",
    # Transformers
    "Transformers\Api\Application\NodeSlimTransformer.php|NodeSlimTransformer|League\Fractal\TransformerAbstract",
    "Transformers\Api\Client\RecycleBinFileTransformer.php|RecycleBinFileTransformer|League\Fractal\TransformerAbstract",
    "Transformers\Api\Client\ServerImportTransformer.php|ServerImportTransformer|League\Fractal\TransformerAbstract",
    "Transformers\Api\Client\ServerSplitTransformer.php|ServerSplitTransformer|League\Fractal\TransformerAbstract"
)

foreach ($entry in $files) {
    $parts = $entry -split "\|"
    $relPath = $parts[0]
    $className = $parts[1]
    $extends = if ($parts.Count -gt 2) { $parts[2] } else { "" }
    
    $fullPath = Join-Path $base $relPath
    $dir = Split-Path $fullPath
    if (!(Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    
    # Derive namespace from path
    $nsPath = $relPath -replace "\\", "/"
    $nsPath = $nsPath -replace "/[^/]+\.php$", ""
    $namespace = "App\\" + ($nsPath -replace "/", "\\")
    
    $extClause = if ($extends) { " extends $extends" } else { "" }
    
    $php = "<?php\n\nnamespace $namespace;\n\nuse Illuminate\Support\Facades\Log;\n\nclass $className$extClause\n{\n    public function __construct()\n    {\n        // VexyThemes - TODO: implement\n    }\n}\n"
    
    [System.IO.File]::WriteAllText($fullPath, $php.Replace("`n", "`r`n"))
    $count++
}

Write-Host "Generated $count clean PHP stubs"
