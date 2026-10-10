# System Modułów i Rozszerzalność Projekt-Emet

Platforma Projekt-Emet została zaprojektowana zgodnie z zasadą zerowej modyfikacji rdzenia przy dodawaniu nowych funkcjonalności. Każdy obszar biznesowy to odrębny moduł w katalogu `modules/`.

---

## 1. Struktura Katalogu Modułu

Każdy moduł umieszczony w katalogu `modules/<NazwaModułu>/` posiada następującą strukturę:

```
modules/MojaWtyczka/
├── module.json                # Manifest modułu (metadane, menu, uprawnienia)
├── Http/                      # Kontrolery, middleware, form requesty
│   └── Controllers/
│       └── MojKontroler.php
├── resources/
│   └── views/                 # Szablony Blade (namespace: module-<slug>::)
│       └── index.blade.php
└── routes/
    ├── web.php                # Trasy przeglądarkowe
    └── api.php                # Trasy API REST
```

---

## 2. Manifest Modułu (`module.json`)

Plik `module.json` definiuje rejestrację modułu w systemie:

```json
{
  "name": "Moja Wtyczka",
  "slug": "moja-wtyczka",
  "description": "Opis działania modułu rozszerzającego",
  "version": "1.0.0",
  "icon": "cube",
  "order": 10,
  "enabled": true,
  "permission": "moja_wtyczka.view",
  "menu1": {
    "title": "Moja Wtyczka",
    "route": "moja_wtyczka.index",
    "icon": "cube",
    "order": 10
  },
  "menu2": [
    {
      "id": "index",
      "title": "Przegląd",
      "route": "moja_wtyczka.index",
      "permission": "moja_wtyczka.view"
    },
    {
      "id": "settings",
      "title": "Konfiguracja",
      "route": "moja_wtyczka.settings",
      "permission": "moja_wtyczka.manage"
    }
  ]
}
```

### Pola manifestu:
- `slug`: unikalny identyfikator, używany jako prefiks przestrzeni nazw widoków: `view('module-<slug>::<widok>')`.
- `enabled`: flaga logiczna określająca, czy moduł jest aktywny.
- `menu1`: rejestracja modułu w głównym poziomym/pionowym pasku nawigacji.
- `menu2`: tablica podkategorii modułu widoczna w Bloku 3 interfejsu.
- `permission`: klucz uprawnienia Spatie wymagany do wyświetlenia modułu.

---

## 3. Automatyczna Rejestracja (`ModuleManager`)

Usługa `App\Services\ModuleManager` automatycznie:
- Wyszukuje wszystkie katalogi w `modules/`.
- Rejestruje pliki tras `routes/web.php` i `routes/api.php`.
- Rejestruje przestrzeń nazw widoków `module-<slug>`.
- Udostępnia listę aktywnych modułów do nawigacji w layoutach Blade (`<x-layouts.app>`).

---

## 4. Wymagania v1.5.0 (Reguła Dokumentacji i Sekcje Konfiguracji)

Od wersji 1.5.0 każdy moduł musi spełniać reguły spójności architektonicznej:
- **`docs/first-setup.md`**: Każdy moduł musi zawierać fragment instrukcji pierwszej konfiguracji. Spójność jest weryfikowana w CI poleceniem `php artisan docs:check`.
- **`config_section` / `config_sections`**: Moduły przechowujące dane biznesowe rejestrują klasy implementujące `App\Contracts\Config\ConfigSectionInterface` w manifeście `module.json`. Pozwala to na pełny, deklaratywny eksport i import konfiguracji.

---

## 5. Moduły Platformy w wersji 1.5.0

- `modules/Agents` – Zarządzanie agentami AI, zdolnościami internet/kontekst oraz Magazyn Skilli.
- `modules/AiSettings` – Bramka LLM i poświadczenia dostawców.
- `modules/Chat` – Czat pojedynczy i wieloagentowy z orkiestracją dialogową.
- `modules/Dashboard` – Główny pulpit i status platformy.
- `modules/Integrations` – Provisioning instancji oraz adopcja kontenerów Docker.
- `modules/Logs` – Audyt zdarzeń i telemetria.
- `modules/Memory` – Magazyn wektorowy i pamięć semantyczna.
- `modules/Scenarios` – Wizualny edytor blokowy Drawflow i silnik Horizon.
- `modules/System` – Eksport/import konfiguracji, kopie zapasowe i instrukcja wdrożenia.
- `modules/Users` – Użytkownicy i kontrola dostępu RBAC.
- `modules/Hello` – Moduł demonstracyjny / referencyjny.
