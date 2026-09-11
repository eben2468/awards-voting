# Build Update Package Script
$files = @(
  "admin/categories/add.php",
  "admin/categories/edit.php",
  "admin/categories/index.php",
  "admin/events/add.php",
  "admin/events/edit.php",
  "admin/includes/sidebar.php",
  "admin/nominees/add.php",
  "admin/nominees/edit.php",
  "admin/payouts/view.php",
  "admin/reports/index.php",
  "admin/settings.php",
  "admin/transactions.php",
  "admin/users/index.php",
  "assets/css/admin.css",
  "assets/css/style.css",
  "assets/js/main.js",
  "config/database.php",
  "config/nalo.php",
  "contact.php",
  "database/apply_updates.php",
  "database/schema_updates.sql",
  "event-details.php",
  "events.php",
  "faq.php",
  "how-it-works.php",
  "includes/footer.php",
  "includes/header.php",
  "includes/vote-modal.php",
  "index.php",
  "login.php",
  "organizer-documentation.md",
  "organizer/categories/add.php",
  "organizer/categories/edit.php",
  "organizer/categories/index.php",
  "organizer/events/add.php",
  "organizer/events/edit.php",
  "organizer/includes/sidebar.php",
  "organizer/payouts/index.php",
  "organizer/reports/index.php",
  "register.php",
  "results.php",
  "ussd/callback.php",
  "ussd/index.php",
  "verify-payment.php",
  "vote.php",
  "admin/ajax/toggle_results_access.php",
  "admin/ajax/update_setting.php",
  "admin/pending-notifications.php",
  "admin/sync_pending.php",
  "admin/ticket-analytics.php",
  "api/admin/send_pending_notifications.php",
  "api/check-payment.php",
  "api/process-card.php",
  "buy-ticket.php",
  "cron/deactivate_expired_events.php",
  "database/migrations/add_phone_to_transactions.sql",
  "database/migrations/add_ticketing_system.sql",
  "database/run_ticketing_migration.php",
  "includes/ticket-modal.php",
  "live_database_migration.sql",
  "run_database_update.php",
  "live_migration_helper.php",
  "migrate_access_codes.php",
  "nominee-results.php",
  "notifications.php",
  "organizer/ticket-analytics.php",
  "ticketing.php"
)

$tempDir = "update_temp"
if (Test-Path $tempDir) {
    Remove-Item -Recurse -Force $tempDir
}
New-Item -ItemType Directory -Path $tempDir | Out-Null

foreach ($file in $files) {
    if (Test-Path $file) {
        $dest = Join-Path $tempDir $file
        $parent = Split-Path $dest -Parent
        if (!(Test-Path $parent)) {
            New-Item -ItemType Directory -Path $parent | Out-Null
        }
        Copy-Item $file $dest
    } else {
        Write-Warning "File not found, skipping: $file"
    }
}

$zipFile = "update_package.zip"
if (Test-Path $zipFile) {
    Remove-Item $zipFile
}

Write-Output "Compressing files into $zipFile..."
Compress-Archive -Path "$tempDir\*" -DestinationPath $zipFile -Force

Remove-Item -Recurse -Force $tempDir
Write-Output "Zip file generated successfully!"
