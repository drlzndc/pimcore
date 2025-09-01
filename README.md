# Pimcore

Clone the repo and then run (in order):

1. `docker compose up --build --detach` (sets up all the services; might take couple of minutes to download images)
2. `docker exec pimcore composer install` (installs Composer packages in the `pimcore` container)
3. `docker compose exec pimcore vendor/bin/pimcore-install --mysql-host-socket=db` (installs Pimcore)

4. `docker cp ./database.sql db:/var/lib/database.sql` (copies database dump into `db` container)
5. `docker exec --interactive --tty db bash` (opens shell inside `db` container)
6. `mysql -u root -p pimcore < /var/lib/database.sql` (enter password `ROOT`; imports database dump)

You can close the shells now, and visit `localhost:8080` to see the app.

If you're having permission issues, make sure the app's `var/` directory and its subdirectories are writable.