CREATE DATABASE IF NOT EXISTS passkey_rbac;
USE passkey_rbac;

CREATE TABLE User (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('STUDENT', 'LECTURER') NOT NULL,
    signupCode VARCHAR(100) NOT NULL UNIQUE,
    codeUsed BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE Credential (
    credentialID VARCHAR(255) PRIMARY KEY,
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
    grade DECIMAL(5,2) NULL,

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
VALUES (2, 'Kweku', 'Luckyman', 'kweku.luckyman@uni.edu', 'STUDENT', 'TY5724', FALSE),
VALUES (3, 'Kipembe', 'Kojo', 'kkipembe@uni.edu', 'LECTUER', 'DT0173', FALSE);

