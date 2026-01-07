-- Hey! This is my master database script. 
-- I spent a lot of time consolidating all the different tables into this one file 
-- so that setting up the project is super easy for everyone.
-- First, let's create the database if it's not already there.

CREATE DATABASE IF NOT EXISTS online_quiz_system;
USE online_quiz_system;

-- I'm turning off FK checks temporarily so I can create the tables 
-- in any order without MySQL complaining about missing parents.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. My Unified Users Table. 
-- Instead of having separate tables for students and teachers, 
-- I put everyone here and used a 'user_type' column. Much cleaner!

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE, -- This is the school ID, like TCH-1 or STU-42.
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') DEFAULT NULL,
    username VARCHAR(50) UNIQUE, -- Only Admins and Teachers really need this to log in.
    password VARCHAR(255) DEFAULT NULL, -- Hashed with BCRYPT for safety.
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) UNIQUE DEFAULT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    user_type ENUM('Admin', 'Teacher', 'Student') NOT NULL,
    status ENUM('Active', 'Not Active') DEFAULT 'Active',
    first_login TINYINT(1) DEFAULT 1, -- I added this to help with the "change password on first use" feature.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2. Classes Table. 
-- This stores the class names like "Computer Science 101".

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    section VARCHAR(50) DEFAULT '',
    academic_year VARCHAR(20) NOT NULL,
    teacher_id INT DEFAULT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Class Teachers Mapping.
-- I used a junction table here because one class might have multiple teachers helping out.

CREATE TABLE IF NOT EXISTS class_teachers (
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    PRIMARY KEY (class_id, teacher_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Class Students Mapping.
-- Linking students to their classes. One student can be in many classes!

CREATE TABLE IF NOT EXISTS class_students (
    class_id INT NOT NULL,
    student_id INT NOT NULL, 
    PRIMARY KEY (class_id, student_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Quizzes Table.
-- The heart of the system. This holds the quiz titles, times, and room codes.
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    material_url VARCHAR(255) DEFAULT NULL,
    teacher_id INT NOT NULL, -- Refers to users.id
    room_code VARCHAR(10) UNIQUE NOT NULL, -- The special code students type to join.
    start_time DATETIME,
    end_time DATETIME,
    duration_minutes INT DEFAULT NULL,
    timer_type ENUM('exam', 'question') DEFAULT 'exam',
    status ENUM('draft', 'active', 'started', 'finished') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Quiz Classes Mapping.
-- This tells the system which classes are allowed to take which quiz.
CREATE TABLE IF NOT EXISTS quiz_classes (
    quiz_id INT NOT NULL,
    class_id INT NOT NULL,
    PRIMARY KEY (quiz_id, class_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Quiz Allowed Students (Manual Roster).
-- Sometimes a teacher wants to add a specific student to a quiz who isn't in the class.
CREATE TABLE IF NOT EXISTS quiz_allowed_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL, 
    UNIQUE KEY unique_roster (quiz_id, student_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Kicked Students.
-- For the naughty ones! If they cheat, the teacher can kick them out and we log why.
CREATE TABLE IF NOT EXISTS kicked_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL, 
    reason TEXT,
    kicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Questions Table.
-- I used JSON for the options column because it makes it flexible for different question types.
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer', 'multiple_selection') DEFAULT 'multiple_choice',
    options JSON DEFAULT NULL,
    correct_answer TEXT NOT NULL,
    points INT DEFAULT 1,
    time_limit_seconds INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Results Table.
-- This tracks the overall performance of a student in a quiz.
CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL, -- Refers to users.id
    quiz_id INT NOT NULL,
    score DECIMAL(5, 2) DEFAULT 0.00,
    total_points INT DEFAULT 0,
    status ENUM('waiting', 'in_progress', 'submitted', 'graded') DEFAULT 'waiting',
    is_paused TINYINT(1) DEFAULT 0,
    is_blocked TINYINT(1) DEFAULT 0,
    started_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Student Answers.
-- I added this to store every single answer a student picks. It's great for detailed reporting.
CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer TEXT,
    is_correct BOOLEAN DEFAULT NULL,
    points_awarded DECIMAL(5, 2) DEFAULT 0.00,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (result_id, question_id),
    FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Violations Table.
-- Security is important! If a student tries to switch tabs, we log a violation.
CREATE TABLE IF NOT EXISTS violations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL, -- Refers to users.id
    quiz_id INT NOT NULL,
    violation_type VARCHAR(50) NOT NULL,
    description TEXT,
    occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Invalid Entries.
-- I built this as an audit log for when people try to join with the wrong IDs.
CREATE TABLE IF NOT EXISTS invalid_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id_attempt VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Initial Data (Default Admin).
-- I added a default admin so we can log in right away. 
-- The password is 'admin123'. Change it after your first login!
INSERT INTO users (first_name, last_name, username, password, user_type, status, user_id)
VALUES ('System', 'Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active', 'ADM-1');

-- Turning FK checks back on!
SET FOREIGN_KEY_CHECKS = 1;
