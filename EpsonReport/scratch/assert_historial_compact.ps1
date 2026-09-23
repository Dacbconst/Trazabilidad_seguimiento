$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# Login as admin first
$urlLogin = "http://127.0.0.1:8999/getters/procesar_login.php"
$body = @{
    usuario = "admin"
    clave   = "12234"
}
try {
    $res = Invoke-WebRequest -Uri $urlLogin -Method POST -Body $body -WebSession $session -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing
} catch {
    $res = $_.Exception.Response
}

$urlAdmin = "http://127.0.0.1:8999/index.php?vista=historial&rol=admin"
$respAdmin = Invoke-WebRequest -Uri $urlAdmin -WebSession $session -UseBasicParsing
$html = $respAdmin.Content

Write-Host "Status Admin: $($respAdmin.StatusCode)"

# Check 1: 16:9 check (Must be FALSE)
$has169 = $html.Contains('16:9')
Write-Host "Check 1 - Contains '16:9': $has169"

# Check 2: Store preview string in user header (Must be FALSE)
$hasUserPdvs = $html.Contains('ep-user-group-pdvs')
Write-Host "Check 2 - Contains 'ep-user-group-pdvs': $hasUserPdvs"

# Check 3: Check PPT button (Must be TRUE)
$hasPptDiario = $html.Contains('PPT Diario')
Write-Host "Check 3 - Has 'PPT Diario' button: $hasPptDiario"

# Check 4: Check template labels in modal (Must be TRUE)
$has1Diapositiva = $html.Contains('1 Diapositiva')
Write-Host "Check 4 - Has '1 Diapositiva' template tag: $has1Diapositiva"

# Check 5: Check compact main class (Must be TRUE)
$hasCompactMain = $html.Contains('ep-registros-main')
Write-Host "Check 5 - Has 'ep-registros-main': $hasCompactMain"

# Check 6: Check record pdv class (Must be TRUE)
$hasRecordPdv = $html.Contains('ep-hist-record-pdv')
Write-Host "Check 6 - Has 'ep-hist-record-pdv': $hasRecordPdv"

# Check 7: Check modal closing tag
$modalCount = ([regex]::Matches($html, 'id="epModalFotoEvidencia"')).Count
Write-Host "Check 7 - Modal foto evidencia exists: ($modalCount == 1)"

# Check 8: Check user groups count
$userGroupCount = ([regex]::Matches($html, 'class="ep-user-group-card"')).Count
Write-Host "Check 8 - User groups rendered: $userGroupCount"

Write-Host "`n=== ALL CHECKS EVALUATED ==="
