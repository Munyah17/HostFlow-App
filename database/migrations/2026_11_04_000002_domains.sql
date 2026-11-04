-- HostFlow 1.0.1: domain registration & renewal management

CREATE TABLE IF NOT EXISTS domain_pricing (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tld VARCHAR(30) NOT NULL,
    register_price DECIMAL(10,2) NOT NULL,
    renew_price DECIMAL(10,2) NOT NULL,
    transfer_price DECIMAL(10,2) NULL,
    status ENUM('active','hidden') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_domain_pricing_tld (tld)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    domain VARCHAR(253) NOT NULL,
    tld VARCHAR(30) NOT NULL,
    status ENUM('pending_payment','pending_registration','active','expired','transferred_away','cancelled') NOT NULL DEFAULT 'pending_payment',
    registrar VARCHAR(120) NULL,
    registrar_reference VARCHAR(190) NULL,
    nameservers VARCHAR(500) NULL,
    registered_at DATE NULL,
    expiry_date DATE NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    registration_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renewal_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_domains_domain (domain),
    KEY idx_domains_customer (customer_id),
    KEY idx_domains_status (status),
    KEY idx_domains_expiry (expiry_date),
    CONSTRAINT fk_domains_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_domains_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoice_items
    ADD COLUMN domain_id INT UNSIGNED NULL AFTER service_id,
    ADD KEY idx_items_domain (domain_id),
    ADD CONSTRAINT fk_items_domain FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL;
