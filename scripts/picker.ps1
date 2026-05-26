# scripts/picker.ps1 — Version compatible Windows 7, 8, 10, 11
# Assure la compatibilité avec les anciennes versions de PowerShell

# Forcer le chargement des assemblies Windows Forms
[System.Reflection.Assembly]::LoadWithPartialName("System.Windows.Forms") | Out-Null
[System.Reflection.Assembly]::LoadWithPartialName("System.Drawing") | Out-Null

# Créer l'objet de dialogue
$d = New-Object System.Windows.Forms.FolderBrowserDialog
$d.Description = "Choisir le dossier d'archives"
$d.ShowNewFolderButton = $false

# Créer une fenêtre invisible pour forcer le focus et le "TopMost"
$f = New-Object System.Windows.Forms.Form
$f.TopMost = $true
$f.TopLevel = $true
$f.ShowInTaskbar = $false
$f.Size = New-Object System.Drawing.Size(1,1)
$f.WindowState = [System.Windows.Forms.FormWindowState]::Minimized

# Afficher brièvement pour capter le focus
$f.Show()
$f.Activate()

# Lancer la boîte de dialogue
$result = $d.ShowDialog($f)

if ($result -eq [System.Windows.Forms.DialogResult]::OK) {
    # Sortie brute du chemin pour PHP
    Write-Host $d.SelectedPath -NoNewline
}

$f.Close()
$f.Dispose()
