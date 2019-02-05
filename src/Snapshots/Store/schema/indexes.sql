CREATE UNIQUE INDEX IF NOT EXISTS event_store_snapshots_aggregate ON event_store_snapshots (id, aggregate_id_class);
CREATE UNIQUE INDEX IF NOT EXISTS event_store_snapshots_identifier ON event_store_snapshots (id, aggregate_id_class);
