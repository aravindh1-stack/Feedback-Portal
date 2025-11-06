-- College Feedback System Database Schema
CREATE DATABASE IF NOT EXISTS college_feedback;
USE college_feedback;

-- Admin table
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NULL UNIQUE
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sin_number VARCHAR(20) NOT NULL UNIQUE,
    reg_number VARCHAR(20) NOT NULL UNIQUE,
    department VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    semester INT NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Faculty table
CREATE TABLE IF NOT EXISTS faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Feedback Forms table
CREATE TABLE IF NOT EXISTS feedback_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    semester INT NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    faculty_id INT NOT NULL,
    question TEXT NOT NULL,
    FOREIGN KEY (faculty_id) REFERENCES faculty(id)
);

-- Feedback Responses table
CREATE TABLE IF NOT EXISTS feedback_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    faculty_id INT NOT NULL,
    form_id INT NOT NULL,
    question TEXT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(id),
    FOREIGN KEY (form_id) REFERENCES feedback_forms(id)
);

-- Sample Admin User
INSERT INTO admin (username, password, email) VALUES ('admin1', 'ChangeMe123!', 'admin1@clg.com');

-- Sample Student User
INSERT INTO students (name, sin_number, reg_number, department, year, semester, email, password)
VALUES ('Student One', 'SIN001', 'REG001', 'CSE', 3, 1, 'student1@clg.com', 'student123');

-- Sample Faculty User
INSERT INTO faculty (name, department, email, password)
VALUES ('Faculty One', 'CSE', 'faculty1@clg.com', 'faculty123');