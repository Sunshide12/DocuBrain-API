-- DocuBrain — PostgreSQL initialization
-- This script runs automatically when the container is first created.

-- Enable pgvector extension for 1536-dim embeddings (text-embedding-3-small)
CREATE EXTENSION IF NOT EXISTS vector;

-- Confirm installation
SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';
