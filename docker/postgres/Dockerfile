FROM postgres:18

# pgvector 0.8.4+ is required for PostgreSQL 18 compatibility
# (fixes vacuum_delay_point signature change introduced in PG18)
ARG PGVECTOR_VERSION=0.8.4

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates \
        build-essential \
        postgresql-server-dev-18 \
        git \
    && update-ca-certificates \
    && git clone --branch v${PGVECTOR_VERSION} https://github.com/pgvector/pgvector.git /tmp/pgvector \
    && cd /tmp/pgvector \
    && make \
    && make install \
    && cd / \
    && rm -rf /tmp/pgvector \
    && apt-get purge -y --auto-remove build-essential git postgresql-server-dev-18 \
    && rm -rf /var/lib/apt/lists/*
