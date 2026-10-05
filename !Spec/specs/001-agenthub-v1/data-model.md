# Phase 1: Data Model Specification

**Feature**: `001-agenthub-v1`  
**Date**: 2026-10-04  

---

## 1. Diagram relacji encji (ERD Overview)

```
[Users] ──< [AuditLogs]
   │
   └──< [ChatConversations] ──< [ChatMessages]
             │
[Agents] ────┘
   │
   ├──< [AgentSkills]
   │
   ├──> [LlmAccountPools] ──< [LlmAccountPoolMembers] >── [LlmAccounts] >── [LlmProviders]
   │                                                               │
   ├──> [IntegrationInstances] ──< [ProvisioningJobs]              └──< [LlmCalls]
   │
   └──> [MemoryCollections] ──< [MemoryChunks]
```

---

## 2. Kluczowe tabele i atrybuty

### 2.1 Moduł Użytkowników i Uprawnień (`Users`, `RBAC`)
- **`users`**:
  - `id`: bigint (PK)
  - `name`: varchar(255)
  - `email`: varchar(255) (UNIQUE)
  - `password`: varchar(255) (hash)
  - `theme`: enum('light', 'dark', 'system') (domyślnie 'system')
  - `setup_completed`: boolean (domyślnie false)
  - `remember_token`: varchar(100)
  - `timestamps`

- **Spatie Laravel-Permission**:
  - `roles`, `permissions`, `model_has_roles`, `role_has_permissions`

### 2.2 Moduł AI Settings & LLM Gateway
- **`llm_providers`**:
  - `id`: bigint (PK)
  - `name`: varchar(100) (np. "Google Gemini", "OpenAI", "Anthropic Claude", "Ollama")
  - `slug`: varchar(100) (UNIQUE)
  - `driver`: varchar(50) (np. `gemini`, `openai`, `anthropic`, `ollama`, `openrouter`)
  - `base_url`: varchar(255) (nullable)
  - `is_active`: boolean (domyślnie true)
  - `default_model`: varchar(100) (nullable)
  - `timestamps`

- **`llm_accounts`**:
  - `id`: bigint (PK)
  - `provider_id`: bigint (FK -> `llm_providers.id`)
  - `name`: varchar(100) (np. "Gemini Pro – Konto Firmowe 1")
  - `api_key`: text (**encrypted**)
  - `api_secret`: text (nullable, **encrypted**)
  - `organization_id`: varchar(100) (nullable)
  - `weight`: int (domyślnie 1)
  - `rpm_limit`: int (nullable – request per minute)
  - `tpm_limit`: int (nullable – tokens per minute)
  - `current_status`: enum('active', 'cooldown', 'rate_limited', 'error') (domyślnie 'active')
  - `cooldown_until`: timestamp (nullable)
  - `last_error_message`: text (nullable)
  - `last_used_at`: timestamp (nullable)
  - `timestamps`

- **`llm_account_pools`**:
  - `id`: bigint (PK)
  - `name`: varchar(100)
  - `slug`: varchar(100) (UNIQUE)
  - `strategy`: enum('round_robin', 'weighted', 'least_used', 'priority_fallback')
  - `allowed_models`: json (lista dozwolonych modeli)
  - `is_active`: boolean (domyślnie true)
  - `timestamps`

- **`llm_account_pool_members`**:
  - `id`: bigint (PK)
  - `pool_id`: bigint (FK -> `llm_account_pools.id`)
  - `account_id`: bigint (FK -> `llm_accounts.id`)
  - `priority`: int (domyślnie 1 – mniejsza wartość = wyższy priorytet)
  - `custom_weight`: int (nullable)

- **`llm_calls`** (telemetria i logowanie wywołań):
  - `id`: uuid (PK)
  - `agent_id`: bigint (nullable, FK -> `agents.id`)
  - `account_id`: bigint (nullable, FK -> `llm_accounts.id`)
  - `provider_id`: bigint (FK -> `llm_providers.id`)
  - `model`: varchar(100)
  - `prompt_tokens`: int
  - `completion_tokens`: int
  - `total_tokens`: int
  - `ttft_ms`: int (Time To First Token)
  - `duration_ms`: int
  - `estimated_cost_usd`: decimal(10, 6)
  - `status`: enum('success', 'failed', 'timeout', 'rate_limited')
  - `prompt_preview`: text (nullable – z możliwością wyłączenia w `.env`)
  - `response_preview`: text (nullable)
  - `error_message`: text (nullable)
  - `created_at`: timestamp (indeksowane)

