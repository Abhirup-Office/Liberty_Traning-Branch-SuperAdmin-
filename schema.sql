-- Liberty Training Management System
-- Database schema + dummy data

DROP DATABASE IF EXISTS liberty_tms;
CREATE DATABASE liberty_tms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE liberty_tms;

-- ---------------------------------------------------------------
-- Branches
-- ---------------------------------------------------------------
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    manager_name VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

INSERT INTO branches (name, manager_name, location) VALUES
('Kolkata HQ', 'Sourav Banerjee', 'Kolkata, West Bengal'),
('Bolpur', 'Ritika Sen', 'Bolpur, West Bengal'),
('Bankura', 'Arnab Dutta', 'Bankura, West Bengal');

-- ---------------------------------------------------------------
-- Courses
-- ---------------------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    course_name VARCHAR(120) NOT NULL,
    course_code VARCHAR(20) NULL,
    description TEXT NULL,
    duration VARCHAR(50) NOT NULL,
    total_fee DECIMAL(10,2) NOT NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_courses_code (course_code),
    UNIQUE KEY uq_courses_branch_name (branch_id, course_name),
    KEY idx_courses_branch_status (branch_id, status),
    CONSTRAINT fk_courses_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO courses (course_name, duration, total_fee) VALUES
('Diploma in Computer Applications', '6 Months', 15000.00),
('Web Development Bootcamp', '4 Months', 20000.00),
('Tally with GST', '3 Months', 8000.00),
('Spoken English', '2 Months', 5000.00),
('Graphic Design', '5 Months', 18000.00);

-- ---------------------------------------------------------------
-- Students
-- ---------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_code CHAR(8) NOT NULL,
    branch_id INT NOT NULL,
    course_id INT NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NULL,
    total_fee DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('Paid in Full', 'Partial', 'Overdue') NOT NULL DEFAULT 'Partial',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_students_student_code (student_code)
) ENGINE=InnoDB;

INSERT INTO students (student_code, branch_id, course_id, first_name, last_name, phone, total_fee, amount_paid, balance_due, status, created_at) VALUES
('8WDXR9KA', 1, 2, 'Aditya', 'Roy', '9830012345', 20000.00, 20000.00, 0.00, 'Paid in Full', '2026-08-01 10:00:00'),
('91TG8N5V', 1, 1, 'Priya', 'Chatterjee', '9830012346', 15000.00, 8000.00, 7000.00, 'Partial', '2026-08-05 11:00:00'),
('0AI0LC1X', 1, 3, 'Rahul', 'Ghosh', '9830012347', 8000.00, 2000.00, 6000.00, 'Overdue', '2026-07-15 09:30:00'),
('SUJTD7RC', 1, 5, 'Sneha', 'Mukherjee', '9830012348', 18000.00, 18000.00, 0.00, 'Paid in Full', '2026-08-10 14:00:00'),
('EIBI9PW8', 2, 4, 'Arjun', 'Pal', '9733012345', 5000.00, 2500.00, 2500.00, 'Partial', '2026-08-12 10:15:00'),
('5H7W8439', 2, 2, 'Moumita', 'Das', '9733012346', 20000.00, 5000.00, 15000.00, 'Overdue', '2026-06-20 12:00:00'),
('YBKIEP6J', 2, 1, 'Suman', 'Mondal', '9733012347', 15000.00, 15000.00, 0.00, 'Paid in Full', '2026-08-18 16:00:00'),
('G8UXW2TH', 3, 3, 'Ananya', 'Saha', '9332012345', 8000.00, 4000.00, 4000.00, 'Partial', '2026-08-20 11:30:00'),
('A290T38I', 3, 5, 'Vikram', 'Singha', '9332012346', 18000.00, 6000.00, 12000.00, 'Overdue', '2026-07-01 13:00:00'),
('IZMF6NBU', 3, 2, 'Ishita', 'Bhattacharya', '9332012347', 20000.00, 20000.00, 0.00, 'Paid in Full', '2026-08-25 15:45:00');

-- ---------------------------------------------------------------
-- Transactions
-- ---------------------------------------------------------------
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('UPI', 'Cash', 'Bank') NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_no VARCHAR(30) NOT NULL UNIQUE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO transactions (student_id, amount, payment_mode, transaction_date, receipt_no) VALUES
(1, 20000.00, 'UPI', '2026-08-01 10:00:00', 'RCPT-1001'),
(2, 8000.00, 'Cash', '2026-08-05 11:00:00', 'RCPT-1002'),
(3, 2000.00, 'Cash', '2026-07-15 09:30:00', 'RCPT-1003'),
(4, 18000.00, 'Bank', '2026-08-10 14:00:00', 'RCPT-1004'),
(5, 2500.00, 'UPI', '2026-08-12 10:15:00', 'RCPT-1005'),
(6, 5000.00, 'Cash', '2026-06-20 12:00:00', 'RCPT-1006'),
(7, 15000.00, 'Bank', '2026-08-18 16:00:00', 'RCPT-1007'),
(8, 4000.00, 'UPI', '2026-08-20 11:30:00', 'RCPT-1008'),
(9, 6000.00, 'Cash', '2026-07-01 13:00:00', 'RCPT-1009'),
(10, 20000.00, 'Bank', '2026-08-25 15:45:00', 'RCPT-1010');

-- ---------------------------------------------------------------
-- Super Admins — fully separate credential store from branch admins.
-- No role column: existence in THIS table is what makes someone a super
-- admin, so there is no role flag to tamper with or misread.
-- ---------------------------------------------------------------
CREATE TABLE super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Dummy super admin (password: "SuperPass123!")
INSERT INTO super_admins (name, email, password_hash) VALUES
('Ravi S. Mukherjee', 'super@libertytraining.in', '$2y$10$SFo4IlxYgr2gXEX8TPMi8.o2PWO2zkjD06YgqvWobxg7KzcD373na');

-- ---------------------------------------------------------------
-- Branch Admins — scoped to exactly one branch via branch_id.
-- The backend always reads this from the session, never from the
-- frontend, so a branch admin can never query another branch's data.
-- ---------------------------------------------------------------
CREATE TABLE branch_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- Dummy branch admin for Kolkata HQ (password: "KolkataPass123!")
INSERT INTO branch_admins (branch_id, name, email, password_hash) VALUES
(1, 'Sourav Banerjee', 'kolkata@libertytraining.in', '$2y$10$YpNJszxY5JO.kkdYEQVbdOXm9U8TQeITowjIWa24xGYXpidT2kXG2');
