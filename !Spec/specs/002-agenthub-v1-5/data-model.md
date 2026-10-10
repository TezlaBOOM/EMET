# Phase 1: Data Model Specification (Aktualizacja 1.5.0)

**Feature**: `002-agenthub-v1-5`  
**Date**: 2026-10-10  
**Source**: [`Agenthub_v1.5.md`](../../Agenthub_v1.5.md)  

---

## 1. Zaktualizowany diagram relacji encji (ERD Overview)

```text
[Users] ───────< [AuditLogs]
   │
   ├───────────< [Conversations] ──< [ConversationParticipants] >── [Agents]
   │                   │                                               │
   │                   └──< [Messages]                                 ├────< [AgentSkills] >── [Skills] ──< [SkillVersions]
   │                                                                   │                           │
   ├───────────< [ConfigTransfers]                                     │                           └──< [SkillRuns]
   │                                                                   │
   ├───────────< [SetupProgress]                                       ├────> [LlmAccountPools]
   │                                                                   │
   ├───────────< [Scenarios] ──< [ScenarioVersions]                    └────> [MemoryCollections]
   │                 │
   │                 ├──< [ScenarioTriggers]
   │                 │
   │                 └──< [ScenarioRuns] ──< [ScenarioRunSteps]
   │                            │                     │
   │                            v                     v
   │               [TelemetryEvents / LlmCalls / AgentRuns]
   │
   └───────────< [IntegrationContainers] >── [AutoConfigProfiles]
                       │
                       └──< [ContainerConfigRuns]
```

---

## 2. Nowe i zmodyfikowane tabele bazy danych

### 2.1 Moduł Skilli (`Skills`)

#### Tabela `skills` (modyfikacja tabeli z v1)
- `id`: bigint (PK)
- `slug`: varchar(100) (UNIQUE) – unikalny identyfikator skilla
- `name`: varchar(150)
- `description`: text (nullable)
- `readme_md`: mediumtext (nullable) – pełny opis / dokumentacja Markdown
- `type`: enum(`tool`, `mcp`, `prompt`, `workflow`, `package`)
- `status`: enum(`draft`, `pending_review`, `active`, `deprecated`)
- `author`: varchar(100) (nullable)
- `tags`: json (nullable) – tablica etykiet
- `source_ref`: varchar(255) (nullable) – URL repozytorium Git, ścieżka ZIP lub identyfikator integracji
- `requires`: json – lista wymaganych zdolności agenta (np. `["internet", "memory.read"]`)
- `current_version_id`: bigint (nullable, FK -> `skill_versions.id`)
- `timestamps`, `soft_deletes`

#### Tabela `skill_versions` (nowa)
- `id`: bigint (PK)
- `skill_id`: bigint (FK -> `skills.id`, onDelete cascade)
- `version`: varchar(30) – semver (np. `1.0.0`)
- `definition`: json – sparsowany manifest `skill.json` (wejście/wyjście, uprawnienia, runtime_compat)
- `package_path`: varchar(255) (nullable) – ścieżka do archiwum w dysku `skills`
- `checksum`: varchar(64) – suma kontrolna SHA-256 paczki
- `changelog`: text (nullable)
- `created_by`: bigint (nullable, FK -> `users.id`)
- `created_at`: timestamp

#### Tabela `agent_skill` (pivot, modyfikacja z v1)
- `id`: bigint (PK)
- `agent_id`: bigint (FK -> `agents.id`, onDelete cascade)
- `skill_id`: bigint (FK -> `skills.id`, onDelete cascade)
- `skill_version_id`: bigint (nullable, FK -> `skill_versions.id`) – `NULL` oznacza śledzenie najnowszej aktywnej wersji
- `enabled`: boolean (domyślnie true)
- `sort`: integer (domyślnie 0)
- `timestamps`

#### Tabela `skill_runs` (nowa)
- `id`: bigint (PK)
- `skill_id`: bigint (FK -> `skills.id`)
- `version_id`: bigint (FK -> `skill_versions.id`)
- `agent_run_id`: bigint (nullable, FK -> `agent_runs.id`)
- `scenario_run_step_id`: bigint (nullable, FK -> `scenario_run_steps.id`)
- `input_summary`: json (nullable)
- `status`: enum(`running`, `success`, `failed`, `timeout`)
- `duration_ms`: integer
- `error`: text (nullable)
- `created_at`: timestamp

---

### 2.2 Zdolności agenta (`Agents`)

#### Tabela `agents` (modyfikacja z v1)
- `internet_mode`: enum(`off`, `allowlist`, `open`) – domyślnie `off` (migracja: z `internet_enabled=true` do `allowlist`)
- `context_mode`: enum(`stateful`, `stateless`) – domyślnie `stateful`
- `context_window_messages`: integer (nullable) – rozmiar okna ostatnich wiadomości dla trybu `stateful`
- `internet_enabled`: boolean (przestarzałe pole, zachowane jako wirtualne/synchronizowane na czas v1.5)

