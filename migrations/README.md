# Database migrations

Migration files are applied once and tracked in `schema_migrations`.

Rules:
- Never edit an applied migration. Add a new migration instead.
- Filename is the migration version, e.g. `6.3.26.sql`.
- The SHA-256 checksum is stored after successful application.
- A checksum mismatch stops automatic migration processing.
