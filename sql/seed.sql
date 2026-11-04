-- RentEase sample data. Admin: admin@rentease.com / Admin@123   Customers: Customer@123
USE rentease_db;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE login_attempts; TRUNCATE TABLE password_resets; TRUNCATE TABLE bookings; TRUNCATE TABLE vehicles; TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (id,name,email,licence_number,password_hash,city,licence_type,phone,dob,role,status) VALUES
(1,'RentEase Admin','admin@rentease.com','ADMIN-0001','$2y$12$T/QH0hPvW/OiXS21tU7NIOij/NNjJYJauUiG9JYVIS6UjKqKWLrRO','Coimbatore','LMV','9000000001','1985-01-01','admin','active'),
(2,'Arun Kumar','arun@example.com','TN3720200012345','$2y$12$qeWn5fxVk6HveLq74ja8SulypjObduXZfyWICr2VKQzc1skg0uHfW','Coimbatore','LMV','9876500001','1995-04-12','customer','active'),
(3,'Priya Sharma','priya@example.com','TN3820190054321','$2y$12$qeWn5fxVk6HveLq74ja8SulypjObduXZfyWICr2VKQzc1skg0uHfW','Chennai','MCWG','9876500002','1998-09-23','customer','active'),
(4,'Rahul Verma','rahul@example.com','KA0520180098765','$2y$12$qeWn5fxVk6HveLq74ja8SulypjObduXZfyWICr2VKQzc1skg0uHfW','Bengaluru','LMV','9876500003','1992-11-05','customer','active');

INSERT INTO vehicles (id,name,description,category,photo,reg_number,fuel_type,transmission,seats,rent_per_day,pickup_location,branch,available_from,available_until,eligibility,min_age,licence_restriction,branch_contact,cancel_policy,status,created_by) VALUES
(1,'Maruti Swift VXi','Compact, fuel-efficient hatchback that is perfect for city driving and weekend getaways.','Hatchback',NULL,'TN66AB1234','Petrol','Manual',5,1800.00,'RS Puram, Coimbatore','Coimbatore Central',DATE_SUB(CURDATE(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 90 DAY),'Valid LMV licence, minimum 1 year old',21,'LMV','+91 90000 11111','flexible','published',1),
(2,'Toyota Innova Crysta','Spacious 7-seat MPV with powerful diesel engine, ideal for family trips and long journeys.','SUV',NULL,'TN66CD5678','Diesel','Manual',7,4200.00,'Gandhipuram, Coimbatore','Coimbatore Central',DATE_SUB(CURDATE(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 60 DAY),'Valid LMV licence, minimum 2 years old',23,'LMV','+91 90000 11111','moderate','published',1),
(3,'Royal Enfield Classic 350','Iconic cruiser motorcycle. Helmet provided. Great for hill-station rides.','Bike',NULL,'TN38EF9012','Petrol','Manual',2,1200.00,'Peelamedu, Coimbatore','Peelamedu Branch',DATE_SUB(CURDATE(),INTERVAL 2 DAY),DATE_ADD(CURDATE(),INTERVAL 45 DAY),'Valid two-wheeler licence (MCWG)',20,'MCWG,MCWOG','+91 90000 22222','strict','published',1),
(4,'Hyundai Creta SX','Premium compact SUV with automatic transmission and sunroof. Being prepared for launch.','SUV',NULL,'TN66GH3456','Petrol','Automatic',5,3500.00,'Saibaba Colony, Coimbatore','Coimbatore Central',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 120 DAY),'Valid LMV licence, minimum 2 years old',23,'LMV','+91 90000 11111','flexible','draft',1),
(5,'Tempo Traveller 12-Seater','12-seat air-conditioned van for group tours. Currently in service workshop.','Van',NULL,'TN66JK7890','Diesel','Manual',12,6500.00,'Singanallur, Coimbatore','Singanallur Branch',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),'Valid LMV licence with 3 years experience',25,'LMV,HMV','+91 90000 33333','none','maintenance',1),
(6,'Honda City ZX','Comfortable premium sedan with automatic transmission. Currently out on rent.','Sedan',NULL,'TN66LM2468','Petrol','Automatic',5,3000.00,'Race Course, Coimbatore','Coimbatore Central',DATE_SUB(CURDATE(),INTERVAL 10 DAY),DATE_ADD(CURDATE(),INTERVAL 40 DAY),'Valid LMV licence',21,'LMV','+91 90000 11111','moderate','rented_out',1);

INSERT INTO bookings (booking_code,vehicle_id,user_id,pickup_dt,return_dt,total_rent,status,return_status) VALUES
('RESEED-000001',1,2,CONCAT(DATE_ADD(CURDATE(),INTERVAL 5 DAY),' 10:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL 8 DAY),' 10:00:00'),5400.00,'confirmed','pending'),
('RESEED-000002',1,4,CONCAT(DATE_ADD(CURDATE(),INTERVAL 6 DAY),' 10:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL 7 DAY),' 10:00:00'),1800.00,'waitlisted','pending'),
('RESEED-000003',2,3,CONCAT(DATE_SUB(CURDATE(),INTERVAL 8 DAY),' 10:00:00'),CONCAT(DATE_SUB(CURDATE(),INTERVAL 5 DAY),' 10:00:00'),12600.00,'confirmed','returned'),
('RESEED-000004',3,2,CONCAT(DATE_ADD(CURDATE(),INTERVAL 12 DAY),' 10:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL 14 DAY),' 10:00:00'),2400.00,'cancelled','pending'),
('RESEED-000005',6,4,CONCAT(DATE_SUB(CURDATE(),INTERVAL 1 DAY),' 10:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL 2 DAY),' 10:00:00'),9000.00,'confirmed','pending'),
('RESEED-000006',3,3,CONCAT(DATE_ADD(CURDATE(),INTERVAL 3 DAY),' 10:00:00'),CONCAT(DATE_ADD(CURDATE(),INTERVAL 5 DAY),' 10:00:00'),2400.00,'confirmed','pending');
