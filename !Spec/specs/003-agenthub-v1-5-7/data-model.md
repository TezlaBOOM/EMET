# Data Model Specification: AgentHub v1.5.7

**Feature**: `003-agenthub-v1-5-7`  
**Source Specification**: [`Agenthub_v1.5.7.md`](../../Agenthub_v1.5.7.md)  
**Date**: 2026-10-11  

---

## 1. Zmiany w schemacie bazy danych

### 1.1 Tabela `scenario_triggers` (Rozszerzenie dla timerów i harmonogramów)
Dotychczasowe proste wyzwalacze cron zostają rozszerzone do wielotypowych harmonogramów.

```sql
ALTER TABLE scenario_triggers
    ADD COLUMN name VARCHAR(150) NOT NULL DEFAULT 'Główny harmonogram',
    ADD COLUMN schedule_type VARCHAR(30) NOT NULL DEFAULT 'cron', -- once, interval, daily, weekly, monthly, cron
    ADD COLUMN schedule JSON NULL, -- parametry specyficzne dla typu (np. interval_minutes, time, days_of_week)
    ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'Europe/Warsaw',
    ADD COLUMN starts_at TIMESTAMP NULL,
    ADD COLUMN ends_at TIMESTAMP NULL,
    ADD COLUMN max_runs INT UNSIGNED NULL,
    ADD COLUMN runs_count INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN input JSON NULL, -- wejście statyczne przekazywane do węzła start
    ADD COLUMN overlap_policy VARCHAR(20) NOT NULL DEFAULT 'skip', -- skip, queue, parallel
    ADD COLUMN misfire_policy VARCHAR(20) NOT NULL DEFAULT 'skip', -- skip, run_once
    ADD COLUMN next_run_at TIMESTAMP NULL,
    ADD COLUMN last_run_at TIMESTAMP NULL,
    ADD COLUMN last_run_id VARCHAR(36) NULL,
    ADD COLUMN consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN last_error TEXT NULL,
    ADD COLUMN state VARCHAR(20) NOT NULL DEFAULT 'active'; -- active, paused, finished, error

CREATE INDEX idx_scenario_triggers_dispatch ON scenario_triggers (state, next_run_at);
```

### 1.2 Tabela `scenario_runs` (Śledzenie pochodzenia i idempotentność)
```sql
ALTER TABLE scenario_runs
    ADD COLUMN trigger_id BIGINT UNSIGNED NULL,
    ADD COLUMN scheduled_for TIMESTAMP NULL,
    ADD COLUMN idempotency_key VARCHAR(100) NULL;

CREATE UNIQUE INDEX idx_scenario_runs_idempotency ON scenario_runs (idempotency_key);
CREATE INDEX idx_scenario_runs_trigger ON scenario_runs (trigger_id);
```

### 1.3 Tabela `models` (Nowa tabela centralnego rejestru modeli LLM)
```sql
CREATE TABLE models (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    model_key VARCHAR(150) NOT NULL, -- identyfikator modelu u dostawcy, np. gemini-1.5-flash
    display_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    capabilities JSON NOT NULL, -- ["chat", "vision", "tools", "embeddings"]
    context_window INT UNSIGNED NOT NULL DEFAULT 4096,
    max_output_tokens INT UNSIGNED NULL,
    parameters JSON NULL, -- {"temperature": 0.7, "top_p": 0.95, "stop": []}
    is_manual BOOLEAN NOT NULL DEFAULT FALSE,
    overrides JSON NULL, -- lista pól chronionych przed synchronizacją: ["display_name", "parameters"]
    tags JSON NULL,
    sort INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_models_provider FOREIGN KEY (provider_id) REFERENCES llm_providers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_models_provider_key (provider_id, model_key)
);
```

### 1.4 Tabela `model_pricing` (Rozszerzenie o cennik per konto)
```sql
ALTER TABLE model_pricing
    ADD COLUMN account_id BIGINT UNSIGNED NULL;

ALTER TABLE model_pricing
    ADD CONSTRAINT fk_model_pricing_account FOREIGN KEY (account_id) REFERENCES llm_accounts(id) ON DELETE CASCADE;
```

