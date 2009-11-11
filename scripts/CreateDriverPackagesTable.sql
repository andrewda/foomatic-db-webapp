CREATE TABLE driver_package
(
	driver_id VARCHAR(50) NOT NULL,
	scope ENUM('general', 'gui', 'printer', 'scanner', 'fax') NOT NULL,
	name VARCHAR(255),
	CONSTRAINT pkey PRIMARY KEY(driver_id, scope),
	FOREIGN KEY(driver_id) REFERENCES driver(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;