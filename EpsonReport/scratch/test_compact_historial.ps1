$resAdmin = Invoke-WebRequest -Uri 'http://127.0.0.1:8999/index.php?vista=historial&rol=admin' -UseBasicParsing
Write-Host "Status Admin: $($resAdmin.StatusCode)"

$has169 = $resAdmin.Content.Contains('16:9')
Write-Host "Contains 16:9 in HTML: $has169"

$hasPdvs = $resAdmin.Content.Contains('ep-user-group-pdvs')
Write-Host "Contains ep-user-group-pdvs in HTML: $hasPdvs"

$resUser = Invoke-WebRequest -Uri 'http://127.0.0.1:8999/index.php?vista=historial&rol=usuario' -UseBasicParsing
Write-Host "Status User: $($resUser.StatusCode)"
Write-Host "Contains 16:9 in User HTML: $($resUser.Content.Contains('16:9'))"
