# Przewodnik Instalacji Projekt-Emet w Kontenerze Proxmox LXC

Niniejszy przewodnik opisuje wdrożenie platformy **Projekt-Emet (AgentHub)** w kontenerze **LXC** na platformie wirtualizacji **Proxmox VE**.

Dostępne są dwie metody:
1. **Instalacja wewnątrz istniejącego/nowo utworzonego kontenera LXC** (`scripts/install-lxc.sh`).
2. **Pełna automatyzacja z poziomu węzła Proxmox VE** (`scripts/proxmox-host-create.sh`).

---

## Wymagania wstępne dla kontenera LXC

Podczas tworzenia kontenera w Proxmox VE (GUI lub CLI) zaleca się następującą konfigurację:

| Parametr | Rekomendowana wartość | Uwagi |
|---|---|---|
| **Szablon OS** | `debian-12-standard` (Bookworm) lub `ubuntu-24.04-standard` | Skrypt automatycznie konfiguruje repozytoria PHP 8.3/8.4 i Node.js |
| **Cores (vCPU)** | 2 lub więcej | Do kompilacji frontendu oraz pracy kolejek |
| **Pamięć RAM** | 2048 MB (2 GB) – 4096 MB | Rekomendowane 3-4 GB dla bazy Qdrant i kolejek |
| **SWAP** | 1024 MB – 2048 MB | Zabezpieczenie przed skokami zużycia pamięci |
| **Dysk (Rootfs)** | 16 GB – 30 GB | Magazyn dla wektorów, bazy PostgreSQL i logów |
| **Features** | `nesting=1`, `keyctl=1` | **Wymagane** dla systemd, a także jeśli w kontenerze uruchamiany będzie Docker |
| **Unprivileged** | `Yes` (zalecane) lub `No` | Skrypt działa poprawnie w kontenerach unprivileged |

> [!IMPORTANT]
> Opcję **Nesting** (`nesting=1`) włączysz w Proxmox VE pod: **Kontener LXC -> Options -> Features -> Nesting: zaznaczone**.

---

## Metoda 1: Instalacja wewnątrz świeżego kontenera LXC

Ta metoda jest przeznaczona dla sytuacji, w której utworzyłeś już kontener w Proxmoxie i masz do niego dostęp (przez konsolę Proxmox NoVNC/xterm.js lub SSH).

### 1. Zaloguj się do konsoli kontenera jako `root`

W Proxmox GUI: kliknij kontener -> **Console**.  
Lub z powłoki węzła Proxmox:
```bash
pct enter <CTID>
```

### 2. Pobierz projekt lub uruchom instalator

**Opcja A: Jedno polecenie (One-Liner, najszybszy sposób):**
Wklej w konsoli kontenera LXC:
```bash
apt-get update && apt-get install -y curl ca-certificates && curl -fsSL https://raw.githubusercontent.com/TezlaBOOM/EMET/main/scripts/install-lxc.sh | bash
```

**Opcja B: Ręczne sklonowanie z Git:**
```bash
apt-get update && apt-get install -y git curl
git clone https://github.com/TezlaBOOM/EMET.git /var/www/agenthub
cd /var/www/agenthub
chmod +x scripts/install-lxc.sh
./scripts/install-lxc.sh
```

Jeśli przenosisz pliki projektu ręcznie (np. rsync / scp / pct push), wejdź do katalogu projektu i uruchom:
```bash
chmod +x scripts/install-lxc.sh
./scripts/install-lxc.sh
```

### 3. Dostępne parametry instalatora `install-lxc.sh`

