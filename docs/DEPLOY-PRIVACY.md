# Deploying Unsilenced without logging visitors

Unsilenced promises that a visit leaves no record of who visited. The app keeps
that promise on its own side: public pages start no session, set no cookie,
load nothing from another site, write nothing to the database, and PHP errors
go to `storage/logs/app.log` with no URL query string or IP in them.

The web server, and anything in front of it, keeps its own logs. By default
those record every visitor's IP address, the page they came from (Referer) and
their browser (User-Agent). The app cannot turn that off. Whoever deploys it
must, using the settings below.

**What the settings below log instead:** time, method, path, protocol, status,
bytes sent and response time. The path is logged **without its query string**,
because two URLs carry something private in it: `/auth/magic?token=…&email=…`
(an admin's sign-in link) and `/schools?q=…` (what a visitor searched for).

## Apache

The Docker image already does this: see `docker/apache/unsilenced-privacy.conf`
and `docker/apache/000-default.conf`, wired up in the `Dockerfile`.

On any other Apache 2.4 server, put this in the server config or the site's
`<VirtualHost>`. It cannot go in `.htaccess`: Apache only accepts `LogFormat`,
`CustomLog` and `ErrorLogFormat` in server or virtual-host config, so on
shared hosting the host has to make the change.

```apache
# Access log: no %h or %a (client IP), %l or %u, Referer or User-Agent.
# %U is the path without the query string.
LogFormat "%t \"%m %U %H\" %>s %b %D" unsilenced_private

# Point the stock nicknames at it too, so no other CustomLog brings the IP back.
LogFormat "%t \"%m %U %H\" %>s %b %D" combined
LogFormat "%t \"%m %U %H\" %>s %b %D" common
LogFormat "%t \"%m %U %H\" %>s %b %D" vhost_combined

# Error log: Apache's default prefixes request errors with "[client IP:port]"
# and appends ", referer: ...". This format has neither.
ErrorLogFormat "[%{u}t] [%-m:%l] [pid %P] %F: %E: %M"

<VirtualHost *:80>
    # ...
    ErrorLog  ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log unsilenced_private
</VirtualHost>
```

Also check:

- **Other access logs.** Debian and Ubuntu enable `other-vhosts-access-log`,
  a second access log in `vhost_combined`. Disable it with
  `a2disconf other-vhosts-access-log`, or rely on the `vhost_combined` override
  above. Search the whole config for `CustomLog`, `TransferLog` and `GlobalLog`.
- **`/server-status`.** With `mod_status` loaded, extended status lists the
  client address of every request in progress. Set `ExtendedStatus Off`, or
  don't load `mod_status`.
- **Log analysers** (AWStats, Webalizer, GoAccess) read these logs. With no IP
  in them they cannot count unique visitors, which is the point.

Apply with `apachectl configtest && apachectl graceful`.

Then check the real log with `scripts/check-access-log.sh`, the same check CI
runs on the Docker image (`docs/LAUNCH-CHECKLIST.md`, section 7).

## nginx

```nginx
# The path without the query string. nginx's $uri is rewritten by try_files
# (it becomes /index.php), so take the path from $request_uri instead.
map $request_uri $request_path {
    "~^(?<path>[^?]*)" $path;
}

# No $remote_addr, $remote_user, $http_referer or $http_user_agent.
log_format unsilenced_private '[$time_local] "$request_method $request_path $server_protocol" '
                              '$status $body_bytes_sent $request_time';

server {
    # ...
    access_log /var/log/nginx/access.log unsilenced_private;

    # nginx's error log has no format setting: errors tied to a request always
    # end with "client: <IP>, server: ..., request: "GET /path?query ..."".
    # Logging only crit and above, and not logging missing files, keeps routine
    # request errors out of it.
    error_log /var/log/nginx/error.log crit;
    log_not_found off;
}
```

`log_format` and `map` belong in the `http` block, `access_log` and `error_log`
in the `server` block. Make sure no other `access_log` in `nginx.conf` or
`conf.d/` still uses the default `combined` format, which includes the IP,
Referer and User-Agent. Apply with `nginx -t && nginx -s reload`.

The `crit` error log is a mitigation, not a guarantee: a critical error during
a request is still logged with the client address. To remove that too, send
the error log to syslog (`error_log syslog:server=unix:/dev/log crit;`) and
strip `client: …` there, or turn the error log off and rely on the app's own
`storage/logs/app.log`.

**PHP-FPM** (nginx always runs PHP through it): leave `access.log` unset in the
pool config. If it is set, its default `access.format` starts with `%R`, the
client IP.

## Things the app cannot control

These keep their own logs before a request ever reaches the server above.

- **CDN or proxy (e.g. Cloudflare).** Cloudflare terminates every visitor's
  connection, so it sees their IP, Referer and User-Agent and records IPs in
  its own logs (security events, for example). What Cloudflare retains is set
  by Cloudflare and your plan, not by the app or the origin server. Settings
  that help: leave Cloudflare Web Analytics and any "Real User Monitoring" beacon
  off (both inject a third-party script, which the site's
  Content-Security-Policy would also block); don't enable Logpush, or if you
  must, leave out `ClientIP`, `ClientRequestReferer`, `ClientRequestUserAgent`
  and the query string. Behind any proxy, never log the `CF-Connecting-IP`,
  `X-Forwarded-For` or `X-Real-IP` headers at the origin.
- **The hosting provider.** Shared hosts (cPanel "Raw Access", Plesk) and
  platforms (load balancers such as AWS ALB, managed WAFs) often keep access
  logs you cannot configure. Ask before choosing a host; turn off load-balancer
  access logs where they are optional.
- **Uptime monitors and security scanners** log their own requests, not
  visitors'; nothing to do.

## What the app itself stores, for completeness

- **Public pages:** nothing. No session, cookie, rate-limit row or activity row.
  `PublicPagesFeatureTest` checks this.
- **Admin sign-in (`/login`, `/auth/*`):** the throttle stores the client IP
  with the path in `rate_limits` for one minute per window. These are admin
  routes; the public never reaches them.
- **Admin actions:** `activity_log.ip_address` records the admin's IP for each
  logged action.
- **Survivor reports (`/submit`, `/my-report`, `/share`, Phase 2):** no IP,
  User-Agent or anything else about the visitor, anywhere. They never write
  the activity log or use the throttle; the submission limit is one global
  counter. The access log above records only paths such as `POST /submit` and
  `GET /my-report`: the case key and the account travel in POST bodies, and a
  share link's token is in the URL fragment (`/share#...`), which browsers
  never send. That a path was requested at a time is still visible to whoever
  reads the access log, a CDN or the host, which is one more reason to keep
  those logs as above. `docs/SURVIVOR-REPORTS.md` has the rest.
- **PHP errors:** `storage/logs/app.log`. `ErrorHandler` logs the exception's
  class, message, file and line only, and scrubs IP addresses and query
  strings from the message.