- **`model_pricing`**:
  - `id`: bigint (PK)
  - `model_pattern`: varchar(100) (np. "gpt-4o*", "gemini-1.5-flash*")
  - `input_cost_per_million`: decimal(10, 4)
  - `output_cost_per_million`: decimal(10, 4)

### 2.3 Moduł Agentów AI i Czat
- **`agents`**:
  - `id`: bigint (PK)
  - `name`: varchar(150)
  - `slug`: varchar(150) (UNIQUE)
  - `description`: text (nullable)
  - `runtime_type`: enum('native', 'hermes', 'openclaw', 'claude_code', 'codex')
  - `instance_id`: bigint (nullable, FK -> `integration_instances.id`)
  - `pool_id`: bigint (nullable, FK -> `llm_account_pools.id`)
  - `primary_model`: varchar(100)
  - `system_prompt`: text (nullable)
  - `temperature`: decimal(3, 2) (domyślnie 0.70)
  - `memory_collection_id`: bigint (nullable, FK -> `memory_collections.id`)
  - `is_active`: boolean (domyślnie true)
  - `timestamps`

- **`agent_skills`**:
  - `id`: bigint (PK)
  - `agent_id`: bigint (FK -> `agents.id`)
  - `name`: varchar(100)
  - `driver`: varchar(100) (np. `web_search`, `bash_executor`, `file_editor`)
  - `config`: json
  - `is_enabled`: boolean (domyślnie true)

- **`chat_conversations`**:
  - `id`: uuid (PK)
  - `user_id`: bigint (FK -> `users.id`)
  - `agent_id`: bigint (FK -> `agents.id`)
  - `title`: varchar(255)
  - `is_archived`: boolean (domyślnie false)
  - `timestamps`

- **`chat_messages`**:
  - `id`: uuid (PK)
  - `conversation_id`: uuid (FK -> `chat_conversations.id`)
  - `role`: enum('user', 'assistant', 'system', 'tool')
  - `content`: longtext
  - `tokens_used`: int (nullable)
  - `metadata`: json (nullable – np. powiązane wywołania narzędzi, id z `llm_calls`)
  - `created_at`: timestamp

### 2.4 Moduł Pamięci Wektorowej (`Memory`)
- **`memory_collections`**:
  - `id`: bigint (PK)
  - `name`: varchar(100)
  - `slug`: varchar(100) (UNIQUE)
  - `vector_store`: enum('qdrant', 'pgvector')
  - `embedding_provider_id`: bigint (FK -> `llm_providers.id`)
  - `embedding_model`: varchar(100)
  - `dimensions`: int (np. 1536, 768)
  - `distance_metric`: enum('cosine', 'euclidean', 'dot') (domyślnie 'cosine')
  - `is_default`: boolean (domyślnie false)
  - `timestamps`

- **`memory_chunks`**:
  - `id`: uuid (PK)
  - `collection_id`: bigint (FK -> `memory_collections.id`)
  - `title`: varchar(255)
  - `content`: longtext
  - `vector_point_id`: varchar(100) (identyfikator punktu w Qdrant lub pgvector)
  - `metadata`: json
  - `timestamps`

### 2.5 Moduł Integracji i Provisioningu Instancji
- **`integration_instances`**:
  - `id`: bigint (PK)
  - `type`: enum('hermes', 'openclaw')
  - `name`: varchar(100)
  - `slug`: varchar(100) (UNIQUE)
  - `mode`: enum('systemd', 'docker')
  - `version`: varchar(50)
  - `assigned_port`: int
  - `data_directory`: varchar(255)
  - `auth_token`: text (**encrypted**)
  - `desired_state`: enum('running', 'stopped', 'destroyed')
  - `actual_state`: enum('requested', 'provisioning', 'starting', 'running', 'degraded', 'stopped', 'failed', 'destroying')
  - `health_status`: enum('healthy', 'unhealthy', 'unknown')
  - `resource_limits`: json (cpu, ram, disk)
  - `runtime_config`: json
  - `last_health_check_at`: timestamp (nullable)
  - `timestamps`

- **`provisioning_jobs`**:
  - `id`: uuid (PK)
  - `instance_id`: bigint (FK -> `integration_instances.id`)
  - `action`: enum('provision', 'start', 'stop', 'restart', 'upgrade', 'rollback', 'destroy')
  - `status`: enum('queued', 'running', 'completed', 'failed', 'rolled_back')
  - `progress_percent`: int (0-100)
  - `logs`: longtext (strumieniowane na żywo)
  - `error_message`: text (nullable)
  - `created_at`: timestamp
  - `completed_at`: timestamp (nullable)
