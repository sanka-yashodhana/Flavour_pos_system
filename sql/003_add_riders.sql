-- Add rider role support
-- Run this to create test rider accounts

-- Insert sample riders
INSERT INTO users (username, full_name, email, password, phone, role) VALUES
('rider1', 'John Rider', 'rider1@example.com', '$2y$10$YourHashedPasswordHere', '0771234567', 'rider'),
('rider2', 'Jane Delivery', 'rider2@example.com', '$2y$10$YourHashedPasswordHere', '0777654321', 'rider')
ON CONFLICT (username) DO NOTHING;

-- Note: Replace '$2y$10$YourHashedPasswordHere' with actual hashed passwords
-- You can generate them using: password_hash('your_password', PASSWORD_DEFAULT) in PHP
-- Or use the same password as default users if testing

-- Example with default password 'password123':
-- Password hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE role = 'rider' AND username IN ('rider1', 'rider2');
