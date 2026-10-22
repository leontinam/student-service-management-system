-- Run once on an existing SSMS database to allow providers to propose a new category.
-- Allow a pending listing to store a requested category before an administrator creates it.
ALTER TABLE services
    MODIFY category_id INT NULL,
    ADD COLUMN requested_category VARCHAR(50) NULL AFTER category_id;
