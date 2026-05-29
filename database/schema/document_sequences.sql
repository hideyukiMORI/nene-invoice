-- Schema snapshot for the document_sequences table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
CREATE TABLE IF NOT EXISTS document_sequences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    doc_type VARCHAR(32) NOT NULL,
    year INTEGER NOT NULL,
    last_number INTEGER NOT NULL DEFAULT 0
);
CREATE UNIQUE INDEX uniq_document_sequences_scope ON document_sequences (organization_id, doc_type, year);
