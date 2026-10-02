I want to create a church management portal that will allow administrators to login and management church members (families with members as adults and children, relationships within the family), events, attendance, giving, sunday school. I want the portal to also configurations for email smtp/api providers, sms providers (etc). Volunteers must also be part of the system.

Help me to design this system by the following

1. Establishing Contexts
2. Processing folder structures for better cohesion

This project will be done using the symfony framework


Follow the patterns on the AuditAccess Context and then implement the same patters for mappers as well as audit timestamps. This must be done on the backend/src/People context.

You can find and example of reconstitute, and audittimestamp from backend/src/IdentityAccess/Domain/Model/UserAccount.php. An example of a mapper can b found on backend/src/IdentityAccess/Infrastructure/Persistence/DBAL/UserAccountMapper.php