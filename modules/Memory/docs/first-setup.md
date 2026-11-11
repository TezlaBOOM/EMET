---
key: memory.hybrid_rag_and_graph
title: Kolekcje Pamięci Wektorowej, Hybrydowy RAG i GraphRAG
order: 300
since_version: 1.7.0
optional: false
link: memory.collections
---

## Konfiguracja Pamięci Wektorowej i GraphRAG (v1.7.0)

1. **Kolekcje Wektorowe (`Pamięć → Kolekcje wektorowe`)**:
   - Utwórz kolekcję wiedzy wybierając silnik **Qdrant** lub **PgVector**, dostawcę embeddingów oraz wymiarowość wektora (np. `1536` lub `768`).
2. **Automatyczna Ekstrakcja Encji do Grafu Wiedzy (GraphRAG)**:
   - Podczas dodawania dokumentu Markdown system automatycznie dzieli tekst, oblicza wektory gęste oraz ekstrahuje encje techniczne, identyfikatory i ich relacje do tabel `memory_entities` i `memory_relations`.
3. **Wyszukiwanie Hybrydowe z Rerankerem (`Pamięć → Wyszukiwarka semantyczna`)**:
   - Tryb **Hybrydowy RAG (Dense + BM25 + GraphRAG + Reranker)** łączy podobieństwo kosinusowe wektorów, pełnotekstowy scoring BM25 (dla dokładnych identyfikatorów i kodów błędów) oraz ekspansję sąsiednich węzłów grafu wiedzy (1-hop).

