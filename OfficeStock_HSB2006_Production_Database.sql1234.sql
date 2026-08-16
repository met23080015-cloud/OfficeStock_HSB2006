SET NAMES utf8mb4;
SET time_zone = '+00:00';

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS auth_sessions;
DROP TABLE IF EXISTS inventory_transaction_items;
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS request_items;
DROP TABLE IF EXISTS stationery_requests;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    department_id INT NULL,
    status ENUM('ACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY(role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_department FOREIGN KEY(department_id) REFERENCES departments(id),
    INDEX idx_users_role(role_id),
    INDEX idx_users_department(department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NOT NULL,
    user_agent_hash CHAR(64) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_auth_session_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_auth_session_expiry(expires_at),
    INDEX idx_auth_session_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    email VARCHAR(160) NULL,
    address VARCHAR(255) NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    category_id INT NOT NULL,
    supplier_id INT NULL,
    unit VARCHAR(40) NOT NULL,
    minimum_stock INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_product_minimum CHECK (minimum_stock >= 0),
    CONSTRAINT chk_product_cost CHECK (unit_cost >= 0),
    CONSTRAINT fk_product_category FOREIGN KEY(category_id) REFERENCES categories(id),
    CONSTRAINT fk_product_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
    INDEX idx_product_category(category_id),
    INDEX idx_product_supplier(supplier_id),
    INDEX idx_product_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
    product_id INT PRIMARY KEY,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_inventory_quantity CHECK (quantity >= 0),
    CONSTRAINT fk_inventory_product FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stationery_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(50) NOT NULL UNIQUE,
    requester_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status ENUM('PENDING','APPROVED','REJECTED','ISSUED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note VARCHAR(255) NULL,
    issued_by INT NULL,
    issued_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_request_requester FOREIGN KEY(requester_id) REFERENCES users(id),
    CONSTRAINT fk_request_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id),
    CONSTRAINT fk_request_issuer FOREIGN KEY(issued_by) REFERENCES users(id),
    INDEX idx_request_status(status),
    INDEX idx_request_requester(requester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    CONSTRAINT chk_request_item_quantity CHECK (quantity > 0),
    CONSTRAINT fk_reqitem_request FOREIGN KEY(request_id) REFERENCES stationery_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_reqitem_product FOREIGN KEY(product_id) REFERENCES products(id),
    UNIQUE KEY uq_request_product(request_id,product_id),
    INDEX idx_reqitem_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(50) NOT NULL UNIQUE,
    type ENUM('IN','OUT','REQUEST_ISSUE') NOT NULL,
    supplier_id INT NULL,
    department_id INT NULL,
    request_id INT NULL,
    note VARCHAR(255) NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_tx_department FOREIGN KEY(department_id) REFERENCES departments(id),
    CONSTRAINT fk_tx_request FOREIGN KEY(request_id) REFERENCES stationery_requests(id),
    CONSTRAINT fk_tx_creator FOREIGN KEY(created_by) REFERENCES users(id),
    INDEX idx_tx_type(type),
    INDEX idx_tx_created_at(created_at),
    INDEX idx_tx_request(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT chk_txitem_quantity CHECK (quantity > 0),
    CONSTRAINT chk_txitem_cost CHECK (unit_cost >= 0),
    CONSTRAINT fk_txitem_tx FOREIGN KEY(transaction_id) REFERENCES inventory_transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_txitem_product FOREIGN KEY(product_id) REFERENCES products(id),
    INDEX idx_txitem_product(product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id INT NULL,
    details_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX idx_audit_time(created_at),
    INDEX idx_audit_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles(code,name) VALUES
('ADMIN_MANAGER','Admin / Manager'),
('WAREHOUSE','Warehouse Staff'),
('EMPLOYEE','Employee');

INSERT INTO departments(name,status) VALUES
('Management','ACTIVE'),
('Warehouse','ACTIVE'),
('Human Resources','ACTIVE'),
('Marketing','ACTIVE'),
('Finance','ACTIVE'),
('Information Technology','ACTIVE');

-- Test accounts for lecturer/demo.
-- Passwords are documented in README and are intended ONLY for the assessment database.
INSERT INTO users(full_name,email,password_hash,role_id,department_id,status) VALUES
('OfficeStock Manager','manager@officestock.demo','$2y$12$2NRTleAGr5kYRuKallEMBOYl7zAToL91H7HgygCwJzZU8GOykiWtS',1,1,'ACTIVE'),
('OfficeStock Warehouse','warehouse@officestock.demo','$2y$12$NIBkJhoIpdE4hSWsXjxCnOVdqDzpGryte2TU7WFkXRzhb6isIKlQq',2,2,'ACTIVE'),
('OfficeStock Employee','employee@officestock.demo','$2y$12$A1o80OG/GfjSs/5Q.qIC9OCh9QQbvJBUVPKO2syZrv8.U8Ikkc2Na',3,4,'ACTIVE');

INSERT INTO categories(name,status) VALUES
('Writing Instruments','ACTIVE'),
('Paper & Notebooks','ACTIVE'),
('Filing','ACTIVE'),
('Office Equipment','ACTIVE'),
('Printer Supplies','ACTIVE');

INSERT INTO suppliers(name,phone,email,address,status) VALUES
('Demo Stationery Supplier','0900000001','sales@supplier.demo','Hanoi','ACTIVE'),
('Demo Paper Supplier','0900000002','paper@supplier.demo','Hanoi','ACTIVE'),
('Demo Printer Supply','0900000003','printer@supplier.demo','Hanoi','ACTIVE');

INSERT INTO products
(sku,name,category_id,supplier_id,unit,minimum_stock,unit_cost,status) VALUES
('PEN-001','Blue Ballpoint Pen',1,1,'piece',30,5000,'ACTIVE'),
('PEN-002','2B Pencil',1,1,'piece',20,4000,'ACTIVE'),
('PAP-001','A4 Paper 70gsm',2,2,'ream',15,72000,'ACTIVE'),
('NOT-001','A5 Notebook',2,1,'book',10,28000,'ACTIVE'),
('FIL-001','A4 Ring Binder',3,1,'piece',12,45000,'ACTIVE'),
('INK-001','Laser Printer Toner',5,3,'box',8,350000,'ACTIVE');

INSERT INTO inventory(product_id,quantity) VALUES
(1,120),(2,60),(3,40),(4,18),(5,9),(6,5);

INSERT INTO stationery_requests(request_code,requester_id,reason,status)
VALUES ('REQ-DEMO-001',3,'Office supplies for weekly work','PENDING');

INSERT INTO request_items(request_id,product_id,quantity) VALUES
(1,1,10),(1,4,5);
