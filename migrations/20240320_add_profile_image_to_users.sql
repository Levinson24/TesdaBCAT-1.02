-- Migration: Add profile_image column to users table
-- Target: users table
-- Description: Adds a column to store the filename of the user's profile picture.

USE tesda_db;

ALTER TABLE users 
ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL 
AFTER email;