### 1.5 Tabela `skills` (Soft deletes, nadpisania i blokada modułowa)
```sql
ALTER TABLE skills
    ADD COLUMN deleted_at TIMESTAMP NULL,
    ADD COLUMN deleted_by BIGINT UNSIGNED NULL,
    ADD COLUMN overrides JSON NULL,
    ADD COLUMN locked_by_module BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX idx_skills_deleted_at ON skills (deleted_at);
```

### 1.6 Tabela `skill_runs` (Migawki nazewnictwa i nullable skill_id)
```sql
ALTER TABLE skill_runs
    ADD COLUMN skill_name VARCHAR(150) NULL,
    ADD COLUMN skill_key VARCHAR(100) NULL,
    MODIFY COLUMN skill_id BIGINT UNSIGNED NULL;
```

### 1.7 Tabela `skill_versions` (Usuwanie pojedynczych wersji)
```sql
ALTER TABLE skill_versions
    ADD COLUMN deleted_at TIMESTAMP NULL;
```

### 1.8 Tabela `scenarios` (Blokada współbieżnej edycji)
```sql
ALTER TABLE scenarios
    ADD COLUMN locked_by BIGINT UNSIGNED NULL,
    ADD COLUMN locked_at TIMESTAMP NULL;
```

---

## 2. Relacje i Rzutowania Modeli Eloquent

### 2.1 Model `App\Models\ScenarioTrigger`
- **Relacje**:
  - `scenario()`: `BelongsTo` -> `Scenario`
  - `lastRun()`: `BelongsTo` -> `ScenarioRun`
- **Rzutowania (Casts)**:
  - `schedule`: `'array'`
  - `input`: `'array'`
  - `starts_at`: `'datetime'`
  - `ends_at`: `'datetime'`
  - `next_run_at`: `'datetime'`
  - `last_run_at`: `'datetime'`

### 2.2 Model `App\Models\LlmModel` (Nowy)
- **Tabela**: `models`
- **Relacje**:
  - `provider()`: `BelongsTo` -> `LlmProvider`
  - `pricing()`: `HasMany` -> `ModelPricing`
  - `agents()`: `HasMany` -> `Agent` (przez model_key lub model_id)
- **Rzutowania (Casts)**:
  - `enabled`: `'boolean'`
  - `is_manual`: `'boolean'`
  - `capabilities`: `'array'`
  - `parameters`: `'array'`
  - `overrides`: `'array'`
  - `tags`: `'array'`

### 2.3 Model `App\Models\Skill` (Rozszerzony)
- **Traits**: `SoftDeletes`
- **Relacje**:
  - `versions()`: `HasMany` -> `SkillVersion`
  - `currentVersion()`: `HasOne` -> `SkillVersion`
  - `agents()`: `BelongsToMany` -> `Agent` przez `agent_skill`
  - `runs()`: `HasMany` -> `SkillRun`
- **Rzutowania (Casts)**:
  - `overrides`: `'array'`
  - `locked_by_module`: `'boolean'`
  - `deleted_at`: `'datetime'`

---

## 3. Maszyny stanów i reguły walidacji

### 3.1 Maszyna stanów Timera (`ScenarioTrigger::state`)
- `active` -> `paused` (ręcznie przez użytkownika lub automatycznie po 5 kolejnych błędach `consecutive_failures`)
- `paused` -> `active` (po ręcznym wznowieniu lub edycji)
- `active` -> `finished` (po osiągnięciu `ends_at` lub `runs_count >= max_runs`)
- `active` -> `error` (jeśli definicja wejściowa lub scenariusz jest nieprawidłowy)

### 3.2 Maszyna stanów blokady edycji Scenariusza (`scenarios.locked_by`)
- Gdy użytkownik otwiera edytor `/scenarios/{id}/editor`, pobierana jest blokada `locked_by = Auth::id()`, `locked_at = now()`.
- Blokada wygasa po 15 minutach bezczynności (heartbeat odnawia ją co 60 sekund).
- Inny użytkownik próbujący otworzyć edytor otrzymuje informację w trybie tylko do odczytu z przyciskiem przejęcia blokady (dla roli `admin`).
