FROM postgres:16-alpine

# Install pgvector extension
RUN apk add --no-cache --virtual .build-deps \
    git build-base clang21 llvm21-dev llvm21 postgresql-dev \
    && cd /tmp \
    && git clone --branch v0.8.0 https://github.com/pgvector/pgvector.git \
    && cd pgvector \
    && make \
    && make install \
    && cd / \
    && rm -rf /tmp/pgvector \
    && apk del .build-deps
