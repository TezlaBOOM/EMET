# Quickstart Guide: AgentHub Platform v1

**Projekt**: AgentHub  
**Wymagania minimalne**: PHP 8.3+, Composer 2, Node.js 20+, Docker (lub natywny Debian 13)

---

## 1. Szybkie uruchomienie z Docker Compose (Środowisko lokalne)

```bash
# 1. Klonowanie i wejście do katalogu
git clone <repozytorium> agenthub && cd agenthub

# 2. Utworzenie pliku środowiskowego
cp .env.example .env

# 3. Uruchomienie skryptu instalacyjnego
./scripts/install.sh
```

Skrypt automatycznie:
- Weryfikuje wersje narzędzi i wolne porty,
- Generuje `APP_KEY`,
- Uruchamia kontenery: `app`, `db` (PostgreSQL), `redis`, `qdrant`, `reverb`,
- Uruchamia migracje bazodanowe i idempotentne seedery,
- Buduje zasoby frontendu (`npm run build`).

---

## 2. Domyślne dane logowania

Po zakończeniu instalacji przejdź w przeglądarce pod adres: `http://localhost:8000` (lub adres podany w terminalu).

| Pole | Wartość domyślna |
|---|---|
| **E-mail** | `admin@admin.lan` |
| **Hasło** | `admin` |
| **Rola** | `admin` (pełny dostęp) |

> ℹ️ Hasło można w każdej chwili zmienić w *Ustawienia użytkownika → Mój profil*. Aplikacja nie blokuje pracy przy haśle domyślnym.

---

## 3. Instalacja natywna na czystym Debian 13 (Trixie)

Dla serwerów produkcyjnych i VPS bez narzutu Dockera:

```bash
sudo ./scripts/install-debian13.sh --domain=agenthub.lan --db=pgsql --vector=qdrant
```

Szczegóły weryfikacji i poświadczenia zostaną zapisane w `/root/agenthub-install.txt`.

---

## 4. Weryfikacja poprawności instalacji (Self-test)

Wewnątrz kontenera lub na hoście uruchom polecenie diagnostyczne:

```bash
php artisan agenthub:selftest
```

Weryfikuje ono:
- Połączenie z bazą danych i migracje,
- Dostępność magazynu wektorów Qdrant / pgvector,
- Działanie Redisa i serwera WebSocket Reverb,
- Gotowość adapterów integracji (mock drivers).
