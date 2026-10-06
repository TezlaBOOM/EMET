# Przewodnik Instalacji i Konfiguracji Projekt-Emet

Platforma Projekt-Emet oferuje dwa w pełni zautomatyzowane warianty instalacji oraz tryb ręczny.

---

## 1. Wariant Docker All-in-one (Szybki start)

Przeznaczony dla środowisk z zainstalowanym silnikiem Docker i docker compose.

```bash
git clone https://github.com/TezlaBOOM/EMET.git agenthub && cd agenthub
./scripts/install.sh
```

### Co wykonuje skrypt `install.sh`:
- Weryfikuje wolne porty: `80` (HTTP), `5432` (PostgreSQL), `6379` (Redis), `6333` (Qdrant), `8080` (Reverb).
- Kopiuje `.env.example` do `.env` i generuje klucze bezpieczeństwa (`APP_KEY`, sekrety serwisowe).
- Uruchamia kontenery: `app`, `nginx`, `postgres`, `redis`, `qdrant`.
- Przeprowadza migracje i seeduje domyślne konto administratora (`admin@admin.lan` / `admin`).

---

## 2. Wariant Natywny Debian 13 (Apache + PHP-FPM)

Przeznaczony dla czystych serwerów dedykowanych lub maszyn wirtualnych z systemem Debian 13 (Trixie).

```bash
git clone https://github.com/TezlaBOOM/EMET.git agenthub && cd agenthub
sudo ./scripts/install-debian13.sh [opcje]
```

### Dostępne opcje instalatora Debian 13:
- `--db=pgsql|mysql` – silnik bazy danych (domyślnie `pgsql`; `mysql` instaluje MariaDB).
- `--vector=qdrant|pgvector` – silnik wektorowy (domyślnie `qdrant`; `pgvector` dostępny tylko z PostgreSQL).
- `--domain=domena.lan` – nazwa serwera wirtualnego hosta Apache (domyślnie `localhost`).
- `--app-dir=/var/www/agenthub` – docelowy katalog instalacji.
- `--with-docker` – doinstalowuje silnik Docker dla integracji agentowych uruchamianych w kontenerach.
- `--force` – pomija weryfikację wersji systemu operacyjnego.

Dane dostępowe i wygenerowane hasła zapisywane są w pliku: `/root/agenthub-install.txt` (chmod 600).

---

## 3. Wariant Proxmox LXC (Debian 12 / Ubuntu 24.04 / 22.04)

Przeznaczony dla świeżo utworzonych kontenerów LXC w środowisku Proxmox VE. Szczegółowy przewodnik znajduje się w [docs/PROXMOX_LXC.md](PROXMOX_LXC.md).

Wewnątrz kontenera LXC:
```bash
sudo ./scripts/install-lxc.sh
```

Lub z poziomu konsoli węzła Proxmox VE (automatyczne utworzenie kontenera i instalacja):
```bash
./scripts/proxmox-host-create.sh
```

---

## 4. Kreator Pierwszego Uruchomienia (Setup Wizard)

Po pierwszym zalogowaniu na konto `admin@admin.lan` platforma automatycznie uruchamia 7-etapowy kreator konfiguracji:
1. **Powitanie & Język:** wybór języka interfejsu (polski/angielski) oraz motywu wizualnego.
2. **Konto Administratora:** opcjonalna aktualizacja hasła i adresu e-mail.
3. **Magazyn Wektorowy:** wybór silnika (Qdrant lub PgVector) i weryfikacja połączenia.
4. **Dostawcy AI:** dodanie pierwszego klucza API do bramy modeli LLM.
5. **Integracje:** detekcja obecności środowisk Hermes Agent, OpenClaw, Claude Code i Codex.
6. **Pierwszy Agent:** utworzenie domyślnego asystenta z opcją włączenia pamięci.
7. **Podsumowanie:** weryfikacja gotowości platformy.

Każdy krok można pominąć przyciskiem *Pomiń ten krok* i skonfigurować go później w odpowiednim module.

---

## 5. Narzędzie Diagnostyczne (Self-Test)

W dowolnym momencie stan całego środowiska można zweryfikować komendą:

```bash
php artisan agenthub:selftest
# lub w formacie maszynowym:
php artisan agenthub:selftest --json
```

Komenda testuje połączenie z bazą, integralność tabel, działanie cache, magazyn wektorowy, bramę dostawców modeli i adaptery integracji.
