#Ashesi University - IS451: Information and Systems Security

Dashboard Elements

Profile
Change Role

Grades
View Grade
Course Roster
Edit Grades

- Using Fetch API for all API requests

## Passkey registration

Open `http://localhost/Ashesi_IS451_Assignment02/portal/authentication/signup.php` in a WebAuthn-capable browser and register with an unused signup code from the `User` table. The code is consumed only after the browser passkey is verified. Registration uses `localhost` for local development; deploy with HTTPS.

For an existing database, widen the credential identifier column before registering passkeys:

```sql
ALTER TABLE Credential
    MODIFY credentialID VARCHAR(1400) CHARACTER SET ascii;
```

## Dashboard
Right, for the dashboard, we have the same for both students and lecturers.
First of, we are going to have the profile part which will be on to. It will have details like the firstName and LastName, studentID/LectuerID (not userID), role which will be in a field, and a button to set a written role.
This is because the lectuer want to see that if lecturer or student try to change their role, it does not work.
You will see how I structured that in the placeholder based html markup.

The next big part is for big things.
I want a horizontal option selection bar with 3 buttons each corresponding to a subdivision. And when a button is clicked, its subdivision appears while the other subdivisions hide.

SUBDIVISION 1: Grades
When that is clicked, for a student, they will see their grades for each of the courses they are enrolled in.
For a lecturer, it shows nothing.

SUBDIVISION 2: Edit Grades
When clicked, a search bar for studentID and a query button appears. If a student enter their own ID, they can see all their grades for all the courses, as in grades, just that in this case, the grade for each course will be in a text entry where they can modify to any grade they want and when they click to set (modify), the api refuses. If they enter another student's id, the api refuses to send the data back. for a lectuer, if they query a student's grade, we will check if the student is part of a course they teach and we only display the grade for that course, and they, unlike students, can edit the grade.

SUBDIVISION 3: Course Roster
to display students and their grades in a course the lectuer is teaching.
For students, they don't see nothing.