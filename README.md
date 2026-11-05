# GeoNet Connect

## Deploy on Vercel with MySQL

This project uses MySQL. Keep its existing schema and query code by connecting
it to a hosted MySQL service. Vercel cannot connect to the MySQL server running
only in XAMPP on your computer.

1. Create a hosted MySQL database and make it reachable from Vercel using the
   database provider's secure external connection settings.
2. For a new empty database, import `sql/schema.sql` first and then
   `sql/seed.sql`. To migrate existing XAMPP data, export it from phpMyAdmin
   and import that export into the hosted database instead. The schema script
   drops and recreates tables; do not run it over data you need to preserve.
3. Run `sql/vercel_sessions.sql` on the hosted database. This creates the
   shared session table without giving the application permission to create
   tables at runtime.
4. In the Vercel project, add these environment variables for Production and
   Preview as needed:
   - `DB_HOST`: database hostname, not `127.0.0.1`
   - `DB_PORT`: database TCP port, usually `3306`
   - `DB_NAME`: database name
   - `DB_USER`: restricted database user
   - `DB_PASSWORD`: database password
5. Redeploy after setting the variables. Vercel detects the root `Dockerfile`
   and builds the app with FrankenPHP and PDO MySQL.
6. Test login and application pages on the deployed URL.

The local database defaults in `config/database.php` are for development only.
On Vercel, the app requires hosted database environment variables. Do not
commit database credentials or local SMS logs.

The public `install.php` installer is disabled on Vercel because it drops and
recreates tables. Manage hosted schema changes through explicit migrations.
Change the seeded demo admin password after importing initial data.