```bash
./scripts/install-lxc.sh [opcje]

Dostępne flagi:
  --domain=<domena|IP>     Nazwa domeny lub docelowe IP (domyślnie: automatycznie wykryte IP kontenera)
  --webserver=nginx|apache Serwer WWW (domyślnie: nginx z obsługą WebSocketów Reverb)
  --db=pgsql|mysql         Silnik bazy danych (domyślnie: pgsql)
  --vector=qdrant|pgvector Silnik pamięci wektorowej (domyślnie: qdrant jako systemd)
  --php=8.3|8.4            Wersja PHP (domyślnie: 8.3)
  --app-dir=<ścieżka>      Katalog aplikacji (domyślnie: /var/www/agenthub)
  --with-docker            Doinstalowuje silnik Docker (dla runnerów Hermes/OpenClaw w kontenerach)
  --force                  Pomiń restrykcyjną weryfikację dystrybucji systemu
```

### Co robi skrypt `install-lxc.sh`:
1. Instaluje pakiety bazowe (`curl`, `git`, `cron`, `zip`, `jq`, `net-tools` itp.).
2. Dodaje repozytoria dla **PHP 8.3/8.4** (Ondřej Surý) oraz **Node.js 20 LTS** (NodeSource).
3. Instaluje i konfiguruje **PHP-FPM**, **Composer**, **Redis** oraz **PostgreSQL**.
4. Pobiera i rejestruje bazę wektorową **Qdrant** jako usługę systemd (`qdrant.service`).
5. Konfiguruje plik `.env` z unikalnymi kluczami i hasłami.
6. Przeprowadza instalację zależności (`composer install`, `npm build`), migracje bazy i tworzy konto administratora.
7. Konfiguruje **Nginx** (lub Apache) jako reverse proxy ze wsparciem dla WebSocketów (`/app` -> Laravel Reverb na porcie 8080).
8. Konfiguruje usługi systemd dla background workera (`agenthub-queue`) oraz WebSockets (`agenthub-reverb`).
9. Dodaje zadania cron do harmonogramu systemowego (`/etc/cron.d/agenthub`).
10. Wykonuje diagnostykę `php artisan agenthub:selftest` i zapisuje dane logowania do `/root/agenthub-install.txt`.

---

## Metoda 2: Automatyczne utworzenie kontenera z węzła Proxmox VE

Jeśli masz dostęp do konsoli głównego węzła Proxmox VE (Shell / SSH):

Skrypt `scripts/proxmox-host-create.sh` automatycznie:
- pobierze szablon Debian 12,
- utworzy kontener z włączonym `nesting=1,keyctl=1`,
- uruchomi go,
- prześle kod projektu i uruchomi instalację wewnątrz kontenera.

```bash
# Uruchom na hoście Proxmox VE (PVE Shell):
./scripts/proxmox-host-create.sh
```

Możesz dostosować parametry:
```bash
./scripts/proxmox-host-create.sh \
  --ctid=150 \
  --hostname=agenthub-lxc \
  --cores=2 \
  --memory=2048 \
  --disk=20 \
  --storage=local-lvm \
  --ip=dhcp
```

---

## Zarządzanie i Diagnostyka po instalacji

### Dostęp do aplikacji
- **URL:** `http://<IP_KONTENERA_LXC>`
- **Domyślny login:** `admin@admin.lan`
- **Domyślne hasło:** `admin`
- Po pierwszym zalogowaniu otwiera się 7-etapowy **Setup Wizard**.

### Sprawdzenie stanu usług
```bash
# Diagnostyka podsystemów platformy
php artisan agenthub:selftest

# Status serwisów systemowych w kontenerze
systemctl status agenthub-queue
systemctl status agenthub-reverb
systemctl status qdrant
systemctl status redis-server
systemctl status postgresql
systemctl status nginx
```

### Logi
- Logi aplikacji Laravel: `/var/www/agenthub/storage/logs/laravel.log`
- Logi serwera Nginx: `/var/log/nginx/error.log`
- Logi bazy Qdrant: `journalctl -u qdrant -f`
- Logi kolejki zadań: `journalctl -u agenthub-queue -f`
- Logi serwera Reverb: `journalctl -u agenthub-reverb -f`
