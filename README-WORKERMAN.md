# 🚀 Guide Workerman pour GED-MEF sur Windows

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![Workerman](https://img.shields.io/badge/Workerman-PHP-FC69B3?logo=php&logoColor=white)
![Windows](https://img.shields.io/badge/Windows-0078D6?logo=windows&logoColor=white)

## Alternative à Swoole : PHP pur et compatible Windows

Workerman fonctionne **nativement sur Windows** sans extension C. C’est l’alternative recommandée pour votre projet.

---

## 📋 Prérequis

✅ **PHP 8.0+**
✅ **PostgreSQL**
✅ **Composer**
❌ **WSL2 / Docker** (pas nécessaire)
❌ **Swoole** (inutile sur Windows)

---

## 🔧 Installation

### 1️⃣ Installer Workerman via Composer

```bash
cd c:\xampp\htdocs\GED-MEF
composer require workerman/workerman
```

### 2️⃣ Fichiers utiles

| Fichier | Rôle |
|---------|------|
| `app/workerman_server.php` | Serveur Workerman WebSocket + HTTP |
| `portable/windows/run_workerman.bat` | Lanceur Windows |
| `README-WORKERMAN.md` | Documentation Workerman |

---

## ▶️ Lancer le serveur

### Méthode 1 : Double-clic

Double-cliquez sur :

```
C:\xampp\htdocs\GED-MEF\portable\windows\run_workerman.bat
```

### Méthode 2 : PowerShell

```powershell
cd C:\xampp\htdocs\GED-MEF
php app/workerman_server.php
```

---

## 🌐 Accès

- Interface : `http://localhost:8000`
- Monitoring : `http://localhost:8000/app/server_status.php`
- Indexer : `http://localhost:8000/app/indexer.php`

---

## 🔄 Workerman vs Swoole

| Critère | Swoole | Workerman |
|--------|--------|-----------|
| Windows natif | ❌ | ✅ |
| Extension C | Oui | Non |
| Installation | Complexe | Facile |
| WebSocket | Oui | Oui |
| Multi-processus Windows | Non | 1 process |

---

## ⚡ Vitesse du scan PHP vs Go

- **PHP** affiche `Durée : Xs` dans `app/scan.php`.
- **Go** affiche `Durée : Xs` dans `app/scan_go.php` et `duration_ms` via `cmd/scannerfs`.
- Sur un SSD et un CPU multi-coeur, Go est généralement **2x à 5x plus rapide**.

---

## 🔍 Dépannage

### PHP introuvable

```batch
setx PATH "%PATH%;C:\xampp\php"
```

### Workerman absent

```batch
cd c:\xampp\htdocs\GED-MEF
composer require workerman/workerman
```

### Port 8000 occupé

```batch
netstat -ano | findstr :8000
taskkill /PID <PID> /F
```

---

## 🛑 Arrêter le serveur

Appuyez sur **Ctrl+C** dans la fenêtre CMD du serveur.

---

## 🚀 Production

Sur Linux, utilisez le même fichier `app/workerman_server.php`.

```bash
php app/workerman_server.php
```

---

## ✅ Checklist

- [ ] PHP 8.0+ installé
- [ ] Composer installé
- [ ] PostgreSQL prêt
- [ ] Workerman installé
- [ ] `portable/windows/run_workerman.bat` lancé

---

**Bonne utilisation !**
