CREATE TABLE IF NOT EXISTS `emails` (
`id` INT AUTO_INCREMENT PRIMARY KEY,
`template` text NOT NULL,
`name` varchar(100) NOT null
) ENGINE=InnoDB;


ALTER TABLE events
ADD COLUMN email_id INT;

ALTER TABLE events
ADD CONSTRAINT fk_events_emails
FOREIGN KEY (email_id)
REFERENCES emails(id)
ON DELETE SET NULL;