#### Tabela `settings` (rozszerzenie kluczy konfiguracyjnych)
- Dodanie klucza `allow_open_internet`: boolean (domyślnie false)

---

### 2.3 Czat z wieloma agentami (`Chat`)

#### Tabela `conversations` (modyfikacja tabeli z v1)
- `mode`: enum(`single`, `group`) – domyślnie `single`
- `orchestration`: enum(`mention`, `broadcast`, `round_robin`, `moderator`) – domyślnie `mention`
- `lead_agent_id`: bigint (nullable, FK -> `agents.id`) – domyślny agent odpowiadający przy braku `@`
- `moderator_agent_id`: bigint (nullable, FK -> `agents.id`) – agent decydujący o kolejności mówców w trybie `moderator`
- `max_turns`: integer (domyślnie 20) – maksymalna liczba tur w ramach jednej rundy
- `max_rounds`: integer (domyślnie 10)
- `limits`: json (nullable) – budżet tokenów i kosztów per konwersacja
- `settings`: json (nullable)

#### Tabela `conversation_participants` (nowa)
- `id`: bigint (PK)
- `conversation_id`: bigint (FK -> `conversations.id`, onDelete cascade)
- `agent_id`: bigint (FK -> `agents.id`, onDelete cascade)
- `role`: enum(`member`, `lead`, `moderator`) – domyślnie `member`
- `join_context`: enum(`full`, `summary`, `last_n`, `none`) – domyślnie `full`
- `join_context_n`: integer (nullable) – liczba wiadomości dla trybu `last_n`
- `joined_at`: timestamp
- `left_at`: timestamp (nullable)
- `position`: integer (domyślnie 0) – kolejność w trybie `round_robin`

#### Tabela `messages` (modyfikacja tabeli z v1)
- `participant_id`: bigint (nullable, FK -> `conversation_participants.id`)
- `turn_id`: varchar(36) (nullable) – identyfikator tury orkiestracji
- `round`: integer (domyślnie 1)
- `reply_to_message_id`: bigint (nullable, FK -> `messages.id`)
- `kind`: enum(`user`, `agent`, `system`) – domyślnie `user`

---

### 2.4 Scenariusze blokowe (`Scenarios`)

#### Tabela `scenarios` (nowa)
- `id`: bigint (PK)
- `slug`: varchar(100) (UNIQUE)
- `name`: varchar(150)
- `description`: text (nullable)
- `status`: enum(`draft`, `published`, `archived`) – domyślnie `draft`
- `current_version_id`: bigint (nullable, FK -> `scenario_versions.id`)
- `created_by`: bigint (FK -> `users.id`)
- `timestamps`, `soft_deletes`

#### Tabela `scenario_versions` (nowa)
- `id`: bigint (PK)
- `scenario_id`: bigint (FK -> `scenarios.id`, onDelete cascade)
- `version`: integer – numer porządkowy wersji (1, 2, 3...)
- `graph`: json – pełna struktura Drawflow (węzły, porty, krawędzie, zmienne)
- `draft`: boolean (domyślnie false)
- `changelog`: text (nullable)
- `published_by`: bigint (nullable, FK -> `users.id`)
- `published_at`: timestamp (nullable)
- `timestamps`

#### Tabela `scenario_triggers` (nowa)
- `id`: bigint (PK)
- `scenario_id`: bigint (FK -> `scenarios.id`, onDelete cascade)
- `type`: enum(`manual`, `cron`, `webhook`)
- `config`: json (nullable) – np. wyrażenie crona lub parametry wejściowe
- `token_hash`: varchar(64) (nullable) – SHA-256 tokenu autoryzacji dla webhooka
- `enabled`: boolean (domyślnie true)
- `last_fired_at`: timestamp (nullable)
- `timestamps`

#### Tabela `scenario_runs` (nowa)
- `id`: bigint (PK)
- `scenario_id`: bigint (FK -> `scenarios.id`)
- `version_id`: bigint (FK -> `scenario_versions.id`)
- `status`: enum(`pending`, `running`, `paused`, `success`, `failed`, `cancelled`)
- `trigger_type`: enum(`manual`, `cron`, `webhook`)
- `input`: json (nullable)
- `output`: json (nullable)
- `vars`: json (nullable) – zmienne runu przekazywane między węzłami
- `tokens_in`: integer (domyślnie 0)
- `tokens_out`: integer (domyślnie 0)
- `cost`: decimal(10, 6) (domyślnie 0.000000)
- `started_at`: timestamp (nullable)
- `finished_at`: timestamp (nullable)
- `error`: text (nullable)
- `created_by`: bigint (nullable, FK -> `users.id`)
- `timestamps`

