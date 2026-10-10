# Pierwsza Konfiguracja: Czat z Wieloma Agentami (v1.5.0)

## Krok: Konfiguracja czatu grupowego i trybów orkiestracji

1. **Wymagane uprawnienia**:
   - `chat.group.create` – tworzenie konwersacji z udziałem wielu agentów.
   - `chat.group.manage` – dodawanie/usuwanie uczestników oraz zatrzymywanie dialogu.

2. **Dostępne tryby orkiestracji**:
   - **Mention (`mention`)**: Odpowiada tylko agent oznaczony za pomocą `@nazwa_agenta` lub domyślny agent prowadzący.
   - **Broadcast (`broadcast`)**: Każdy aktywny agent w grupie otrzymuje wiadomość użytkownika i generuje odpowiedź.
   - **Round-robin (`round_robin`)**: Agenci odpowiadają cyklicznie jeden po drugim.
   - **Moderator (`moderator`)**: Dedykowany agent-moderator analizuje kontekst i wskazuje kolejnego mówcę.

3. **Limity bezpieczeństwa**:
   - Zabezpieczenie `max_turns` ogranicza liczbę wymian zdań w rundzie (domyślnie 20).
   - Wbudowany detektor pętli przerywa generowanie, gdy agenci zaczną powtarzać identyczne wypowiedzi.
