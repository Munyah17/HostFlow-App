-- Performance indexes for hot lookup paths.
ALTER TABLE services
    ADD KEY idx_services_provider_username (provider_username),
    ADD KEY idx_services_sync_status (sync_status);

ALTER TABLE payments
    ADD KEY idx_payments_status_created (status, created_at);
