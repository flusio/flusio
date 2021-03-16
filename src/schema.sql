CREATE TABLE jobs (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL DEFAULT '',
    created_at TIMESTAMPTZ NOT NULL,
    handler JSON NOT NULL,
    perform_at TIMESTAMPTZ NOT NULL,
    frequency TEXT NOT NULL DEFAULT '',
    locked_at TIMESTAMPTZ,
    number_attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT,
    failed_at TIMESTAMPTZ
);

CREATE TABLE tokens (
    token TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    expired_at TIMESTAMPTZ NOT NULL,
    invalidated_at TIMESTAMPTZ
);

CREATE TABLE users (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    email TEXT UNIQUE NOT NULL,
    username TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    locale TEXT NOT NULL DEFAULT 'en_GB',
    avatar_filename TEXT,
    csrf TEXT NOT NULL DEFAULT '',
    news_preferences JSON NOT NULL DEFAULT '{}',
    validated_at TIMESTAMPTZ,
    validation_token TEXT REFERENCES tokens ON DELETE SET NULL ON UPDATE CASCADE,
    subscription_account_id TEXT,
    subscription_expired_at TIMESTAMPTZ
        NOT NULL
        DEFAULT date_trunc('second', NOW() + INTERVAL '1 month'),

    pocket_request_token TEXT,
    pocket_access_token TEXT,
    pocket_username TEXT,
    pocket_error INTEGER
);

CREATE INDEX idx_users_email ON users(email);

CREATE TABLE sessions (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    confirmed_password_at TIMESTAMPTZ,
    name TEXT NOT NULL,
    ip TEXT NOT NULL,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE,
    token TEXT UNIQUE REFERENCES tokens ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_sessions_token ON sessions(token);

CREATE TABLE importations (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    type TEXT NOT NULL,
    status TEXT NOT NULL,
    options JSON NOT NULL,
    error TEXT NOT NULL DEFAULT '',
    user_id TEXT NOT NULL REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE collections (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    name TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL,
    is_public BOOLEAN NOT NULL DEFAULT false,
    user_id TEXT NOT NULL REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE,

    feed_url TEXT,
    feed_site_url TEXT,
    feed_fetched_code INTEGER NOT NULL DEFAULT 0,
    feed_fetched_at TIMESTAMPTZ,
    feed_fetched_error TEXT
);

CREATE INDEX idx_collections_user_id ON collections(user_id);

CREATE TABLE links (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    is_hidden BOOLEAN NOT NULL DEFAULT false,
    reading_time INTEGER NOT NULL DEFAULT 0,
    image_filename TEXT,
    fetched_at TIMESTAMPTZ,
    fetched_code INTEGER NOT NULL DEFAULT 0,
    fetched_error TEXT,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE,

    feed_entry_id TEXT
);

CREATE UNIQUE INDEX idx_links_user_id_url ON links(user_id, url);

CREATE TABLE links_to_collections (
    id SERIAL PRIMARY KEY,
    link_id TEXT REFERENCES links ON DELETE CASCADE ON UPDATE CASCADE,
    collection_id TEXT REFERENCES collections ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX idx_links_to_collections ON links_to_collections(link_id, collection_id);
CREATE INDEX idx_links_to_collections_collection_id ON links_to_collections(collection_id);

CREATE TABLE followed_collections (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE,
    collection_id TEXT REFERENCES collections ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX idx_followed_collections ON followed_collections(user_id, collection_id);

CREATE TABLE news_links (
    id SERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    reading_time INTEGER NOT NULL DEFAULT 0,
    image_filename TEXT,
    via_type TEXT NOT NULL DEFAULT '',
    via_link_id TEXT REFERENCES links ON DELETE SET NULL ON UPDATE CASCADE,
    via_collection_id TEXT REFERENCES collections ON DELETE SET NULL ON UPDATE CASCADE,
    is_read BOOLEAN NOT NULL DEFAULT false,
    is_removed BOOLEAN NOT NULL DEFAULT false,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE messages (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    content TEXT NOT NULL,
    link_id TEXT REFERENCES links ON DELETE CASCADE ON UPDATE CASCADE,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_messages_link_id ON messages(link_id);

CREATE TABLE topics (
    id TEXT PRIMARY KEY,
    created_at TIMESTAMPTZ NOT NULL,
    label TEXT NOT NULL
);

CREATE TABLE collections_to_topics (
    id SERIAL PRIMARY KEY,
    collection_id TEXT REFERENCES collections ON DELETE CASCADE ON UPDATE CASCADE,
    topic_id TEXT REFERENCES topics ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX idx_collections_to_topics ON collections_to_topics(collection_id, topic_id);
CREATE INDEX idx_collections_to_topics_topic_id ON collections_to_topics(topic_id);

CREATE TABLE users_to_topics (
    id SERIAL PRIMARY KEY,
    user_id TEXT REFERENCES users ON DELETE CASCADE ON UPDATE CASCADE,
    topic_id TEXT REFERENCES topics ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE UNIQUE INDEX idx_users_to_topics ON users_to_topics(user_id, topic_id);
CREATE INDEX idx_users_to_topics_topic_id ON users_to_topics(topic_id);
