# OneChurch Postman collection

Import `OneChurch.postman_collection.json` into Postman. It contains 42 requests covering all application route/method pairs declared in the current controllers and the configured login endpoint. Symfony development error/profiler routes are excluded.

## Setup

1. Open the collection's **Variables** tab. `baseUrl` defaults to `http://localhost:8081`, matching the Docker webserver. Change it for another deployment, without a trailing slash.
2. Set `username` and `password` locally to an existing enabled account. No credentials or tokens are committed. Keep your credentials and token out of shared exports.
3. Send **Authentication → Login and save JWT**. On success, its script saves `token`; other API requests inherit the collection's Bearer authentication. **Health** and **Login** use no authentication.
4. Send creation requests to populate resource IDs automatically, or set the ID variables manually for existing records. Postman environment variables with matching names override collection variables; clear conflicting environment values if the captured IDs or token appear not to update.

## Suggested workflow

- Register a person, then use the person read/update/profile requests. Registration saves `personId`.
- For a relationship, register a second person and save its ID as `relatedPersonId`. Set `personId` back to the first person's ID before creating the relationship.
- Create a family, then add the person as a member. Creation saves `familyId`.
- Create a permission and role, add the permission to the role, then assign the role to a user. Set `newUsername` and `newUserPassword` before creating a user. Creation saves `permissionId`, `roleId`, and `userId`. The initial login account must already exist because user creation requires authentication.
- Record a life event after creating a person. Creation saves `lifeEventId`. The sample uses baptism with one subject; other event types may require different participant roles and details. The life-event list request includes a disabled optional `type` query parameter.

Each request checks its expected HTTP status. Creation and login scripts also check the returned ID/token before saving it. Payloads include examples of the fields accepted by the controllers, including nullable optional fields.

Send mutations deliberately against test records. Running the whole collection changes passwords, disables/enables users, removes permissions/roles, ends relationships, cancels/restores events, and deletes an event. Set `updatedUserPassword` before sending the password change request. Generated IDs refer to the latest successful creation request, so rerunning a creation changes the target of later requests.

## Current application limitations

The collection was checked against the route attributes, request mappings, domain enums, and security configuration in the source. Its 42 route/method pairs, JSON bodies, and variable references were verified locally; the requests were not executed against the live API.

The current security configuration references the deleted `App\IdentityAccess\Infrastructure\Security\UserChecker`, which blocks application container validation. Also, `LifeEventController.php` currently declares `LifeEventControlle` (missing the final `r`), which does not match its filename and can prevent service/route discovery. The collection includes the life-event routes declared in that file, but these application issues must be resolved before all requests can run.
