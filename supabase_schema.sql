-- Supabase SQL Schema for Pet Shelter Application
-- Run this in your Supabase SQL Editor

-- 1. Create USERS table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user', -- 'user', 'volunteer', 'admin'
    age INT DEFAULT 0,
    gender VARCHAR(20) DEFAULT 'Not Specified',
    created_at TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Create PETS table
CREATE TABLE IF NOT EXISTS pets (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    image TEXT,
    description TEXT,
    health_status VARCHAR(20) DEFAULT 'green', -- 'green' (Healthy), 'yellow' (Needs Help), 'red' (Critical)
    status VARCHAR(20) DEFAULT 'available', -- 'available', 'adopted'
    added_by INT REFERENCES users (id) ON DELETE SET NULL,
    created_at TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Create RESCUES table
CREATE TABLE IF NOT EXISTS rescues (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users (id) ON DELETE CASCADE,
    name VARCHAR(100),
    type VARCHAR(50),
    image TEXT,
    location TEXT NOT NULL,
    description TEXT,
    condition_desc TEXT,
    status VARCHAR(20) DEFAULT 'reported', -- 'reported', 'rescued', 'rejected'
    report_date TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Create ACTIVITY_LOGS table
CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users (id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Create DONATIONS table
CREATE TABLE IF NOT EXISTS donations (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users (id) ON DELETE CASCADE,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    donation_date TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 6. Create EXPENSES table
CREATE TABLE IF NOT EXISTS expenses (
    id SERIAL PRIMARY KEY,
    description TEXT,
    amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    requested_by INT REFERENCES users (id) ON DELETE SET NULL,
    expense_date TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. Create USER_PAYMENT_METHODS table
CREATE TABLE IF NOT EXISTS user_payment_methods (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users (id) ON DELETE CASCADE,
    type VARCHAR(50), -- 'card', 'bkash', etc.
    provider VARCHAR(50),
    display_info VARCHAR(255)
);

-- 8. Create SETTINGS table
CREATE TABLE IF NOT EXISTS settings (
    id SERIAL PRIMARY KEY,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    shelter_location TEXT,
    about_text TEXT,
    founder_name VARCHAR(100)
);

-- 9. Create MESSAGES table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    sender_id INT REFERENCES users (id) ON DELETE CASCADE,
    receiver_id INT REFERENCES users (id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 10. Create ADOPTIONS table
CREATE TABLE IF NOT EXISTS adoptions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users (id) ON DELETE CASCADE,
    pet_id INT REFERENCES pets (id) ON DELETE CASCADE,
    experience TEXT,
    other_pets TEXT,
    financial_status TEXT,
    adoption_status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
    submission_date TIMESTAMP
    WITH
        TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- --- INITIAL DATA ---

-- Insert default admin if not exists (Password: admin123)
-- Note: In production, password should be hashed.
-- PHP handles hashing, so this manual insert is just for schema verification.
-- UPDATE: Admin password is "admin123" hashed using BCRYPT
INSERT INTO
    users (name, email, password, role)
VALUES (
        'System Admin',
        'admin@petshelter.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'admin'
    ) ON CONFLICT (email) DO NOTHING;

-- Insert default settings
INSERT INTO
    settings (
        id,
        contact_email,
        contact_phone,
        shelter_location,
        about_text,
        founder_name
    )
VALUES (
        1,
        'contact@petshelter.com',
        '+880 1234 567 890',
        'Dhaka, Bangladesh',
        'We are dedicated to rescuing and rehoming pets.',
        'John Doe'
    ) ON CONFLICT (id) DO NOTHING;