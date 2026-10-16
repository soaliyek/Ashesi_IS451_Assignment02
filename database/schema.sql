CREATE DATABASE IF NOT EXISTS passkey_rbac;
USE passkey_rbac;

CREATE TABLE IF NOT EXISTS`User` (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('STUDENT', 'LECTURER') NOT NULL,
    signupCode VARCHAR(100) NOT NULL UNIQUE,
    codeUsed BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE Credential (
    credentialID VARCHAR(1400) CHARACTER SET ascii PRIMARY KEY,
    userID INT NOT NULL,
    publicKey TEXT NOT NULL,
    signCount INT NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_credential_user
        FOREIGN KEY (userID)
        REFERENCES User(userID)
        ON DELETE CASCADE
);

CREATE TABLE Student (
    userID INT PRIMARY KEY,
    studentID VARCHAR(20) NOT NULL UNIQUE,

    CONSTRAINT fk_student_user
        FOREIGN KEY (userID)
        REFERENCES User(userID)
        ON DELETE CASCADE
);

CREATE TABLE Lecturer (
    userID INT PRIMARY KEY,
    lecturerID VARCHAR(20) NOT NULL UNIQUE,

    CONSTRAINT fk_lecturer_user
        FOREIGN KEY (userID)
        REFERENCES User(userID)
        ON DELETE CASCADE
);

CREATE TABLE Course (
    courseCode VARCHAR(20) PRIMARY KEY,
    courseTitle VARCHAR(255) NOT NULL
);

CREATE TABLE Enrollment (
    studentID VARCHAR(20) NOT NULL,
    courseCode VARCHAR(20) NOT NULL,
    grade VARCHAR(2) DEFAULT '',

    PRIMARY KEY (studentID, courseCode),

    CONSTRAINT fk_enrollment_student
        FOREIGN KEY (studentID)
        REFERENCES Student(studentID)
        ON DELETE CASCADE,

    CONSTRAINT fk_enrollment_course
        FOREIGN KEY (courseCode)
        REFERENCES Course(courseCode)
        ON DELETE CASCADE
);

CREATE TABLE Assignment (
    lecturerID VARCHAR(20) NOT NULL,
    courseCode VARCHAR(20) NOT NULL,

    PRIMARY KEY (lecturerID, courseCode),

    CONSTRAINT fk_assignment_lecturer
        FOREIGN KEY (lecturerID)
        REFERENCES Lecturer(lecturerID)
        ON DELETE CASCADE,

    CONSTRAINT fk_assignment_course
        FOREIGN KEY (courseCode)
        REFERENCES Course(courseCode)
        ON DELETE CASCADE
);


INSERT INTO User (userID, firstName, lastName, email, role, signupCode, codeUsed)
VALUES (1, 'Serwa', 'Noma', 'serwa.noma@uni.edu', 'STUDENT', 'GJ4628', FALSE),
(2, 'Kweku', 'Luckyman', 'kweku.luckyman@uni.edu', 'STUDENT', 'TY5724', FALSE),
(3, 'Kipembe', 'Kojo', 'kkipembe@uni.edu', 'LECTURER', 'DT0173', FALSE);

INSERT INTO Student(userID, studentID)
VALUES(1, '53622027'),
(2, '67842027');

INSERT INTO Lecturer(userID, lecturerID)
VALUES(3, 'HGE472');

INSERT INTO Course(courseCode, courseTitle)
VALUES('IS451', 'Information and Systems Securitry'),
('ME101', 'Engineering Mechanics'),
('CS415', 'Software Engineering'),
('CS323', 'Database Systems and Management'),
('CS452', 'Machine Learning'),
('CS254', 'Introduction to Artificial intelligence'),
('CS361', 'Introduction to Modeling and Simulation');

INSERT INTO Enrollment(studentID, courseCode, grade)
VALUES('53622027', 'IS451', 'A+'),
('53622027', 'CS323', 'B+'),
('53622027', 'CS415', 'A'),
('53622027', 'CS452', 'A+'),
('67842027', 'IS451', 'B+'),
('67842027', 'CS254', 'A+'),
('67842027', 'CS361', 'A+');

INSERT INTO Assignment(lecturerID, courseCode)
VALUES('HGE472', 'IS451');