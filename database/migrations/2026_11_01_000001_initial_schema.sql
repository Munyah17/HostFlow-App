-- HostFlow 1.0 initial schema

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(120) NOT NULL PRIMARY KEY,
    `value` MEDIUMTEXT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    business_name VARCHAR(150) NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NOT NULL DEFAULT 'Zimbabwe',
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_status (status),
    KEY idx_customers_name (full_name),
    KEY idx_customers_business (business_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('super_admin','admin','client') NOT NULL,
    customer_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_customer (customer_id),
    KEY idx_users_role (role),
    CONSTRAINT fk_users_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_password_resets_token (token_hash),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_login_attempts_email (email, attempted_at),
    KEY idx_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_customer_notes_customer (customer_id),
    CONSTRAINT fk_customer_notes_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosting_providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'cpanel_whm',
    hostname VARCHAR(190) NOT NULL,
    port INT UNSIGNED NOT NULL DEFAULT 2087,
    username VARCHAR(100) NOT NULL,
    api_token TEXT NULL,
    verify_ssl TINYINT(1) NOT NULL DEFAULT 1,
    nameservers VARCHAR(255) NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    last_checked_at DATETIME NULL,
    last_check_ok TINYINT(1) NULL,
    last_check_message VARCHAR(500) NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hosting_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('web','email') NOT NULL DEFAULT 'web',
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    price_monthly DECIMAL(10,2) NULL,
    price_quarterly DECIMAL(10,2) NULL,
    price_semiannual DECIMAL(10,2) NULL,
    price_annual DECIMAL(10,2) NULL,
    setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    default_cycle ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
    storage_gb DECIMAL(10,2) NULL,
    bandwidth_gb DECIMAL(10,2) NULL,
    domains_allowed INT NULL,
    email_accounts INT NULL,
    databases_allowed INT NULL,
    features TEXT NULL,
    provider_id INT UNSIGNED NULL,
    provider_package VARCHAR(150) NULL,
    status ENUM('active','hidden','retired') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_plans_type_status (type, status),
    CONSTRAINT fk_plans_provider FOREIGN KEY (provider_id) REFERENCES hosting_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    type ENUM('web','email') NOT NULL DEFAULT 'web',
    domain VARCHAR(253) NOT NULL,
    provider_id INT UNSIGNED NULL,
    provider_username VARCHAR(64) NULL,
    billing_cycle ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
    price DECIMAL(10,2) NOT NULL,
    status ENUM('pending_payment','provisioning','active','suspended','provisioning_failed','cancellation_pending','terminated','cancelled') NOT NULL DEFAULT 'pending_payment',
    activation_date DATE NULL,
    next_due_date DATE NULL,
    suspension_reason VARCHAR(255) NULL,
    suspension_type ENUM('billing','manual') NULL,
    suspended_at DATETIME NULL,
    terminated_at DATETIME NULL,
    termination_flagged_at DATETIME NULL,
    domain_expiry_date DATE NULL,
    domain_registrar VARCHAR(120) NULL,
    provision_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1000) NULL,
    provider_status VARCHAR(30) NULL,
    provider_package VARCHAR(150) NULL,
    disk_used_mb DECIMAL(12,2) NULL,
    disk_limit_mb DECIMAL(12,2) NULL,
    sync_status ENUM('unknown','in_sync','mismatch','missing','error') NOT NULL DEFAULT 'unknown',
    sync_message VARCHAR(1000) NULL,
    last_synced_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_services_provider_user (provider_id, provider_username),
    KEY idx_services_customer (customer_id),
    KEY idx_services_status (status),
    KEY idx_services_due (next_due_date),
    KEY idx_services_type (type),
    KEY idx_services_domain (domain),
    CONSTRAINT fk_services_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_services_plan FOREIGN KEY (plan_id) REFERENCES hosting_plans(id) ON DELETE RESTRICT,
    CONSTRAINT fk_services_provider FOREIGN KEY (provider_id) REFERENCES hosting_providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    status ENUM('draft','pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    paid_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    payment_reference VARCHAR(190) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_invoices_number (number),
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_status_due (status, due_date),
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    item_type ENUM('new_service','renewal','upgrade','domain','setup','custom') NOT NULL DEFAULT 'custom',
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    meta JSON NULL,
    applied_at DATETIME NULL,
    KEY idx_items_invoice (invoice_id),
    KEY idx_items_service_period (service_id, item_type, period_start),
    CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(60) NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    gateway VARCHAR(40) NOT NULL,
    method VARCHAR(60) NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending','completed','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    gateway_reference VARCHAR(190) NULL,
    poll_url VARCHAR(500) NULL,
    gateway_status VARCHAR(60) NULL,
    paid_at DATETIME NULL,
    recorded_by INT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    raw_response TEXT NULL,
    last_checked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_payments_reference (reference),
    UNIQUE KEY uq_payments_gateway_ref (gateway, gateway_reference),
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_customer (customer_id),
    KEY idx_payments_status (status, paid_at),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(20) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    category VARCHAR(60) NOT NULL DEFAULT 'General',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to INT UNSIGNED NULL,
    last_reply_at DATETIME NULL,
    last_reply_by ENUM('client','staff') NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_tickets_number (number),
    KEY idx_tickets_customer (customer_id),
    KEY idx_tickets_status (status),
    CONSTRAINT fk_tickets_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_messages_ticket (ticket_id),
    CONSTRAINT fk_messages_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'email',
    recipient VARCHAR(190) NOT NULL,
    template VARCHAR(60) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(1000) NULL,
    dedupe_key VARCHAR(190) NULL,
    send_after DATETIME NOT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_notifications_dedupe (dedupe_key),
    KEY idx_notifications_status (status, send_after),
    KEY idx_notifications_customer (customer_id),
    CONSTRAINT fk_notifications_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status ENUM('running','completed','completed_with_errors','failed') NOT NULL DEFAULT 'running',
    summary TEXT NULL,
    KEY idx_cron_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id INT UNSIGNED NULL,
    task VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    status ENUM('success','failed','skipped','info') NOT NULL,
    message VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_automation_logs_run (run_id),
    KEY idx_automation_logs_status (status, created_at),
    KEY idx_automation_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_audit_created (created_at),
    KEY idx_audit_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS update_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_version VARCHAR(20) NULL,
    to_version VARCHAR(20) NOT NULL,
    migrations TEXT NULL,
    backup_file VARCHAR(255) NULL,
    status ENUM('success','failed') NOT NULL,
    message TEXT NULL,
    triggered_by VARCHAR(60) NOT NULL DEFAULT 'cli',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
