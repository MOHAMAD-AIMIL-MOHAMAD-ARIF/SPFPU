ALTER TABLE entries
 MODIFY type ENUM('Incoming','Outgoing') NULL,
 MODIFY letter_date DATE NULL,
 MODIFY correspondent VARCHAR(150) NULL,
 MODIFY movement_date DATE NULL,
 MODIFY matter VARCHAR(500) NULL;
