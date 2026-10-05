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

## 4. Referencyjny Moduł `modules/Hello/`

W repozytorium znajduje się moduł demonstracyjny `modules/Hello/`, który demonstruje:
- Rejestrację nowej pozycji w Menu 1 i Menu 2.
- Użycie layoutu bazowego `<x-layouts.app>`.
- Brak jakichkolwiek modyfikacji w plikach rdzenia Laravel.
