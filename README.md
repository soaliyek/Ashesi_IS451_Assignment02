#Ashesi University - IS451: Information and Systems Security


Dashboard Elements

Profile
Change Role

Grades
View Grade
Course Roster
Edit Grades

## Passkey registration

Open `http://localhost/Ashesi_IS451_Assignment02/portal/authentication/signup.php` in a WebAuthn-capable browser and register with an unused signup code from the `User` table. The code is consumed only after the browser passkey is verified. Registration uses `localhost` for local development; deploy with HTTPS.

For an existing database, widen the credential identifier column before registering passkeys:

```sql
ALTER TABLE Credential
    MODIFY credentialID VARCHAR(1400) CHARACTER SET ascii;
```