#### Tabela `scenario_run_steps` (nowa)
- `id`: bigint (PK)
- `run_id`: bigint (FK -> `scenario_runs.id`, onDelete cascade)
- `node_id`: varchar(50) – identyfikator węzła w grafie JSON (np. `n2`)
- `node_type`: varchar(30) – np. `agent`, `skill`, `condition`, `human`
- `status`: enum(`pending`, `running`, `waiting`, `success`, `failed`, `skipped`, `retrying`)
- `attempt`: integer (domyślnie 1)
- `input`: json (nullable)
- `output`: json (nullable)
- `agent_run_id`: bigint (nullable, FK -> `agent_runs.id`)
- `skill_run_id`: bigint (nullable, FK -> `skill_runs.id`)
- `tokens_in`: integer (domyślnie 0)
- `tokens_out`: integer (domyślnie 0)
- `cost`: decimal(10, 6) (domyślnie 0.000000)
- `started_at`: timestamp (nullable)
- `finished_at`: timestamp (nullable)
- `error`: text (nullable)

#### Powiązania w tabelach telemetrycznych (`telemetry_events`, `llm_calls`, `agent_runs`)
- `scenario_run_id`: bigint (nullable, indeksowane)
- `node_id`: varchar(50) (nullable, indeksowane)

---

### 2.5 Kontenery w Integracjach (`Containers`)

#### Tabela `integration_containers` (nowa)
- `id`: bigint (PK)
- `integration_id`: bigint (nullable, FK -> `integrations.id`)
- `instance_id`: bigint (nullable, FK -> `integration_instances.id`)
- `container_id`: varchar(64) – ID kontenera w Dockerze
- `name`: varchar(150)
- `image`: varchar(255)
- `image_digest`: varchar(100) (nullable)
- `labels`: json (nullable)
- `ports`: json (nullable)
- `networks`: json (nullable)
- `detected_type`: enum(`hermes`, `openclaw`, `ollama`, `other`)
- `mode`: enum(`observe`, `configure`, `managed`)
- `status`: enum(`running`, `stopped`, `restarting`, `error`)
- `autoconfig_profile_id`: bigint (nullable, FK -> `autoconfig_profiles.id`)
- `auto_configure`: boolean (domyślnie false)
- `config_snapshot`: mediumtext (nullable, encrypted json) – kopia zapasowa konfiguracji przed zmianą
- `last_inspected_at`: timestamp (nullable)
- `last_error`: text (nullable)
- `adopted_by`: bigint (nullable, FK -> `users.id`)
- `timestamps`

#### Tabela `autoconfig_profiles` (nowa)
- `id`: bigint (PK)
- `name`: varchar(100)
- `target_type`: enum(`hermes`, `openclaw`, `ollama`, `general`)
- `steps`: json – sekwencja deklaratywnych kroków konfiguracji
- `is_default`: boolean (domyślnie false)
- `created_by`: bigint (nullable, FK -> `users.id`)
- `timestamps`

#### Tabela `container_config_runs` (nowa)
- `id`: bigint (PK)
- `container_id`: bigint (FK -> `integration_containers.id`, onDelete cascade)
- `profile_id`: bigint (nullable, FK -> `autoconfig_profiles.id`)
- `kind`: enum(`manual`, `auto`)
- `diff`: json (nullable) – różnice w konfiguracji
- `status`: enum(`running`, `success`, `rolled_back`, `failed`)
- `log`: mediumtext (nullable)
- `started_at`: timestamp
- `finished_at`: timestamp (nullable)

---

### 2.6 Eksport/Import i Konfiguracja Systemu (`System`)

#### Tabela `config_transfers` (nowa)
- `id`: bigint (PK)
- `type`: enum(`export`, `import`)
- `user_id`: bigint (FK -> `users.id`)
- `sections`: json – lista sekcji objętych transferem
- `mode`: enum(`merge`, `overwrite`, `new_only`) (dla importu)
- `status`: enum(`pending`, `processing`, `completed`, `failed`)
- `summary`: json (nullable) – raport diffu lub podsumowanie zaimportowanych obiektów
- `file_path`: varchar(255)
- `checksum`: varchar(64) – SHA-256
- `created_at`: timestamp
- `finished_at`: timestamp (nullable)

#### Tabela `setup_progress` (nowa)
- `id`: bigint (PK)
- `user_id`: bigint (nullable, FK -> `users.id`) – `NULL` oznacza postęp globalny instancji
- `step_key`: varchar(100) (UNIQUE composite z `user_id`) – np. `ai.accounts`, `agents.skills`
- `status`: enum(`todo`, `done`, `skipped`)
- `completed_at`: timestamp (nullable)
- `note`: text (nullable)
- `timestamps`
