<?php

return [
    'title' => 'Kreator pierwszego uruchomienia (Setup Wizard)',
    'subtitle' => 'Skonfiguruj kluczowe elementy platformy Projekt-Emet w 7 prostych krokach.',
    'skip_step' => 'Pomiń ten krok',
    'next_step' => 'Dalej',
    'prev_step' => 'Wstecz',
    'finish_wizard' => 'Zakończ konfigurację i przejdź do Dashboardu',
    'step_counter' => 'Krok :current z :total',

    'step1_title' => '1. Powitanie i preferencje',
    'step1_desc' => 'Wybierz preferowany język interfejsu oraz domyślny motyw wizualny.',
    'language' => 'Język',
    'theme' => 'Motyw',
    'theme_dark' => 'Ciemny (zalecany)',
    'theme_light' => 'Jasny',

    'step2_title' => '2. Dane konta Administratora',
    'step2_desc' => 'Domyślne dane logowania to admin@admin.lan / admin. Możesz je teraz opcjonalnie zaktualizować (lub pominąć).',
    'admin_email' => 'Adres e-mail administratora',
    'admin_password' => 'Nowe hasło (pozostaw puste, aby nie zmieniać)',
    'admin_password_confirmation' => 'Powtórz nowe hasło',

    'step3_title' => '3. Magazyn Pamięci Wektorowej',
    'step3_desc' => 'Wybierz silnik indeksowania i przeszukiwania semantycznego notatek oraz bazy wiedzy.',
    'vector_engine' => 'Silnik wektorowy',
    'vector_qdrant' => 'Qdrant (Zalecany, dedykowany kontener lub usługa REST)',
    'vector_pgvector' => 'PgVector (Wbudowany w PostgreSQL)',
    'test_connection' => 'Testuj połączenie z bazą wektorową',
    'connection_ok' => 'Połączenie udane!',

    'step4_title' => '4. Dostawcy modeli AI',
    'step4_desc' => 'Dodaj pierwsze konto dostawcy modeli AI (np. OpenAI, Anthropic, Gemini, Ollama).',
    'provider' => 'Dostawca',
    'account_name' => 'Nazwa konta',
    'api_key' => 'Klucz API',
    'base_url' => 'Opcjonalny adres bazowy URL (np. dla Ollama http://localhost:11434)',

    'step5_title' => '5. Detekcja integracji agentowych',
    'step5_desc' => 'Wykrywanie lokalnych środowisk wykonawczych Hermes Agent, OpenClaw, Claude Code i Codex.',
    'detection_results' => 'Stan wykrywania runtime:',
    'install_runtime' => 'Zainstaluj instancję',

    'step6_title' => '6. Twój pierwszy Agent AI',
    'step6_desc' => 'Stwórz asystenta do zadań ogólnych lub wybierz szablon roli.',
    'agent_name' => 'Nazwa agenta',
    'agent_role' => 'Szablon / Rola',
    'enable_memory' => 'Włącz pamięć wektorową dla tego agenta',

    'step7_title' => '7. Podsumowanie i test czatu',
    'step7_desc' => 'Platforma Projekt-Emet jest gotowa do pracy! Możesz wysłać pierwszą wiadomość testową.',
    'ready_msg' => 'Wszystkie moduły zostały pomyślnie zainicjowane.',
    'start_chatting' => 'Otwórz czat',
    'completed_success' => 'Konfiguracja zakończona pomyślnie!',
];
