Add-Type -AssemblyName System.Windows.Forms
$d = New-Object System.Windows.Forms.FolderBrowserDialog
$d.Description = "Choisir le dossier d'archives"

# Utilisation d'un objet .NET pour forcer la boîte de dialogue en haut
$f = [System.Windows.Forms.Form]@{TopMost=$true; TopLevel=$true; ShowInTaskbar=$false; Size='0,0'; WindowState='Minimized'}
$f.Show()
$f.Activate()
$f.Focus()

$result = $d.ShowDialog($f)

if ($result -eq "OK") {
    Write-Output $d.SelectedPath
}
$f.Close()
