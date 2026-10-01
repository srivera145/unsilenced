# Shared by scripts/backup.sh and scripts/row-counts.sh. Source it; do not run it.
#
# DB_* settings come from the environment first, then from the project's .env
# (plain KEY=value lines; surrounding quotes are removed), as in the app.
# The password goes into a temporary option file readable only by this user,
# never onto a command line where other users could see it in the process list.

project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

env_value() {
    local key=$1 value
    value=$(printenv "$key" || true)
    if [ -z "$value" ] && [ -f "$project_root/.env" ]; then
        value=$(grep -E "^${key}=" "$project_root/.env" | tail -n 1 | cut -d= -f2- | tr -d '\r')
        value=${value#\"}; value=${value%\"}
        value=${value#\'}; value=${value%\'}
    fi
    printf '%s' "$value"
}

# write_client_options <file>: a [client] section with host, port, user and password.
write_client_options() {
    local file=$1 password
    password=$(env_value DB_PASSWORD)
    password=${password//\\/\\\\}
    password=${password//\"/\\\"}
    (
        umask 077
        {
            echo '[client]'
            local key value
            for key in host:DB_HOST port:DB_PORT user:DB_USERNAME; do
                value=$(env_value "${key#*:}")
                if [ -n "$value" ]; then
                    echo "${key%%:*}=$value"
                fi
            done
            echo "password=\"$password\""
            echo 'default-character-set=utf8mb4'
        } > "$file"
    )
}
