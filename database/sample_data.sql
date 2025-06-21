-- =====================================================
-- msbCORE SAMPLE DATA AND ADDITIONAL TABLES
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', 'admin@msbcore.com', '$2y$10$nEEEnzEtHpLNuGnPYXeet.LLYL99z3iyxN9jD3XmuqdTs4NB4Uco2', 'System Administrator', 'admin', 1),
('manager', 'manager@msbcore.com', '$2y$10$nEEEnzEtHpLNuGnPYXeet.LLYL99z3iyxN9jD3XmuqdTs4NB4Uco2', 'Workshop Manager', 'manager', 1),
('tech1', 'tech1@msbcore.com', '$2y$10$nEEEnzEtHpLNuGnPYXeet.LLYL99z3iyxN9jD3XmuqdTs4NB4Uco2', 'Senior Technician', 'technician', 1);

-- Insert sample customers
INSERT INTO `customers` (`customer_code`, `name`, `email`, `phone`, `address`, `customer_type`, `is_active`) VALUES
('CUST001', 'John Doe', 'john.doe@email.com', '+62812345678', 'Jl. Sudirman No. 123, Jakarta', 'individual', 1),
('CUST002', 'PT. Maju Jaya', 'info@majujaya.com', '+62213456789', 'Jl. Gatot Subroto No. 456, Jakarta', 'company', 1),
('CUST003', 'Jane Smith', 'jane.smith@email.com', '+62856789012', 'Jl. Thamrin No. 789, Jakarta', 'individual', 1);

-- Insert sample vehicles
INSERT INTO `vehicles` (`customer_id`, `license_plate`, `brand`, `model`, `year`, `color`, `fuel_type`, `transmission`, `mileage`, `status`) VALUES
(1, 'B1234ABC', 'Toyota', 'Avanza', 2020, 'Silver', 'gasoline', 'manual', 25000, 'active'),
(2, 'B5678DEF', 'Honda', 'Civic', 2019, 'White', 'gasoline', 'automatic', 35000, 'active'),
(3, 'B9012GHI', 'Suzuki', 'Ertiga', 2021, 'Black', 'gasoline', 'manual', 15000, 'active');

-- Insert sample services
INSERT INTO `services` (`service_code`, `name`, `description`, `category`, `base_price`, `estimated_duration`, `is_active`) VALUES
('SRV001', 'Oil Change', 'Engine oil and filter replacement', 'Maintenance', 150000.00, 30, 1),
('SRV002', 'Brake Service', 'Brake pad and fluid inspection/replacement', 'Brake System', 300000.00, 60, 1),
('SRV003', 'Tire Rotation', 'Tire rotation and balancing', 'Tire Service', 100000.00, 45, 1),
('SRV004', 'Engine Tune-up', 'Complete engine inspection and tuning', 'Engine', 500000.00, 120, 1),
('SRV005', 'AC Service', 'Air conditioning system service', 'AC System', 250000.00, 90, 1);

-- Insert sample work orders
INSERT INTO `work_orders` (`work_order_number`, `customer_id`, `vehicle_id`, `technician_id`, `status`, `priority`, `complaint`, `estimated_amount`, `created_at`) VALUES
('WO2025001', 1, 1, 3, 'completed', 'normal', 'Engine oil change and general inspection', 150000.00, '2025-06-15 08:00:00'),
('WO2025002', 2, 2, 3, 'in_progress', 'high', 'Brake noise and vibration', 300000.00, '2025-06-18 10:30:00'),
('WO2025003', 3, 3, 3, 'pending', 'normal', 'AC not cooling properly', 250000.00, '2025-06-19 09:15:00');

COMMIT;
