<?php

return [
    'title' => 'Setup Wizard',
    'subtitle' => 'Configure your Projekt-Emet platform in 7 simple steps.',
    'skip_step' => 'Skip this step',
    'next_step' => 'Next',
    'prev_step' => 'Back',
    'finish_wizard' => 'Finish Setup & Go to Dashboard',
    'step_counter' => 'Step :current of :total',

    'step1_title' => '1. Welcome & Preferences',
    'step1_desc' => 'Choose your preferred language and theme.',
    'language' => 'Language',
    'theme' => 'Theme',
    'theme_dark' => 'Dark (recommended)',
    'theme_light' => 'Light',

    'step2_title' => '2. Administrator Credentials',
    'step2_desc' => 'Default credentials are admin@admin.lan / admin. You may optionally update them now or skip.',
    'admin_email' => 'Admin Email',
    'admin_password' => 'New Password (leave empty to keep current)',
    'admin_password_confirmation' => 'Confirm New Password',

    'step3_title' => '3. Vector Memory Store',
    'step3_desc' => 'Choose indexing and semantic search engine for knowledge base.',
    'vector_engine' => 'Vector Engine',
    'vector_qdrant' => 'Qdrant (Recommended, dedicated REST/gRPC container)',
    'vector_pgvector' => 'PgVector (PostgreSQL extension)',
    'test_connection' => 'Test Vector Store Connection',
    'connection_ok' => 'Connection successful!',

    'step4_title' => '4. AI Model Providers',
    'step4_desc' => 'Add your first AI provider account (OpenAI, Anthropic, Gemini, Ollama, etc.).',
    'provider' => 'Provider',
    'account_name' => 'Account Name',
    'api_key' => 'API Key',
    'base_url' => 'Optional Base URL (e.g. for Ollama http://localhost:11434)',

    'step5_title' => '5. Agent Runtime Integrations',
    'step5_desc' => 'Detect local Hermes Agent, OpenClaw, Claude Code and Codex environments.',
    'detection_results' => 'Runtime Detection Status:',
    'install_runtime' => 'Provision Instance',

    'step6_title' => '6. Your First AI Agent',
    'step6_desc' => 'Create a general assistant or select a predefined role template.',
    'agent_name' => 'Agent Name',
    'agent_role' => 'Template / Role',
    'enable_memory' => 'Enable vector memory for this agent',

    'step7_title' => '7. Summary & Chat Test',
    'step7_desc' => 'Projekt-Emet platform is ready! You can test your first chat message.',
    'ready_msg' => 'All core modules have been initialized successfully.',
    'start_chatting' => 'Start Chatting',
    'completed_success' => 'Setup completed successfully!',
];
